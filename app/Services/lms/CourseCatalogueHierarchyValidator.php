<?php

namespace App\Services\lms;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course Catalogue Reader Agent - Continuous Hierarchy Validator.
 *
 * Validates the complete 5-tier Teach/Learn learning content hierarchy:
 *   Standard -> Subject -> Chapter -> Topic -> Intelligence
 *
 * Requirements:
 * - Read-only against all LMS tables (standard, sub_std_map, chapter_master, topic_master, lms_concept, content_master).
 * - Scoped strictly to the tenant's sub_institute_id.
 * - End-to-end sequential verification without early stopping.
 * - Detects missing content at every level and broken/orphaned mappings.
 * - Idempotent, deduplicated using data fingerprints to avoid unnecessary re-computation.
 * - Tracks newly discovered vs resolved issues across continuous runs.
 */
class CourseCatalogueHierarchyValidator
{
    public const AGENT_KEY = 'k12_course_catalogue';
    public const DEFAULT_INTERVAL_MINUTES = 30;

    protected ?ContentAgentService $contentAgent = null;

    /**
     * Resolve the Content Agent Service.
     */
    public function getContentAgent(): ContentAgentService
    {
        if ($this->contentAgent === null) {
            $this->contentAgent = app(ContentAgentService::class);
        }
        return $this->contentAgent;
    }

    /**
     * Perform the complete 5-tier hierarchy validation and coordinate with Content Agent.
     *
     * @param int $subInstituteId The tenant institute ID
     * @param bool $force Force revalidation even if data hash hasn't changed
     * @param int|null $intervalSeconds Loop interval in seconds
     * @param bool|null $autoGenerateContent Whether to trigger Content Agent for missing intelligence
     * @param int $maxGeneratePerScan Maximum topics to generate/verify per scan cycle (default: 15)
     * @return array<string, mixed> Detailed validation report
     */
    public function validate(
        int $subInstituteId,
        bool $force = false,
        ?int $intervalSeconds = null,
        ?bool $autoGenerateContent = null,
        int $maxGeneratePerScan = 15
    ): array {
        $lockKey = 'lms_hierarchy_validation_lock_' . $subInstituteId;
        $lock = Cache::lock($lockKey, 300);

        if (!$lock->get()) {
            $latest = $this->getLatestRun($subInstituteId);
            return [
                'status' => 'locked',
                'message' => 'Hierarchy validation is currently already running for institute ' . $subInstituteId,
                'latest_run' => $latest,
            ];
        }

        $startedAt = Carbon::now();
        $startTime = microtime(true);

        try {
            $fingerprint = $this->calculateFingerprint($subInstituteId);
            $previousRun = $this->getLatestRunRow($subInstituteId);
            $agentState = $this->getOrCreateAgentState($subInstituteId);
            $intervalSec = $intervalSeconds ?? (int) ($agentState->interval_seconds ?? (self::DEFAULT_INTERVAL_MINUTES * 60));
            $nextScheduled = Carbon::now()->addSeconds($intervalSec);

            $autoGen = $autoGenerateContent ?? (isset($agentState->auto_generate_content) ? (bool) $agentState->auto_generate_content : true);
            $remainingPending = $autoGen ? $this->getContentAgent()->countRemainingPendingTopics($subInstituteId) : 0;

            // Check if unchanged (only skip if hash matches AND no pending topics require content generation)
            if (!$force && $previousRun && $previousRun->data_hash === $fingerprint && $previousRun->status === 'completed' && (!$autoGen || $remainingPending === 0)) {
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                // Update timestamps
                DB::table('lms_catalogue_hierarchy_validations')
                    ->where('id', $previousRun->id)
                    ->update([
                        'updated_at' => Carbon::now(),
                    ]);

                $this->updateAgentStateRecord($subInstituteId, 'unchanged', $fingerprint, $startedAt, $nextScheduled, $previousRun);

                $prevSummary = json_decode($previousRun->summary ?? '{}', true) ?: [];
                $prevIssues = json_decode($previousRun->issues ?? '{}', true) ?: [];

                return [
                    'agent_key' => self::AGENT_KEY,
                    'agent_name' => 'Course Catalogue Reader Agent',
                    'sub_institute_id' => $subInstituteId,
                    'status' => 'unchanged',
                    'message' => 'LMS content hierarchy data is unchanged since last check (fingerprint matches). Processing skipped.',
                    'data_hash' => $fingerprint,
                    'last_run_time' => $previousRun->started_at,
                    'next_scheduled_run' => $nextScheduled->toIso8601String(),
                    'interval_seconds' => $intervalSec,
                    'duration_ms' => $durationMs,
                    'counts' => [
                        'standards_checked' => (int) $previousRun->standards_count,
                        'subjects_checked' => (int) $previousRun->subjects_count,
                        'chapters_checked' => (int) $previousRun->chapters_count,
                        'topics_checked' => (int) $previousRun->topics_count,
                        'intelligence_configs_checked' => (int) $previousRun->intelligence_count,
                        'complete_hierarchies_validated' => (int) $previousRun->complete_hierarchies_count,
                        'missing_subjects_count' => (int) $previousRun->missing_subjects_count,
                        'missing_chapters_count' => (int) $previousRun->missing_chapters_count,
                        'missing_topics_count' => (int) $previousRun->missing_topics_count,
                        'missing_intelligence_count' => (int) $previousRun->missing_intelligence_count,
                        'broken_mappings_count' => (int) $previousRun->broken_mappings_count,
                        'total_issues_detected' => (int) $previousRun->issues_count,
                    ],
                    'summary' => $prevSummary,
                    'issues' => $prevIssues,
                    'newly_discovered_issues' => [],
                    'resolved_issues' => [],
                    'content_agent' => [
                        'auto_generate_enabled' => $autoGen,
                        'processed_count' => 0,
                        'stats' => $this->getContentAgent()->getContentStats($subInstituteId),
                    ],
                ];
            }

            // Perform full 5-tier evaluation with Content Agent collaboration
            $report = $this->runFullScan($subInstituteId, $fingerprint, $startedAt, $autoGen, $maxGeneratePerScan);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $report['duration_ms'] = $durationMs;
            $report['interval_seconds'] = $intervalSec;
            $report['next_scheduled_run'] = $nextScheduled->toIso8601String();

            // Compute diff with previous run
            $diff = $this->computeIssueDiff($previousRun, $report['issues']);
            $report['newly_discovered_issues'] = $diff['newly_discovered'];
            $report['resolved_issues'] = $diff['resolved'];

            // Persist run
            $this->saveRunRecord($subInstituteId, $report, $startedAt, $durationMs);
            $this->updateAgentStateRecord($subInstituteId, $report['status'], $report['data_hash'] ?? $fingerprint, $startedAt, $nextScheduled, (object) [
                'summary_counts' => json_encode($report['counts']),
            ]);

            return $report;
        } finally {
            $lock->release();
        }
    }

    /**
     * Compute a cryptographic content fingerprint across all 5 levels.
     */
    public function calculateFingerprint(int $subInstituteId): string
    {
        $stdMeta = DB::table('standard')->where('sub_institute_id', $subInstituteId)->selectRaw('COUNT(*) as cnt, MAX(id) as max_id')->first();
        $subMeta = DB::table('sub_std_map')->where('sub_institute_id', $subInstituteId)->where('allow_content', 'Yes')->selectRaw('COUNT(*) as cnt, MAX(id) as max_id')->first();
        $chMeta = DB::table('chapter_master')->where('sub_institute_id', $subInstituteId)->where('show_hide', 1)->selectRaw('COUNT(*) as cnt, MAX(id) as max_id')->first();
        $tpMeta = DB::table('topic_master')->where('sub_institute_id', $subInstituteId)->selectRaw('COUNT(*) as cnt, MAX(id) as max_id')->first();
        $conceptMeta = DB::table('lms_concept')->where('sub_institute_id', $subInstituteId)->selectRaw('COUNT(*) as cnt, MAX(id) as max_id')->first();
        $contentMeta = DB::table('content_master')->where('sub_institute_id', $subInstituteId)->selectRaw('COUNT(*) as cnt, MAX(id) as max_id')->first();

        $raw = sprintf(
            'tenant:%d|std:%d,%d|sub:%d,%d|ch:%d,%d|tp:%d,%d|concept:%d,%d|content:%d,%d',
            $subInstituteId,
            $stdMeta->cnt ?? 0, $stdMeta->max_id ?? 0,
            $subMeta->cnt ?? 0, $subMeta->max_id ?? 0,
            $chMeta->cnt ?? 0, $chMeta->max_id ?? 0,
            $tpMeta->cnt ?? 0, $tpMeta->max_id ?? 0,
            $conceptMeta->cnt ?? 0, $conceptMeta->max_id ?? 0,
            $contentMeta->cnt ?? 0, $contentMeta->max_id ?? 0
        );

        return hash('sha256', $raw);
    }

    /**
     * Execute full set-based scan of the 5-tier content hierarchy.
     */
    protected function runFullScan(
        int $subInstituteId,
        string $fingerprint,
        Carbon $startedAt,
        bool $autoGenerateContent = false,
        int $maxGeneratePerScan = 15
    ): array
    {
        // 1. Standards
        $standards = DB::table('standard')
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->keyBy('id');

        // 2. Subjects with content allowed
        $subjects = DB::table('sub_std_map as m')
            ->where('m.sub_institute_id', $subInstituteId)
            ->where('m.allow_content', 'Yes')
            ->select('m.standard_id', 'm.subject_id', 'm.display_name')
            ->groupBy('m.standard_id', 'm.subject_id', 'm.display_name')
            ->get();

        // 3. Chapters
        $chapters = DB::table('chapter_master')
            ->where('sub_institute_id', $subInstituteId)
            ->where('show_hide', 1)
            ->get(['id', 'standard_id', 'subject_id', 'chapter_name', 'syear'])
            ->keyBy('id');

        // 4. Topics
        $topics = DB::table('topic_master')
            ->where('sub_institute_id', $subInstituteId)
            ->get(['id', 'chapter_id', 'name', 'source_evidence', 'confidence', 'evidence_verified'])
            ->keyBy('id');

        // 5. Intelligence mappings
        $conceptTopicIds = DB::table('lms_concept')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('topic_id')
            ->pluck('topic_id')
            ->flip()
            ->all();

        $contentTopicIds = DB::table('content_master')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('topic_id')
            ->pluck('topic_id')
            ->flip()
            ->all();

        // Check if semantic intelligence exists
        $semanticChapterIds = [];
        if (Schema::hasTable('semantic_intelligence')) {
            $semanticChapterIds = DB::table('semantic_intelligence')
                ->where('sub_institute_id', $subInstituteId)
                ->pluck('chapter_id')
                ->flip()
                ->all();
        }

        // Group subjects by standard_id
        $subjectsByStandard = [];
        foreach ($subjects as $s) {
            $subjectsByStandard[$s->standard_id][] = $s;
        }

        // Group chapters by standard_id:subject_id
        $chaptersByCourse = [];
        $orphanedChapters = [];
        foreach ($chapters as $ch) {
            if (!isset($standards[$ch->standard_id])) {
                $orphanedChapters[] = [
                    'chapter_id' => $ch->id,
                    'chapter_name' => $ch->chapter_name,
                    'standard_id' => $ch->standard_id,
                    'subject_id' => $ch->subject_id,
                    'issue' => 'Chapter standard_id does not resolve to an active standard in this institute',
                ];
            } else {
                $chaptersByCourse[$ch->standard_id . ':' . $ch->subject_id][] = $ch;
            }
        }

        // Group topics by chapter_id
        $topicsByChapter = [];
        $orphanedTopics = [];
        foreach ($topics as $tp) {
            if (!isset($chapters[$tp->chapter_id])) {
                $orphanedTopics[] = [
                    'topic_id' => $tp->id,
                    'topic_name' => $tp->name,
                    'chapter_id' => $tp->chapter_id,
                    'issue' => 'Topic chapter_id does not resolve to any active chapter in chapter_master',
                ];
            } else {
                $topicsByChapter[$tp->chapter_id][] = $tp;
            }
        }

        // Hierarchy issues containers
        $missingSubjects = [];
        $missingChapters = [];
        $missingTopics = [];
        $missingIntelligence = [];

        $generatedThisScan = 0;
        $contentActions = [];

        $totalCheckedStandards = count($standards);
        $totalCheckedSubjects = 0;
        $totalCheckedChapters = 0;
        $totalCheckedTopics = 0;
        $totalCheckedIntel = 0;
        $completeHierarchies = 0;

        foreach ($standards as $stdId => $std) {
            $subs = $subjectsByStandard[$stdId] ?? [];
            if (empty($subs)) {
                $missingSubjects[] = [
                    'standard_id' => $stdId,
                    'standard_name' => $std->name,
                    'issue' => 'Standard is configured but has no subjects with content allowed (allow_content = "Yes")',
                ];
                continue;
            }

            foreach ($subs as $sub) {
                $totalCheckedSubjects++;
                $courseKey = $stdId . ':' . $sub->subject_id;
                $chs = $chaptersByCourse[$courseKey] ?? [];

                if (empty($chs)) {
                    $missingChapters[] = [
                        'standard_id' => $stdId,
                        'standard_name' => $std->name,
                        'subject_id' => $sub->subject_id,
                        'subject_name' => $sub->display_name,
                        'issue' => 'Subject is mapped to standard but contains no active chapters (chapter_master)',
                    ];
                    continue;
                }

                foreach ($chs as $ch) {
                    $totalCheckedChapters++;
                    $tps = $topicsByChapter[$ch->id] ?? [];

                    if (empty($tps)) {
                        $missingTopics[] = [
                            'standard_id' => $stdId,
                            'standard_name' => $std->name,
                            'subject_id' => $sub->subject_id,
                            'subject_name' => $sub->display_name,
                            'chapter_id' => $ch->id,
                            'chapter_name' => $ch->chapter_name,
                            'issue' => 'Chapter is configured but contains no topics (topic_master)',
                        ];
                        continue;
                    }

                    foreach ($tps as $tp) {
                        $totalCheckedTopics++;
                        $hasConcept = isset($conceptTopicIds[$tp->id]);
                        $hasContent = isset($contentTopicIds[$tp->id]);
                        $hasEvidence = !empty($tp->source_evidence) || $tp->confidence > 0 || $tp->evidence_verified == 1;
                        $hasSemantic = isset($semanticChapterIds[$ch->id]);

                        // Agent 2 (Content Agent) automated collaboration:
                        // As soon as intelligence is checked, if content is missing and auto-generation is active,
                        // trigger Content Agent immediately to verify or generate content and store its ID!
                        if ($autoGenerateContent && !$hasContent && $generatedThisScan < $maxGeneratePerScan) {
                            $agentRes = $this->getContentAgent()->processTopicIntelligence($subInstituteId, [
                                'standard_id' => $stdId,
                                'standard_name' => $std->name,
                                'subject_id' => $sub->subject_id,
                                'subject_name' => $sub->display_name,
                                'chapter_id' => $ch->id,
                                'chapter_name' => $ch->chapter_name,
                                'topic_id' => $tp->id,
                                'topic_name' => $tp->name,
                            ]);

                            if (!empty($agentRes['content_id'])) {
                                $hasContent = true;
                                $contentTopicIds[$tp->id] = true;
                                $generatedThisScan++;
                                $contentActions[] = [
                                    'topic_id' => $tp->id,
                                    'topic_name' => $tp->name,
                                    'standard_name' => $std->name,
                                    'subject_name' => $sub->display_name,
                                    'chapter_name' => $ch->chapter_name,
                                    'action' => $agentRes['action'] ?? 'generated_content',
                                    'content_id' => $agentRes['content_id'],
                                    'concept_id' => $agentRes['concept_id'] ?? null,
                                    'title' => $agentRes['content_title'] ?? $tp->name,
                                ];
                            }
                        }

                        // Check if complete intelligence exists (including newly verified or generated content)
                        if ($hasConcept || $hasContent || $hasEvidence) {
                            $totalCheckedIntel++;
                            $completeHierarchies++;
                        } else {
                            $missingIntelligence[] = [
                                'standard_id' => $stdId,
                                'standard_name' => $std->name,
                                'subject_id' => $sub->subject_id,
                                'subject_name' => $sub->display_name,
                                'chapter_id' => $ch->id,
                                'chapter_name' => $ch->chapter_name,
                                'topic_id' => $tp->id,
                                'topic_name' => $tp->name,
                                'has_chapter_semantic' => $hasSemantic,
                                'issue' => 'Topic exists but has no associated intelligence content or configuration (no concepts in lms_concept, no content in content_master, and no verified evidence on topic)',
                            ];
                        }
                    }
                }
            }
        }

        $brokenMappings = array_merge($orphanedTopics, $orphanedChapters);
        $totalIssues = count($missingSubjects) + count($missingChapters) + count($missingTopics) + count($missingIntelligence) + count($brokenMappings);

        if ($generatedThisScan > 0) {
            $fingerprint = $this->calculateFingerprint($subInstituteId);
        }

        $nextScheduled = Carbon::now()->addMinutes(self::DEFAULT_INTERVAL_MINUTES);

        return [
            'agent_key' => self::AGENT_KEY,
            'agent_name' => 'Course Catalogue Reader Agent',
            'sub_institute_id' => $subInstituteId,
            'status' => 'completed',
            'data_hash' => $fingerprint,
            'last_run_time' => $startedAt->toIso8601String(),
            'next_scheduled_run' => $nextScheduled->toIso8601String(),
            'counts' => [
                'standards_checked' => $totalCheckedStandards,
                'subjects_checked' => $totalCheckedSubjects,
                'chapters_checked' => $totalCheckedChapters,
                'topics_checked' => $totalCheckedTopics,
                'intelligence_configs_checked' => $totalCheckedIntel,
                'complete_hierarchies_validated' => $completeHierarchies,
                'missing_subjects_count' => count($missingSubjects),
                'missing_chapters_count' => count($missingChapters),
                'missing_topics_count' => count($missingTopics),
                'missing_intelligence_count' => count($missingIntelligence),
                'broken_mappings_count' => count($brokenMappings),
                'total_issues_detected' => $totalIssues,
            ],
            'summary' => [
                'hierarchy_health' => $totalIssues === 0 ? 'HEALTHY' : ($completeHierarchies > 0 ? 'PARTIAL_GAPS_DETECTED' : 'CRITICAL_GAPS'),
                'evaluation_path' => 'Standard -> Subject -> Chapter -> Topic -> Intelligence',
                'standards_checked' => $totalCheckedStandards,
                'complete_chains' => $completeHierarchies,
                'gaps_summary' => sprintf(
                    '%d missing subjects, %d missing chapters, %d missing topics, %d missing intelligence configurations, %d broken/orphaned mappings',
                    count($missingSubjects), count($missingChapters), count($missingTopics), count($missingIntelligence), count($brokenMappings)
                ),
            ],
            'issues' => [
                'missing_subjects' => $missingSubjects,
                'missing_chapters' => $missingChapters,
                'missing_topics' => $missingTopics,
                'missing_intelligence' => $missingIntelligence,
                'broken_mappings' => $brokenMappings,
            ],
            'content_agent' => [
                'auto_generate_enabled' => $autoGenerateContent,
                'processed_count' => $generatedThisScan,
                'actions' => $contentActions,
                'stats' => $this->getContentAgent()->getContentStats($subInstituteId),
            ],
        ];
    }

    /**
     * Compute newly discovered vs resolved issues between runs.
     */
    protected function computeIssueDiff(?object $previousRun, array $currentIssues): array
    {
        if (!$previousRun || empty($previousRun->issues)) {
            $allCurrent = [];
            foreach ($currentIssues as $category => $items) {
                foreach ($items as $item) {
                    $key = $this->issueFingerprint($category, $item);
                    $allCurrent[] = array_merge($item, ['category' => $category, 'fingerprint' => $key]);
                }
            }
            return ['newly_discovered' => $allCurrent, 'resolved' => []];
        }

        $prevIssues = json_decode($previousRun->issues, true) ?: [];
        $prevMap = [];
        foreach ($prevIssues as $category => $items) {
            foreach ($items as $item) {
                $prevMap[$this->issueFingerprint($category, $item)] = array_merge($item, ['category' => $category]);
            }
        }

        $currMap = [];
        foreach ($currentIssues as $category => $items) {
            foreach ($items as $item) {
                $currMap[$this->issueFingerprint($category, $item)] = array_merge($item, ['category' => $category]);
            }
        }

        $newlyDiscovered = [];
        foreach ($currMap as $fp => $item) {
            if (!isset($prevMap[$fp])) {
                $newlyDiscovered[] = $item;
            }
        }

        $resolved = [];
        foreach ($prevMap as $fp => $item) {
            if (!isset($currMap[$fp])) {
                $resolved[] = $item;
            }
        }

        return [
            'newly_discovered' => $newlyDiscovered,
            'resolved' => $resolved,
        ];
    }

    /**
     * Stable string key for a specific hierarchy issue.
     */
    protected function issueFingerprint(string $category, array $item): string
    {
        return sprintf(
            '%s:%s:%s:%s:%s',
            $category,
            $item['standard_id'] ?? 'none',
            $item['subject_id'] ?? 'none',
            $item['chapter_id'] ?? 'none',
            $item['topic_id'] ?? 'none'
        );
    }

    /**
     * Save validation run to database.
     */
    protected function saveRunRecord(int $subInstituteId, array $report, Carbon $startedAt, int $durationMs): void
    {
        $counts = $report['counts'];

        DB::table('lms_catalogue_hierarchy_validations')->insert([
            'sub_institute_id' => $subInstituteId,
            'agent_key' => self::AGENT_KEY,
            'status' => 'completed',
            'data_hash' => $report['data_hash'],
            'standards_count' => $counts['standards_checked'],
            'subjects_count' => $counts['subjects_checked'],
            'chapters_count' => $counts['chapters_checked'],
            'topics_count' => $counts['topics_checked'],
            'intelligence_count' => $counts['intelligence_configs_checked'],
            'complete_hierarchies_count' => $counts['complete_hierarchies_validated'],
            'issues_count' => $counts['total_issues_detected'],
            'missing_subjects_count' => $counts['missing_subjects_count'],
            'missing_chapters_count' => $counts['missing_chapters_count'],
            'missing_topics_count' => $counts['missing_topics_count'],
            'missing_intelligence_count' => $counts['missing_intelligence_count'],
            'broken_mappings_count' => $counts['broken_mappings_count'],
            'summary' => json_encode($report['summary']),
            'issues' => json_encode($report['issues']),
            'newly_discovered' => json_encode(array_slice($report['newly_discovered_issues'], 0, 100)),
            'resolved' => json_encode(array_slice($report['resolved_issues'], 0, 100)),
            'started_at' => $startedAt,
            'finished_at' => Carbon::now(),
            'duration_ms' => $durationMs,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Get the latest raw row for an institute.
     */
    protected function getLatestRunRow(int $subInstituteId): ?object
    {
        return DB::table('lms_catalogue_hierarchy_validations')
            ->where('sub_institute_id', $subInstituteId)
            ->where('agent_key', self::AGENT_KEY)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get the latest structured run report for an institute.
     */
    public function getLatestRun(int $subInstituteId): ?array
    {
        $row = $this->getLatestRunRow($subInstituteId);
        if (!$row) {
            return null;
        }

        $nextScheduled = Carbon::parse($row->started_at)->addMinutes(self::DEFAULT_INTERVAL_MINUTES);

        return [
            'id' => $row->id,
            'agent_key' => $row->agent_key,
            'agent_name' => 'Course Catalogue Reader Agent',
            'sub_institute_id' => (int) $row->sub_institute_id,
            'status' => $row->status,
            'data_hash' => $row->data_hash,
            'last_run_time' => $row->started_at,
            'finished_at' => $row->finished_at,
            'next_scheduled_run' => $nextScheduled->toIso8601String(),
            'duration_ms' => $row->duration_ms,
            'counts' => [
                'standards_checked' => (int) $row->standards_count,
                'subjects_checked' => (int) $row->subjects_count,
                'chapters_checked' => (int) $row->chapters_count,
                'topics_checked' => (int) $row->topics_count,
                'intelligence_configs_checked' => (int) $row->intelligence_count,
                'complete_hierarchies_validated' => (int) $row->complete_hierarchies_count,
                'missing_subjects_count' => (int) $row->missing_subjects_count,
                'missing_chapters_count' => (int) $row->missing_chapters_count,
                'missing_topics_count' => (int) $row->missing_topics_count,
                'missing_intelligence_count' => (int) $row->missing_intelligence_count,
                'broken_mappings_count' => (int) $row->broken_mappings_count,
                'total_issues_detected' => (int) $row->issues_count,
            ],
            'summary' => json_decode($row->summary ?? '{}', true) ?: [],
            'issues' => json_decode($row->issues ?? '{}', true) ?: [],
            'newly_discovered_issues' => json_decode($row->newly_discovered ?? '[]', true) ?: [],
            'resolved_issues' => json_decode($row->resolved ?? '[]', true) ?: [],
        ];
    }

    /**
     * Get or initialize agent continuous monitoring state for an institute.
     */
    public function getOrCreateAgentState(int $subInstituteId, int $defaultIntervalSeconds = 60): object
    {
        if (!Schema::hasTable('lms_catalogue_agent_state')) {
            return (object) [
                'sub_institute_id' => $subInstituteId,
                'is_active' => 1,
                'interval_seconds' => $defaultIntervalSeconds,
                'last_run_at' => null,
                'last_run_status' => null,
                'next_run_at' => null,
                'last_heartbeat_at' => null,
            ];
        }

        $row = DB::table('lms_catalogue_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$row) {
            $now = Carbon::now();
            $id = DB::table('lms_catalogue_agent_state')->insertGetId([
                'sub_institute_id' => $subInstituteId,
                'agent_key' => self::AGENT_KEY,
                'is_active' => true,
                'interval_seconds' => $defaultIntervalSeconds,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $row = DB::table('lms_catalogue_agent_state')->where('id', $id)->first();
        }

        return $row;
    }

    /**
     * Update state record after validation run.
     */
    protected function updateAgentStateRecord(
        int $subInstituteId,
        string $status,
        string $fingerprint,
        Carbon $startedAt,
        Carbon $nextScheduled,
        ?object $data = null
    ): void {
        if (!Schema::hasTable('lms_catalogue_agent_state')) {
            return;
        }

        $summaryCounts = null;
        if ($data && isset($data->summary_counts)) {
            $summaryCounts = is_string($data->summary_counts) ? $data->summary_counts : json_encode($data->summary_counts);
        } elseif ($data && isset($data->summary)) {
            $summaryCounts = json_encode([
                'standards_checked' => $data->standards_count ?? 0,
                'subjects_checked' => $data->subjects_count ?? 0,
                'chapters_checked' => $data->chapters_count ?? 0,
                'topics_checked' => $data->topics_count ?? 0,
                'intelligence_configs_checked' => $data->intelligence_count ?? 0,
                'complete_hierarchies_validated' => $data->complete_hierarchies_count ?? 0,
                'total_issues_detected' => $data->issues_count ?? 0,
            ]);
        }

        $completedTasks = 0;
        $generatedTasks = 0;
        $verifiedTasks = 0;
        if (Schema::hasTable('lms_catalogue_content_tasks')) {
            $completedTasks = DB::table('lms_catalogue_content_tasks')->where('sub_institute_id', $subInstituteId)->count();
            $generatedTasks = DB::table('lms_catalogue_content_tasks')->where('sub_institute_id', $subInstituteId)->where('status', 'generated_new')->count();
            $verifiedTasks = DB::table('lms_catalogue_content_tasks')->where('sub_institute_id', $subInstituteId)->where('status', 'verified_existing')->count();
        }

        DB::table('lms_catalogue_agent_state')->updateOrInsert(
            ['sub_institute_id' => $subInstituteId],
            [
                'agent_key' => self::AGENT_KEY,
                'is_active' => true,
                'last_run_at' => $startedAt,
                'last_run_status' => $status,
                'next_run_at' => $nextScheduled,
                'latest_hash' => $fingerprint,
                'summary_counts' => $summaryCounts,
                'content_agent_status' => 'active',
                'content_tasks_completed' => $completedTasks,
                'content_tasks_generated' => $generatedTasks,
                'content_tasks_verified' => $verifiedTasks,
                'last_heartbeat_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Activate continuous monitoring loop for an institute.
     */
    public function activateContinuousMonitoring(int $subInstituteId, int $intervalSeconds = 60): array
    {
        $now = Carbon::now();
        $next = $now->copy(); // immediate first execution

        DB::table('lms_catalogue_agent_state')->updateOrInsert(
            ['sub_institute_id' => $subInstituteId],
            [
                'agent_key' => self::AGENT_KEY,
                'is_active' => true,
                'interval_seconds' => max(5, $intervalSeconds),
                'next_run_at' => $next,
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
            ]
        );

        // Run initial validation pass
        $report = $this->validate($subInstituteId, false, $intervalSeconds);

        return [
            'status' => 'activated',
            'message' => 'Course Catalogue Reader Agent continuous monitoring loop successfully activated.',
            'interval_seconds' => max(5, $intervalSeconds),
            'next_run_at' => Carbon::now()->addSeconds($intervalSeconds)->toIso8601String(),
            'initial_report' => $report,
        ];
    }

    /**
     * Deactivate continuous monitoring loop for an institute.
     */
    public function deactivateContinuousMonitoring(int $subInstituteId): array
    {
        if (Schema::hasTable('lms_catalogue_agent_state')) {
            DB::table('lms_catalogue_agent_state')
                ->where('sub_institute_id', $subInstituteId)
                ->update([
                    'is_active' => false,
                    'daemon_pid' => null,
                    'updated_at' => Carbon::now(),
                ]);
        }

        return [
            'status' => 'deactivated',
            'message' => 'Course Catalogue Reader Agent continuous monitoring loop stopped for institute ' . $subInstituteId,
        ];
    }

    /**
     * Update worker heartbeat and optionally recorded PID.
     */
    public function heartbeat(int $subInstituteId, ?int $pid = null): void
    {
        if (!Schema::hasTable('lms_catalogue_agent_state')) {
            return;
        }

        $update = ['last_heartbeat_at' => Carbon::now(), 'updated_at' => Carbon::now()];
        if ($pid !== null) {
            $update['daemon_pid'] = $pid;
        }

        DB::table('lms_catalogue_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->update($update);
    }

    /**
     * Persistent Scheduler Tick - automatically resumes and runs active agents.
     * Invoked every minute by Laravel's console kernel.
     */
    public function tickActiveAgents(): array
    {
        if (!Schema::hasTable('lms_catalogue_agent_state')) {
            return ['status' => 'skipped', 'message' => 'State table does not exist'];
        }

        $now = Carbon::now();
        $activeInstitutes = DB::table('lms_catalogue_agent_state')
            ->where('is_active', true)
            ->get();

        $ran = 0;
        $skipped = 0;

        foreach ($activeInstitutes as $state) {
            $due = $state->next_run_at === null || Carbon::parse($state->next_run_at)->lessThanOrEqualTo($now);

            if ($due) {
                // Execute validation cycle
                $this->validate((int) $state->sub_institute_id, false, (int) $state->interval_seconds);
                $ran++;
            } else {
                $skipped++;
            }
        }

        return ['ran' => $ran, 'skipped' => $skipped, 'total_active' => count($activeInstitutes)];
    }

    /**
     * Get active agent state details for an institute.
     */
    public function getAgentState(int $subInstituteId): ?array
    {
        if (!Schema::hasTable('lms_catalogue_agent_state')) {
            return null;
        }

        $state = DB::table('lms_catalogue_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$state) {
            return null;
        }

        return [
            'sub_institute_id' => (int) $state->sub_institute_id,
            'agent_key' => $state->agent_key,
            'is_active' => (bool) $state->is_active,
            'auto_generate_content' => isset($state->auto_generate_content) ? (bool) $state->auto_generate_content : true,
            'content_agent_status' => $state->content_agent_status ?? 'active',
            'content_tasks_completed' => (int) ($state->content_tasks_completed ?? 0),
            'content_tasks_generated' => (int) ($state->content_tasks_generated ?? 0),
            'content_tasks_verified' => (int) ($state->content_tasks_verified ?? 0),
            'interval_seconds' => (int) $state->interval_seconds,
            'last_run_at' => $state->last_run_at,
            'last_run_status' => $state->last_run_status,
            'next_run_at' => $state->next_run_at,
            'last_heartbeat_at' => $state->last_heartbeat_at,
            'daemon_pid' => $state->daemon_pid ? (int) $state->daemon_pid : null,
            'latest_hash' => $state->latest_hash,
            'summary_counts' => json_decode($state->summary_counts ?? '{}', true) ?: [],
            'content_stats' => $this->getContentAgent()->getContentStats($subInstituteId),
        ];
    }
}

if (!class_exists('App\\Services\\Lms\\CourseCatalogueHierarchyValidator', false)) {
    class_alias(CourseCatalogueHierarchyValidator::class, 'App\\Services\\Lms\\CourseCatalogueHierarchyValidator');
}
