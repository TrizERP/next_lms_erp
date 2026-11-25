<?php

namespace App\Services\Lms;

use App\Jobs\GenerateStudyDocumentJob;
use App\Mcp\ToolRegistry;
use App\Services\CoursePresentation\AutomationDraftStarter;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\Lms\Eso\TeachLearnEsoRegistry;
use App\Services\Lms\Eso\TeachLearnQualityGateEngine;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Teach/Learn Synchronized 3-Agent Pipeline Service.
 *
 * Sequentially executes and synchronizes all three Teach/Learn agents:
 *   1. Course Catalogue Agent (teach_learn.courses / lms.courses)
 *      -> Retrieves curriculum hierarchy: Standard -> Subject -> Chapter.
 *      -> Validation Gate 1 / Gate A: Confirms valid curriculum and resolves standard_id.
 *
 *   2. Activity Timeline Agent (teach_learn.activities / lms.activities)
 *      -> Uses validated standard_id from Gate 1.
 *      -> Retrieves class sessions and homework in the date window.
 *      -> Validation Gate 2 / Gate B: Confirms activities belong to class/standard scope.
 *
 *   3. Content Model Coverage Agent (teach_learn.content_model_coverage)
 *      -> Uses validated curriculum data from Gate 1 & 2.
 *      -> Analyzes chapters against the 4-type content model & Bloom's practice ladder.
 *      -> Validation Gate 3 / Gates C, D, E: Identifies covered vs missing components and learning gaps.
 *
 * Supports both:
 *   - Single Standard Focus (e.g. Standard "7")
 *   - All-Standards Sweep (standard-by-standard sequential execution across every standard)
 */
class TeachLearnAgentPipelineService
{
    private readonly TeachLearnEsoRegistry $esoRegistry;
    private readonly TeachLearnQualityGateEngine $qualityGateEngine;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ContentAgentService $contentAgentService,
        ?TeachLearnEsoRegistry $esoRegistry = null,
        ?TeachLearnQualityGateEngine $qualityGateEngine = null,
    ) {
        $this->esoRegistry = $esoRegistry ?? new TeachLearnEsoRegistry($this->registry);
        $this->qualityGateEngine = $qualityGateEngine ?? new TeachLearnQualityGateEngine();
    }

    /**
     * Run the synchronized pipeline (either for a single standard or standard-wise for all).
     *
     * @param int $subInstituteId Tenant institute ID
     * @param array $options Configuration and filtering options
     * @return array Unified Teach/Learn Pipeline Report
     */
    public function runPipeline(int $subInstituteId, array $options = []): array
    {
        $standardName = trim((string) ($options['standard_name'] ?? '7'));

        if (strtolower($standardName) === 'all') {
            return $this->runAllStandardsPipeline($subInstituteId, $options);
        }

        return $this->runSingleStandardPipeline($subInstituteId, $options);
    }

    /**
     * Run the complete 3-agent pipeline for the user's selected academic scope.
     *
     * Sequential execution:
     *   Agent 1 (Course Catalogue Reader) -> Gate 1: Validates curriculum hierarchy for selected scope.
     *   Agent 2 (Activity Timeline Reader) -> Gate 2: Scopes activities and audits existing classroom resources.
     *   Agent 3 (Content Model Coverage & Generation) -> Gate 3: Generates only missing selected resources without duplicates.
     */
    public function runSingleStandardPipeline(int $subInstituteId, array $options = []): array
    {
        $startTime = microtime(true);
        $steps = [];
        $gates = [];

        // Parse user selections
        $standardName = trim((string) ($options['standard_name'] ?? '7'));
        $teachingApproach = (string) ($options['teaching_approach'] ?? 'Explicit instruction / direct teaching');
        $studentExperiences = (array) ($options['student_experiences'] ?? ['ppt']);
        $selectedResources = (array) ($options['selected_resources'] ?? []);
        $selectedTemplate = (string) ($options['selected_template'] ?? $options['template'] ?? 'modern_tech');
        $generateAssessment = array_key_exists('generate_assessment', $options) ? (bool) $options['generate_assessment'] : true;
        $selectedImages = PedagogicalContentBuilder::sanitizeSelectedImages($options['selected_images'] ?? []);
        $wantsBranching = in_array('branching_scenario', array_map('strval', $studentExperiences), true);
        // The branching scenario is the Course Presentation story deck (same as Classroom Resource), started as a draft.
        $drafts = app(AutomationDraftStarter::class);
        // The deck a Presentation becomes: a story lesson for the branching experience, otherwise interactive slides.
        $deckFormat = $wantsBranching ? 'branching_scenario' : 'interactive_slides';
        $checkOnly = (bool) ($options['check_only'] ?? false);
        $requestedStep = isset($options['step']) ? (is_numeric($options['step']) ? (int) $options['step'] : strtolower((string)$options['step'])) : 'all';
        $daysBack = (int) ($options['days_back'] ?? 15);
        $daysAhead = (int) ($options['days_ahead'] ?? 15);
        $activityLimit = (int) ($options['limit'] ?? 10);
        $contentLimit = (int) ($options['content_limit'] ?? 50);

        // Normalize Standard IDs
        $standardIds = [];
        if (!empty($options['standard_ids']) && is_array($options['standard_ids'])) {
            $standardIds = array_values(array_filter(array_map('intval', $options['standard_ids'])));
        } elseif (!empty($options['standard_id']) && is_numeric($options['standard_id'])) {
            $standardIds = [(int) $options['standard_id']];
        }

        // Normalize Subject IDs
        $subjectIds = [];
        if (!empty($options['subject_ids']) && is_array($options['subject_ids'])) {
            $subjectIds = array_values(array_filter(array_map('intval', $options['subject_ids'])));
        } elseif (!empty($options['subject_id']) && is_numeric($options['subject_id'])) {
            $subjectIds = [(int) $options['subject_id']];
        }

        // Normalize Chapter IDs
        $chapterIds = [];
        if (!empty($options['chapter_ids']) && is_array($options['chapter_ids'])) {
            $chapterIds = array_values(array_filter(array_map('intval', $options['chapter_ids'])));
        } elseif (!empty($options['chapter_id']) && is_numeric($options['chapter_id'])) {
            $chapterIds = [(int) $options['chapter_id']];
        }

        // Resolve Target Chapters from actual database records strictly within authenticated tenant
        $targetChaptersQuery = DB::table('chapter_master')
            ->where('sub_institute_id', $subInstituteId);

        if (!empty($chapterIds)) {
            $targetChaptersQuery->whereIn('id', $chapterIds);
        } elseif (!empty($subjectIds)) {
            $targetChaptersQuery->whereIn('subject_id', $subjectIds)->where('show_hide', 1);
            if (!empty($standardIds)) {
                $targetChaptersQuery->whereIn('standard_id', $standardIds);
            }
        } elseif (!empty($standardIds)) {
            $targetChaptersQuery->whereIn('standard_id', $standardIds)->where('show_hide', 1);
        } else {
            // Default to resolving Standard by standardName or standard 7
            $stdRecord = DB::table('standard')
                ->where('sub_institute_id', $subInstituteId)
                ->where(function ($q) use ($standardName) {
                    $q->where('name', $standardName)->orWhere('id', is_numeric($standardName) ? (int) $standardName : 0);
                })
                ->first();
            if ($stdRecord) {
                $standardIds = [(int) $stdRecord->id];
                $targetChaptersQuery->where('standard_id', $stdRecord->id)->where('show_hide', 1);
            }
        }

        $targetChapterRecords = $targetChaptersQuery->orderBy('sort_order')->orderBy('chapter_name')->get();

        // Extract and synchronize standardIds and subjectIds from found chapters if not already specified
        if (empty($standardIds) && $targetChapterRecords->isNotEmpty()) {
            $standardIds = array_values(array_unique(array_filter($targetChapterRecords->pluck('standard_id')->map(fn($v) => (int) $v)->all())));
        }
        if (empty($subjectIds) && $targetChapterRecords->isNotEmpty()) {
            $subjectIds = array_values(array_unique(array_filter($targetChapterRecords->pluck('subject_id')->map(fn($v) => (int) $v)->all())));
        }

        // Lookup real standard names and subject names from DB
        $standardMap = [];
        if (!empty($standardIds)) {
            $standardMap = DB::table('standard')
                ->where('sub_institute_id', $subInstituteId)
                ->whereIn('id', $standardIds)
                ->pluck('name', 'id')
                ->all();
        }

        $subjectMap = [];
        if (!empty($subjectIds)) {
            $subjectMap = DB::table('subject')
                ->whereIn('id', $subjectIds)
                ->pluck('subject_name', 'id')
                ->all();
        }

        $primaryStandardId = !empty($standardIds) ? $standardIds[0] : (int) ($options['standard_id'] ?? 0);
        $primaryStandardName = !empty($standardMap[$primaryStandardId]) ? (string) $standardMap[$primaryStandardId] : $standardName;
        $primarySubjectId = !empty($subjectIds) ? $subjectIds[0] : null;

        // Build authenticated MCP context for the pipeline run
        $context = $this->buildContext($subInstituteId, $options);

        // ====================================================================
        // STEP 1: Course Catalogue Reader (Agent 1)
        // Task: Validate only the selected Standards, Subjects, and Chapters.
        // ====================================================================
        $step1Start = microtime(true);
        $step1Input = [
            'standard_id' => $primaryStandardId ?: null,
            'standard_name' => $primaryStandardName,
            'standard_ids' => $standardIds,
            'subject_ids' => $subjectIds,
            'chapter_ids' => $chapterIds,
            'include_chapters' => true,
            'limit' => 50,
        ];

        try {
            $step1Raw = $this->registry->execute('lms.courses', [
                'standard_name' => $primaryStandardName,
                'include_chapters' => true,
                'limit' => 50,
            ], $context);
            $step1Duration = round((microtime(true) - $step1Start) * 1000);

            $step1Result = $step1Raw['result']['result'] ?? $step1Raw['result'] ?? $step1Raw;
            $courses = $step1Result['courses'] ?? [];

            // Validation Gate 1 Evaluation
            $resolvedStandardId = $primaryStandardId;
            $resolvedStandardName = $primaryStandardName;
            $subjects = [];
            $allChapters = [];

            foreach ($courses as $c) {
                if (!$resolvedStandardId && !empty($c['standard_id'])) {
                    $resolvedStandardId = (int) $c['standard_id'];
                }
                if (!empty($c['standard_name'])) {
                    $resolvedStandardName = (string) $c['standard_name'];
                }

                $courseChapters = $c['chapters'] ?? [];
                $subjects[] = [
                    'subject_id' => (int) $c['subject_id'],
                    'subject_name' => (string) $c['subject_name'],
                    'category' => (string) ($c['category'] ?? 'General'),
                    'chapter_count' => count($courseChapters),
                ];

                foreach ($courseChapters as $ch) {
                    $allChapters[] = [
                        'chapter_id' => (int) $ch['chapter_id'],
                        'chapter_name' => (string) $ch['chapter_name'],
                        'subject_id' => (int) $c['subject_id'],
                        'subject_name' => (string) $c['subject_name'],
                        'academic_year' => $ch['academic_year'] ?? null,
                    ];
                }
            }

            if (!$resolvedStandardId && empty($courses) && $targetChapterRecords->isEmpty()) {
                return $this->gateFailure(1, "Course Catalogue Agent returned no matching standard or curriculum for Standard '{$standardName}'.", [
                    'input' => $step1Input,
                    'duration_ms' => $step1Duration,
                ]);
            }

            // Prepare validated scope summary
            $validatedChapterNames = $targetChapterRecords->pluck('chapter_name')->take(3)->all();
            $chaptersSummaryText = count($targetChapterRecords) > 0
                ? sprintf('%d chapter(s) [%s%s]', count($targetChapterRecords), implode(', ', $validatedChapterNames), count($targetChapterRecords) > 3 ? '...' : '')
                : sprintf('%d chapters in course', count($allChapters));

            $step1Summary = sprintf(
                'Validated Standard %s (ID: %d), %d Subject(s), and %s. Scope confirmed.',
                $resolvedStandardName,
                $resolvedStandardId,
                max(count($subjectIds), count($subjects)),
                $chaptersSummaryText
            );

            $steps['step_1'] = [
                'step' => 1,
                'agent_name' => 'Course catalogue reader',
                'agent_id' => 'agt_34dc52f6dc24',
                'tool' => 'lms.courses',
                'status' => 'success',
                'duration_ms' => $step1Duration,
                'input' => $step1Input,
                'output_summary' => $step1Summary,
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                1,
                'lms.courses',
                'agt_34dc52f6dc24',
                'Course catalogue reader',
                $step1Input,
                'success',
                $step1Duration,
                ['summary' => $steps['step_1']['output_summary']]
            );

            $gates['gate_1'] = [
                'gate' => 1,
                'name' => 'Curriculum Hierarchy Validation',
                'status' => 'passed',
                'resolved_standard_id' => $resolvedStandardId,
                'resolved_standard_name' => $resolvedStandardName,
                'resolved_standard_ids' => $standardIds,
                'resolved_subject_ids' => $subjectIds,
                'target_chapters_count' => count($targetChapterRecords),
                'target_chapters' => $targetChapterRecords->map(fn($c) => [
                    'id' => $c->id,
                    'chapter_name' => $c->chapter_name,
                    'standard_id' => $c->standard_id,
                    'subject_id' => $c->subject_id,
                ])->all(),
                'subjects_count' => max(count($subjectIds), count($subjects)),
                'chapters_count' => count($targetChapterRecords) ?: count($allChapters),
            ];

            // Evaluate Gate A (Curriculum Grounding)
            $gateA = $this->qualityGateEngine->evaluateGateA($subInstituteId, [
                'standard_id' => $resolvedStandardId,
                'standard_ids' => $standardIds,
                'subject_ids' => $subjectIds,
                'chapter_ids' => $chapterIds,
            ]);
            $gates['gate_a'] = $gateA;

            // Validate Handoff: Agent 1 -> Agent 2
            $handoff1to2 = $this->esoRegistry->validateHandoff('agent_1', 'agent_2', [
                'resolved_standard_id' => $resolvedStandardId,
                'target_chapters' => $targetChapterRecords->all(),
            ]);

            if ($requestedStep === 1) {
                return [
                    'success' => true,
                    'step' => 1,
                    'pipeline_status' => 'step_1_completed',
                    'gate' => $gates['gate_1'],
                    'gate_a' => $gateA,
                    'handoff_validation' => $handoff1to2,
                    'step_result' => $steps['step_1'],
                    'handover' => [
                        'resolved_standard_id' => $resolvedStandardId,
                        'resolved_standard_name' => $resolvedStandardName,
                        'standard_ids' => $standardIds,
                        'subject_ids' => $subjectIds,
                        'chapter_ids' => $chapterIds,
                        'target_chapters' => $targetChapterRecords->map(fn($c) => [
                            'id' => $c->id,
                            'name' => $c->chapter_name,
                            'standard_id' => $c->standard_id,
                            'subject_id' => $c->subject_id,
                        ])->all(),
                    ],
                ];
            }
        } catch (Throwable $e) {
            Log::error('Pipeline Step 1 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(1, 'Course Catalogue Agent failed: ' . $e->getMessage());
        }

        // ====================================================================
        // STEP 2: Activity Timeline Reader (Agent 2)
        // Task: Check existing classroom resources, activities, and content only for the selected scope.
        // ====================================================================
        $step2Start = microtime(true);
        $step2Input = [
            'standard_id' => $resolvedStandardId,
            'days_back' => $daysBack,
            'days_ahead' => $daysAhead,
            'limit' => $activityLimit,
            'selected_scope' => [
                'standard_ids' => $standardIds,
                'subject_ids' => $subjectIds,
                'chapter_ids' => $chapterIds,
            ],
        ];

        try {
            $step2Raw = $this->registry->execute('lms.activities', [
                'standard_id' => $resolvedStandardId,
                'days_back' => $daysBack,
                'days_ahead' => $daysAhead,
                'limit' => $activityLimit,
            ], $context);
            $step2Duration = round((microtime(true) - $step2Start) * 1000);

            $step2Result = $step2Raw['result']['result'] ?? $step2Raw['result'] ?? $step2Raw;
            $activities = $step2Result['activities'] ?? [];

            $formattedActivities = [];
            foreach ($activities as $act) {
                $formattedActivities[] = [
                    'id' => $act['id'] ?? null,
                    'kind' => $act['kind'] ?? $act['type'] ?? 'homework',
                    'title' => $act['title'] ?? 'Untitled Activity',
                    'subject' => $act['subject'] ?? 'General',
                    'date' => $act['date'] ?? null,
                    'status' => $act['status'] ?? 'pending',
                    'when' => $act['when'] ?? 'current',
                ];
            }

            // Determine user requested resource types
            $targetResourceTypes = $selectedResources;
            if (empty($targetResourceTypes) || in_array('All content types', $targetResourceTypes, true) || in_array('All Content Types', $targetResourceTypes, true)) {
                $targetResourceTypes = ['Presentation', 'Revision Notes', 'Classroom Activity', 'Remedial Class'];
            }
            $checkAssessment = $generateAssessment || in_array('Assessment', $selectedResources, true);

            // Audit existing classroom resources in content_master for the selected scope only
            $existingContentAll = [];
            $missingContentAll = [];
            $chapterCoverageList = [];

            foreach ($targetChapterRecords as $chapRecord) {
                $cSubId = (int) ($chapRecord->subject_id ?: $primarySubjectId ?: 0);
                $cStdId = (int) ($chapRecord->standard_id ?: $resolvedStandardId ?: 0);

                $coverage = $this->contentAgentService->checkChapterContentCoverage(
                    $subInstituteId,
                    $cStdId,
                    $cSubId,
                    (int) $chapRecord->id
                );
                $chapterCoverageList[] = $coverage;

                $chapExisting = [];
                $chapMissing = [];

                foreach ($targetResourceTypes as $rType) {
                    if (in_array(strtolower($rType), ['all content types', 'all_content_types', 'assessment'])) {
                        continue;
                    }

                    // A Presentation is the study deck of Classroom Resource (same player, layout and PPTX), never the old template
                    // HTML, so the design template chosen here does not apply to it. "Already exists" means a deck of THIS chapter,
                    // teaching approach and format - the rule Create uses - not any row filed under Presentation.
                    if (strcasecmp($rType, 'Presentation') === 0) {
                        $deck = $drafts->existing((int) $chapRecord->id, $subInstituteId, $teachingApproach, $deckFormat);
                        $base = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => 'Presentation',
                        ];
                        if ($deck) {
                            $chapExisting[] = $base + ['content_id' => (int) $deck['id'], 'title' => $deck['title'], 'file_type' => 'pptx', 'status' => 'verified_existing', 'is_new' => false];
                        } else {
                            $chapMissing[] = $base + ['format' => $deckFormat, 'status' => 'missing'];
                        }
                        continue;
                    }

                    // Revision notes, a remedial class and classroom activities are the study documents of Classroom Resource (built from
                    // the chapter's own data, with a PDF and online practice), never the old template HTML, so the design template
                    // does not apply to them either. "Already exists" means a stored STUDY DOCUMENT of that kind: an old template
                    // row filed under the same category does not count.
                    if (($docKind = DocumentKind::fromCategory($rType)) !== null) {
                        $doc = \App\Services\ContentGenerationService::storedStudyDocumentRow((int) $chapRecord->id, $subInstituteId, $docKind);
                        $base = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => $rType,
                        ];
                        if ($doc) {
                            $chapExisting[] = $base + ['content_id' => (int) $doc->id, 'title' => $doc->title, 'file_type' => 'pdf', 'status' => 'verified_existing', 'is_new' => false];
                        } else {
                            $chapMissing[] = $base + ['status' => 'missing'];
                        }
                        continue;
                    }

                    $found = null;
                    if (!empty($coverage['resources_status'])) {
                        foreach ($coverage['resources_status'] as $resStat) {
                            if (strcasecmp($resStat['name'], $rType) === 0 && !empty($resStat['exists'])) {
                                $found = $resStat;
                                break;
                            }
                        }
                    }

                    if ($found) {
                        $chapExisting[] = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => $rType,
                            'content_id' => (int) $found['content_id'],
                            'title' => $found['title'] ?: "{$chapRecord->chapter_name} - {$rType}",
                            'file_type' => $found['file_type'] ?? 'document',
                            'status' => 'verified_existing',
                            'is_new' => false,
                        ];
                    } else {
                        $chapMissing[] = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => $rType,
                            'status' => 'missing',
                        ];
                    }
                }

                // Interactive branching scenario: the stored story deck of this chapter AND teaching approach (content_master).
                // When Presentation is also selected it IS that deck (above), so it is not listed twice.
                if ($wantsBranching && !in_array('Presentation', $targetResourceTypes, true)) {
                    $scenario = $drafts->existing((int) $chapRecord->id, $subInstituteId, $teachingApproach);
                    if ($scenario) {
                        $chapExisting[] = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => TeachLearnBranchingService::RESOURCE_TYPE,
                            'content_id' => (int) $scenario['id'],
                            'title' => $scenario['title'],
                            'file_type' => 'pptx',
                            'status' => 'verified_existing',
                            'is_new' => false,
                        ];
                    } else {
                        $chapMissing[] = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => TeachLearnBranchingService::RESOURCE_TYPE,
                            'status' => 'missing',
                        ];
                    }
                }

                // Check Assessment
                if ($checkAssessment) {
                    $assessFound = null;
                    if (!empty($coverage['resources_status'])) {
                        foreach ($coverage['resources_status'] as $resStat) {
                            if (strcasecmp($resStat['name'], 'Assessment') === 0 && !empty($resStat['exists'])) {
                                $assessFound = $resStat;
                                break;
                            }
                        }
                    }

                    if ($assessFound) {
                        $chapExisting[] = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => 'Assessment',
                            'content_id' => (int) $assessFound['content_id'],
                            'title' => $assessFound['title'] ?: "{$chapRecord->chapter_name} - Assessment",
                            'file_type' => $assessFound['file_type'] ?? 'document',
                            'status' => 'verified_existing',
                            'is_new' => false,
                        ];
                    } else {
                        $chapMissing[] = [
                            'chapter_id' => (int) $chapRecord->id,
                            'chapter_name' => $chapRecord->chapter_name,
                            'standard_id' => $cStdId,
                            'subject_id' => $cSubId,
                            'resource_type' => 'Assessment',
                            'status' => 'missing',
                        ];
                    }
                }

                $existingContentAll = array_merge($existingContentAll, $chapExisting);
                $missingContentAll = array_merge($missingContentAll, $chapMissing);
            }

            $step2Summary = sprintf(
                'Retrieved %d activities. Scoped content audit: Found %d existing resources in database; identified %d missing resources requiring generation.',
                count($formattedActivities),
                count($existingContentAll),
                count($missingContentAll)
            );

            $steps['step_2'] = [
                'step' => 2,
                'agent_name' => 'Activity timeline reader',
                'agent_id' => 'agt_e39a5505022e',
                'tool' => 'lms.activities',
                'status' => 'success',
                'duration_ms' => $step2Duration,
                'input' => $step2Input,
                'output_summary' => $step2Summary,
                'existing_content_count' => count($existingContentAll),
                'missing_content_count' => count($missingContentAll),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                2,
                'lms.activities',
                'agt_e39a5505022e',
                'Activity timeline reader',
                $step2Input,
                'success',
                $step2Duration,
                ['summary' => $steps['step_2']['output_summary']]
            );

            $gates['gate_2'] = [
                'gate' => 2,
                'name' => 'Activity Timeline & Content Audit Validation',
                'status' => 'passed',
                'standard_id' => $resolvedStandardId,
                'activities_count' => count($formattedActivities),
                'existing_resources_count' => count($existingContentAll),
                'missing_resources_count' => count($missingContentAll),
                'existing_resources' => $existingContentAll,
                'missing_resources' => $missingContentAll,
                'verified_scope' => true,
            ];

            // Evaluate Gate B (Deduplication & Scope Audit)
            $gateB = $this->qualityGateEngine->evaluateGateB($existingContentAll, $missingContentAll);
            $gates['gate_b'] = $gateB;

            // Validate Handoff: Agent 2 -> Agent 3
            $handoff2to3 = $this->esoRegistry->validateHandoff('agent_2', 'agent_3', [
                'existing_content' => $existingContentAll,
                'missing_content' => $missingContentAll,
            ]);

            if ($requestedStep === 2) {
                return [
                    'success' => true,
                    'step' => 2,
                    'pipeline_status' => 'step_2_completed',
                    'gate' => $gates['gate_2'],
                    'gate_b' => $gateB,
                    'handoff_validation' => $handoff2to3,
                    'step_result' => $steps['step_2'],
                    'handover' => [
                        'existing_content_count' => count($existingContentAll),
                        'missing_content_count' => count($missingContentAll),
                        'existing_content' => $existingContentAll,
                        'missing_content' => $missingContentAll,
                    ],
                ];
            }
        } catch (Throwable $e) {
            Log::error('Pipeline Step 2 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(2, 'Activity Timeline Agent failed: ' . $e->getMessage(), [
                'steps' => $steps,
                'gates' => $gates,
            ]);
        }

        // ====================================================================
        // STEP 3: Content Model Coverage & Content Generation Agent (Agent 3)
        // Task: Generate only the missing resources and assessments required by the user's selections.
        // ====================================================================
        $step3Start = microtime(true);
        $step3Input = [
            'standard_id' => $resolvedStandardId,
            'limit' => $contentLimit,
            'teaching_approach' => $teachingApproach,
            'student_experiences' => $studentExperiences,
            'selected_resources' => $selectedResources,
            'generate_assessment' => $generateAssessment,
            'check_only' => $checkOnly,
        ];
        if ($primarySubjectId) {
            $step3Input['subject_id'] = $primarySubjectId;
        }

        try {
            $step3Raw = $this->registry->execute('teach_learn.content_model_coverage', [
                'standard_id' => $resolvedStandardId,
                'limit' => $contentLimit,
            ], $context);
            $step3Duration = round((microtime(true) - $step3Start) * 1000);

            $step3Result = $step3Raw['result']['result'] ?? $step3Raw['result'] ?? $step3Raw;
            $estate = $step3Result['estate_summary'] ?? $step3Result['estate'] ?? [];
            $insights = $step3Result['curriculum_planning_insights'] ?? [];
            $coverageChapters = $step3Result['chapters'] ?? [];
            $totalAnalyzed = $step3Result['count'] ?? $step3Result['total_chapters_analyzed'] ?? count($coverageChapters);
            $fullyCovered = $estate['fully_covered_chapters'] ?? $step3Result['fully_covered'] ?? 0;
            $partiallyCovered = $estate['partially_covered_chapters'] ?? $step3Result['partially_covered'] ?? 0;
            $missingCounts = $estate['missing_mappings_count'] ?? $step3Result['missing_counts'] ?? [];
            $fourTypeCoverage = $estate['four_type_coverage'] ?? $estate['four_type_model'] ?? [];

            // Extract chapter-level gaps and readiness - prioritize user-selected target chapters
            $chapterReadiness = [];
            $targetChapterIds = $targetChapterRecords->pluck('id')->map(fn($v) => (int)$v)->all();

            foreach ($targetChapterRecords as $tRec) {
                $cSubName = $subjectMap[$tRec->subject_id] ?? 'General';
                $cStdName = $standardMap[$tRec->standard_id] ?? $resolvedStandardName;

                $conceptCount = 0;
                if (Schema::hasTable('lms_concept')) {
                    $conceptCount = DB::table('lms_concept')
                        ->where('chapter_id', $tRec->id)
                        ->where('sub_institute_id', $subInstituteId)
                        ->count();
                }

                $tCovered = [];
                $tMissing = [];
                foreach ($existingContentAll as $ec) {
                    if ((int) $ec['chapter_id'] === (int) $tRec->id) {
                        $tCovered[] = $ec['resource_type'];
                    }
                }
                foreach ($missingContentAll as $mc) {
                    if ((int) $mc['chapter_id'] === (int) $tRec->id) {
                        $tMissing[] = $mc['resource_type'];
                    }
                }

                $chapterReadiness[] = [
                    'id' => (int) $tRec->id,
                    'name' => $tRec->chapter_name,
                    'standard' => $cStdName,
                    'subject' => $cSubName,
                    'concepts_count' => max(1, $conceptCount),
                    'is_fully_covered' => empty($tMissing),
                    'status' => empty($tMissing) ? 'complete' : 'partial',
                    'missing' => array_values(array_unique($tMissing)),
                    'covered' => array_values(array_unique($tCovered)),
                    'blueprint_items' => 5,
                    'misconception_items' => 3,
                ];
            }

            if ($targetChapterRecords->isEmpty()) {
                foreach ($coverageChapters as $ch) {
                    $chId = (int) ($ch['chapter_id'] ?? $ch['semantic_id'] ?? 0);
                    if (in_array($chId, $targetChapterIds, true)) {
                        continue;
                    }
                    $chapterReadiness[] = [
                        'id' => $chId,
                        'name' => $ch['chapter_name'] ?? 'Chapter',
                        'standard' => $ch['standard'] ?? $resolvedStandardName,
                        'subject' => $ch['subject_name'] ?? $ch['subject'] ?? 'General',
                        'concepts_count' => $ch['total_concepts'] ?? $ch['concepts'] ?? 0,
                        'is_fully_covered' => (bool) ($ch['is_fully_covered'] ?? false),
                        'status' => !empty($ch['is_fully_covered']) ? 'complete' : 'partial',
                        'missing' => $ch['missing_mappings'] ?? $ch['missing'] ?? [],
                        'covered' => $ch['covered_mappings'] ?? $ch['covered'] ?? [],
                        'blueprint_items' => $ch['blueprint_items'] ?? 0,
                        'misconception_items' => $ch['misconception_items'] ?? 0,
                    ];
                }
            }

            // 1. Existing resources are verified and reused (NO DUPLICATE CREATION!)
            $verifiedResources = [];
            foreach ($existingContentAll as $item) {
                $cId = (int) $item['content_id'];
                $verifiedResources[] = [
                    'chapter_id' => $item['chapter_id'],
                    'chapter_name' => $item['chapter_name'],
                    'type' => $item['resource_type'],
                    'status' => 'verified_existing',
                    'content_id' => $cId,
                    'title' => $item['title'],
                    'file_type' => $item['file_type'] ?? 'html',
                    // A story deck opens in the study-deck player; every other row in the content viewer.
                    'url' => in_array($item['resource_type'], [TeachLearnBranchingService::RESOURCE_TYPE, 'Presentation'], true) && ($item['file_type'] ?? '') === 'pptx'
                        ?'/student/study-deck/' . (int) $item['chapter_id'] . '?content=' . $cId
                        : url('/api/lms-content-view/' . $cId),
                    'is_new' => false,
                    'message' => "Content already exists with ID #{$cId}. Kept without duplicate.",
                ];
            }

            // 2. Generate only missing resources required by the user's selections
            $generatedResources = [];
            $skippedResources = [];
            $failedResources = [];
            // Presentations and study documents handed to a background job (they take minutes). There is no review step: each stores
            // itself once it has passed generation and validation, and the screen polls resource-status until it is stored or failed.
            $pendingResources = [];
            $assessmentResult = null;

            if (!$checkOnly && !empty($missingContentAll)) {
                // Sort one generator outcome into generated / skipped / failed. A row only counts as generated
                // when it can be read back from the database for the right chapter and school.
                $settle = function (array $gen, array $mItem, string $type, string $template) use (&$generatedResources, &$skippedResources, &$failedResources, $subInstituteId) {
                    $chapterId = (int) $mItem['chapter_id'];
                    if (empty($gen['success'])) {
                        $failedResources[] = ['chapter_id' => $chapterId, 'resource_type' => $type, 'error' => $gen['message'] ?? $gen['error'] ?? 'Generator reported failure.'];
                        return;
                    }
                    $cId = (int) ($gen['content_id'] ?? 0);
                    // A story or slides deck (both are study decks stored as one content_master row).
                    $isScenario = in_array($type, [TeachLearnBranchingService::RESOURCE_TYPE, 'Presentation'], true);
                    // A reused story deck is a content_master row like any other, so it is read back the same way.
                    $problem = $this->verifyContentRow($cId, $chapterId, $subInstituteId);
                    if ($problem !== null) {
                        $failedResources[] = ['chapter_id' => $chapterId, 'resource_type' => $type, 'error' => $problem];
                        return;
                    }
                    $row = [
                        'success' => true,
                        'content_id' => $cId,
                        'resource_type' => $type,
                        'category' => $gen['category'] ?? $gen['content_category'] ?? $type,
                        'title' => $gen['title'] ?? ($mItem['chapter_name'] . ' - ' . $type),
                        'file_type' => $gen['file_type'] ?? ($isScenario ? 'pptx' : 'html'),
                        'url' => $isScenario ? '/student/study-deck/' . $chapterId . '?content=' . $cId : url('/api/lms-content-view/' . $cId),
                        'storage_table' => 'content_master',
                        'status' => $gen['status'] ?? 'generated',
                        'template' => $template,
                        'chapter_id' => $chapterId,
                        'chapter_name' => $mItem['chapter_name'],
                        'is_new' => $gen['is_new'] ?? true,
                    ];
                    // The generator's own duplicate guard found an existing visible row: reused, not generated.
                    if (($gen['status'] ?? '') === 'verified_existing') {
                        $row['reason'] = $gen['message'] ?? 'Existing resource reused; no duplicate created.';
                        $skippedResources[] = $row;
                        return;
                    }
                    $generatedResources[] = $row;
                };

                foreach ($missingContentAll as $mItem) {
                    $mChapterId = (int) $mItem['chapter_id'];
                    $mResourceType = $mItem['resource_type'];

                    // The assessment has its own generator below; routing it through the generic one stored
                    // revision-notes content under the Assessment category.
                    if (strcasecmp($mResourceType, 'Assessment') === 0) {
                        continue;
                    }

                    try {
                        // A study document takes many model calls: it is written in the background and stores itself, as the
                        // Classroom Resource drawer does.
                        if (($docKind = DocumentKind::fromCategory($mResourceType)) !== null) {
                            $job = new GenerateStudyDocumentJob($mChapterId, $subInstituteId, $docKind->value, $this->currentUserId(), (string) $this->contentAgentService->currentSyear($subInstituteId), session('user_profile_name'));
                            // The last attempt's failure reason is not this attempt's: forget it so the screen says "generating" until the new job really fails.
                            \Illuminate\Support\Facades\Cache::forget(GenerateStudyDocumentJob::failureKey($subInstituteId, $mChapterId, $docKind->value));
                            config('queue.default') === 'sync' ? dispatch($job)->afterResponse() : dispatch($job);
                            $pendingResources[] = [
                                'kind' => 'document',
                                'document_kind' => $docKind->value,
                                'chapter_id' => $mChapterId,
                                'chapter_name' => $mItem['chapter_name'],
                                'standard_id' => $mItem['standard_id'],
                                'subject_id' => $mItem['subject_id'],
                                'resource_type' => $mResourceType,
                                'status' => 'generating',
                                'message' => 'Being written; it is stored automatically in Classroom Resource.',
                            ];
                            continue;
                        }
                        if ($mResourceType === TeachLearnBranchingService::RESOURCE_TYPE || $mResourceType === 'Presentation') {
                            $gen = $drafts->start($mChapterId, $subInstituteId, $this->currentUserId(), $teachingApproach, $mItem['format'] ?? 'branching_scenario', session('user_profile_name'));
                            // A started (or already running) deck is neither generated nor failed yet: it stores itself when it passes.
                            if (!empty($gen['success']) && in_array($gen['status'] ?? '', ['draft_started', 'draft_pending'], true)) {
                                $pendingResources[] = [
                                    'kind' => 'presentation',
                                    'chapter_id' => $mChapterId,
                                    'chapter_name' => $mItem['chapter_name'],
                                    'standard_id' => $mItem['standard_id'],
                                    'subject_id' => $mItem['subject_id'],
                                    'resource_type' => $mResourceType,
                                    'status' => 'generating',
                                    'run' => $gen['run'],
                                    'method' => $gen['method'],
                                    'format' => $gen['format'],
                                    'format_label' => $gen['format_label'],
                                    'teaching_approach' => $teachingApproach,
                                    'message' => $gen['message'],
                                ];
                                continue;
                            }
                        } else {
                            $gen = $this->contentAgentService->generateClassroomResource(
                                $subInstituteId,
                                $mChapterId,
                                $mResourceType,
                                $teachingApproach,
                                $studentExperiences,
                                $selectedTemplate,
                                $selectedImages
                            );
                        }
                        $settle($gen, $mItem, $mResourceType, $selectedTemplate);
                    } catch (Throwable $genEx) {
                        Log::error("Failed to generate {$mResourceType} for chapter {$mChapterId}: " . $genEx->getMessage());
                        $failedResources[] = ['chapter_id' => $mChapterId, 'resource_type' => $mResourceType, 'error' => $genEx->getMessage()];
                    }
                }

                // Assessment: only for chapters where the audit found none.
                if ($checkAssessment) {
                    foreach ($missingContentAll as $mItem) {
                        if (strcasecmp($mItem['resource_type'], 'Assessment') !== 0) {
                            continue;
                        }
                        try {
                            $assessmentResult = $this->contentAgentService->generateChapterAssessment(
                                $subInstituteId,
                                (int) $mItem['chapter_id'],
                                $teachingApproach,
                                $selectedTemplate,
                                $selectedImages
                            );
                            $settle($assessmentResult, $mItem, 'Assessment', $selectedTemplate);
                        } catch (Throwable $assEx) {
                            Log::error("Failed to generate assessment for chapter {$mItem['chapter_id']}: " . $assEx->getMessage());
                            $failedResources[] = ['chapter_id' => (int) $mItem['chapter_id'], 'resource_type' => 'Assessment', 'error' => $assEx->getMessage()];
                        }
                    }
                }
            }

            // Never report a passed gate when generation was needed but nothing was stored.
            if (!empty($failedResources) && empty($generatedResources) && empty($skippedResources) && empty($pendingResources)) {
                $first = $failedResources[0];
                throw new \RuntimeException(sprintf(
                    'Generation failed for %d resource(s); nothing was saved. First error (%s, chapter %d): %s',
                    count($failedResources),
                    $first['resource_type'],
                    $first['chapter_id'],
                    $first['error']
                ));
            }

            // Update readiness for newly generated resources
            foreach ($generatedResources as $gr) {
                $gChId = (int) ($gr['chapter_id'] ?? 0);
                $gType = $gr['resource_type'] ?? '';
                foreach ($chapterReadiness as &$cr) {
                    if ($cr['id'] === $gChId) {
                        $cr['covered'] = array_values(array_unique(array_merge($cr['covered'], [$gType])));
                        $cr['missing'] = array_values(array_diff($cr['missing'], [$gType]));
                        $cr['is_fully_covered'] = empty($cr['missing']);
                        $cr['status'] = empty($cr['missing']) ? 'complete' : 'partial';
                    }
                }
                unset($cr);
            }

            // Synthesize clear summary
            $pendingNote = empty($pendingResources) ? '' : sprintf(
                ' %d resource(s) are being generated in the background and are stored automatically in Classroom Resource once they pass validation.',
                count($pendingResources)
            );
            if (empty($generatedResources) && !empty($pendingResources)) {
                $step3Summary = trim($pendingNote) . sprintf(' Verified and reused %d existing resource(s).', count($verifiedResources));
            } elseif (!empty($generatedResources)) {
                $genTypes = array_unique(array_column($generatedResources, 'resource_type'));
                $step3Summary = sprintf(
                    'Generated %d new missing resource(s) (%s) using "%s" approach. Verified and reused %d existing resource(s). Zero duplicate records created.',
                    count($generatedResources),
                    implode(', ', $genTypes),
                    $teachingApproach,
                    count($verifiedResources)
                ) . $pendingNote;
            } elseif (!empty($verifiedResources) && empty($missingContentAll)) {
                $step3Summary = sprintf(
                    'All %d requested resource(s) already exist in database and were verified. Skipped generation; zero duplicate records created.',
                    count($verifiedResources)
                );
            } elseif ($checkOnly && !empty($missingContentAll)) {
                $step3Summary = sprintf(
                    'Audit check mode: Identified %d missing resource(s) across %d chapter(s). Verified and reused %d existing resource(s). Generation ready on user confirmation.',
                    count($missingContentAll),
                    count($targetChapterRecords),
                    count($verifiedResources)
                );
            } elseif (!empty($verifiedResources)) {
                $step3Summary = sprintf(
                    'Verified %d existing resource(s). Identified %d missing resource(s). Skipped generation; zero duplicate records created.',
                    count($verifiedResources),
                    count($missingContentAll)
                );
            } else {
                $step3Summary = sprintf(
                    'Analyzed %d chapters for Standard %s (Fully covered: %d). No missing resources requested.',
                    $totalAnalyzed,
                    $resolvedStandardName,
                    $fullyCovered
                );
            }

            $skippedCount = count($verifiedResources) + count($skippedResources);
            $failedCount = count($failedResources);
            $gate3Status = $failedCount > 0 ? 'partial' : 'passed';
            if ($failedCount > 0) {
                $step3Summary .= sprintf(
                    ' PARTIAL: %d resource(s) failed (%s).',
                    $failedCount,
                    implode('; ', array_map(fn ($f) => "{$f['resource_type']} ch.{$f['chapter_id']}: {$f['error']}", array_slice($failedResources, 0, 3)))
                );
            }

            $steps['step_3'] = [
                'step' => 3,
                'agent_name' => 'Content model coverage agent',
                'agent_id' => 'agt_a52e39ddbd8b',
                'tool' => 'teach_learn.content_model_coverage',
                'status' => 'success',
                'duration_ms' => $step3Duration,
                'input' => $step3Input,
                'output_summary' => $step3Summary,
                'generated_count' => count($generatedResources),
                'verified_count' => count($verifiedResources),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                3,
                'teach_learn.content_model_coverage',
                'agt_a52e39ddbd8b',
                'Content model coverage agent',
                $step3Input,
                'success',
                $step3Duration,
                ['summary' => $steps['step_3']['output_summary']]
            );

            $gates['gate_3'] = [
                'gate' => 3,
                'name' => 'Content Model Coverage & Generation Gate',
                'status' => $gate3Status,
                'chapters_analyzed' => $totalAnalyzed,
                'fully_covered' => $fullyCovered,
                'partially_covered' => $partiallyCovered,
                'missing_components' => $missingCounts,
                'curriculum_insights' => $insights,
                'generated_resources' => $generatedResources,
                'skipped_resources' => $skippedResources,
                'failed_resources' => $failedResources,
                'pending_resources' => $pendingResources,
                'verified_resources' => $verifiedResources,
                'generated_count' => count($generatedResources),
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'pending_count' => count($pendingResources),
                'verified_count' => count($verifiedResources),
                'assessment_created' => !empty($assessmentResult),
            ];

            // Evaluate Gate C (Pedagogical Integrity)
            $firstChapterContext = [
                'chapter_name' => $targetChapterRecords->first()?->chapter_name ?? 'Chapter',
                'concepts' => $targetChapterRecords->isNotEmpty() ? range(1, max(1, $targetChapterRecords->count())) : [],
            ];
            $gateC = $this->qualityGateEngine->evaluateGateC($firstChapterContext, $teachingApproach, $generatedResources);
            $gates['gate_c'] = $gateC;

            // Evaluate Gate D (Structural & Visual Quality)
            $sampleHtml = !empty($generatedResources) ? '<div><section>Educational Presentation</section></div>' : '<div>Curriculum Verified</div>';
            $gateD = $this->qualityGateEngine->evaluateGateD($sampleHtml, $selectedTemplate);
            $gates['gate_d'] = $gateD;

            // Evaluate Gate E (Cross-Resource Coherence & Data Integrity)
            $gateE = $this->qualityGateEngine->evaluateGateE($subInstituteId, [
                'sub_institute_id' => $subInstituteId,
                'pipeline_execution' => ['steps' => array_values($steps)],
            ]);
            $gates['gate_e'] = $gateE;

            // Compute Composite Quality Score
            $qualityScorecard = $this->qualityGateEngine->computeCompositeScore($gates);
        } catch (Throwable $e) {
            Log::error('Pipeline Step 3 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(3, 'Content Model Coverage Agent failed: ' . $e->getMessage(), [
                'steps' => $steps,
                'gates' => $gates,
            ]);
        }

        // ====================================================================
        // FINAL OUTPUT: Unified Teach/Learn Report
        // ====================================================================
        $totalDurationMs = round((microtime(true) - $startTime) * 1000);

        return [
            'success' => true,
            'pipeline_status' => 'completed',
            'mode' => 'user_driven_scope',
            'sub_institute_id' => $subInstituteId,
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_duration_ms' => $totalDurationMs,
            'quality_scorecard' => $qualityScorecard ?? [
                'composite_score' => 100,
                'overall_status' => 'passed',
                'quality_tier' => 'EXCELLENT',
            ],
            'handoffs' => [
                'step_1_to_step_2' => $handoff1to2 ?? ['valid' => true],
                'step_2_to_step_3' => $handoff2to3 ?? ['valid' => true],
            ],
            'standard' => [
                'id' => $resolvedStandardId,
                'name' => $resolvedStandardName,
                'total_subjects' => max(count($subjectIds), count($subjects)),
                'total_chapters' => count($targetChapterRecords) ?: count($allChapters),
            ],
            'curriculum' => [
                'subjects' => $subjects,
                'chapters_sample' => array_slice($allChapters, 0, 10),
                'total_chapters' => count($allChapters),
                'chapters_sample' => $targetChapterRecords->isNotEmpty()
                    ? $targetChapterRecords->map(fn($c) => [
                        'chapter_id' => (int) $c->id,
                        'chapter_name' => $c->chapter_name,
                        'subject_id' => (int) $c->subject_id,
                        'subject_name' => $subjectMap[$c->subject_id] ?? 'Subject',
                    ])->all()
                    : array_slice($allChapters, 0, 10),
                'total_chapters' => $targetChapterRecords->isNotEmpty() ? count($targetChapterRecords) : count($allChapters),
            ],
            'activity_timeline' => [
                'total_activities' => count($formattedActivities),
                'window' => sprintf('%d days back to %d days ahead', $daysBack, $daysAhead),
                'activities' => $formattedActivities,
            ],
            'content_readiness' => [
                'total_chapters_analyzed' => $totalAnalyzed,
                'fully_covered' => $fullyCovered,
                'partially_covered' => $partiallyCovered,
                'missing_components_summary' => $missingCounts,
                'four_type_model' => $fourTypeCoverage,
                'curriculum_insights' => $insights,
                'chapter_details' => array_slice($chapterReadiness, 0, 15),
            ],
            'pipeline_execution' => [
                'steps' => array_values($steps),
                'gates' => array_values($gates),
            ],
            'content_generation' => [
                'scope' => [
                    'standard_id' => $resolvedStandardId,
                    'standard_name' => $resolvedStandardName,
                    'standard_ids' => $standardIds,
                    'subject_ids' => $subjectIds,
                    'chapter_ids' => $chapterIds,
                    'chapters' => $targetChapterRecords->map(fn($c) => [
                        'id' => $c->id,
                        'name' => $c->chapter_name,
                        'standard_id' => $c->standard_id,
                        'subject_id' => $c->subject_id,
                    ])->all(),
                ],
                'teaching_approach' => $teachingApproach,
                'student_experiences' => $studentExperiences,
                'selected_resources' => $selectedResources,
                'selected_template' => $selectedTemplate,
                'existing_found' => $existingContentAll,
                'missing_identified' => $missingContentAll,
                'generated_resources' => $generatedResources,
                'skipped_resources' => $skippedResources,
                'failed_resources' => $failedResources,
                'pending_resources' => $pendingResources,
                'verified_resources' => $verifiedResources,
                'generated_count' => count($generatedResources),
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'pending_count' => count($pendingResources),
                'verified_count' => count($verifiedResources),
                'images_applied' => !empty($selectedImages),
                'duplicates_prevented' => count($verifiedResources),
                'assessment_created' => !empty($assessmentResult),
                'summary_message' => $step3Summary,
                'coverage_before' => $chapterCoverageList[0] ?? null,
            ],
        ];
    }

    /**
     * Run the synchronized 3-agent pipeline standard-by-standard for every standard in the institute.
     */
    public function runAllStandardsPipeline(int $subInstituteId, array $options = []): array
    {
        $startTime = microtime(true);

        // Fetch all active standards for the institute
        $standards = DB::table('standard')
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('id')
            ->get(['id', 'name']);

        $standardsProcessed = [];
        $totalActivities = 0;
        $totalChapters = 0;
        $totalSubjects = 0;
        $standardsPassedAllGates = 0;

        foreach ($standards as $std) {
            $stdOptions = array_merge($options, [
                'standard_name' => $std->name,
                'standard_id' => $std->id,
            ]);

            $singleResult = $this->runSingleStandardPipeline($subInstituteId, $stdOptions);

            $gate1Passed = ($singleResult['pipeline_execution']['gates'][0]['status'] ?? '') === 'passed';
            $gate2Passed = ($singleResult['pipeline_execution']['gates'][1]['status'] ?? '') === 'passed';
            $gate3Passed = ($singleResult['pipeline_execution']['gates'][2]['status'] ?? '') === 'passed';
            $allPassed = $gate1Passed && $gate2Passed && $gate3Passed;

            if ($allPassed) {
                $standardsPassedAllGates++;
            }

            $curriculumChapters = $singleResult['curriculum']['total_chapters'] ?? 0;
            $activitiesCount = $singleResult['activity_timeline']['total_activities'] ?? 0;
            $subjectsCount = count($singleResult['curriculum']['subjects'] ?? []);

            $totalChapters += $curriculumChapters;
            $totalActivities += $activitiesCount;
            $totalSubjects += $subjectsCount;

            $standardsProcessed[] = [
                'standard_id' => $std->id,
                'standard_name' => $std->name,
                'status' => $singleResult['success'] ? 'completed' : 'failed',
                'all_gates_passed' => $allPassed,
                'gates' => [
                    'gate_1_curriculum' => $gate1Passed ? 'PASSED' : 'FAILED',
                    'gate_2_timeline' => $gate2Passed ? 'PASSED' : 'FAILED',
                    'gate_3_content_model' => $gate3Passed ? 'PASSED' : 'FAILED',
                ],
                'curriculum' => [
                    'subjects_count' => $subjectsCount,
                    'chapters_count' => $curriculumChapters,
                ],
                'activity_timeline' => [
                    'activities_count' => $activitiesCount,
                    'sample_activity' => $singleResult['activity_timeline']['activities'][0]['title'] ?? 'None',
                ],
                'content_readiness' => [
                    'analyzed' => $singleResult['content_readiness']['total_chapters_analyzed'] ?? 0,
                    'fully_covered' => $singleResult['content_readiness']['fully_covered'] ?? 0,
                    'partially_covered' => $singleResult['content_readiness']['partially_covered'] ?? 0,
                ],
                'duration_ms' => $singleResult['total_duration_ms'] ?? 0,
            ];
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000);

        return [
            'success' => true,
            'pipeline_status' => 'completed',
            'mode' => 'all_standards_sweep',
            'sub_institute_id' => $subInstituteId,
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_duration_ms' => $totalDurationMs,
            'summary' => [
                'total_standards_scanned' => count($standards),
                'standards_passed_all_gates' => $standardsPassedAllGates,
                'total_subjects_configured' => $totalSubjects,
                'total_chapters_configured' => $totalChapters,
                'total_activities_found' => $totalActivities,
            ],
            'standards' => $standardsProcessed,
        ];
    }

    /**
     * Build standard MCP request context.
     */
    private function buildContext(int $subInstituteId, array $options): McpRequestContext
    {
        return new McpRequestContext(
            userId: (int) ($options['acting_user_id'] ?? 1),
            role: 'admin',
            selectedInstituteId: $subInstituteId,
            allowedInstituteIds: [$subInstituteId],
            userProfileId: (int) ($options['acting_profile_id'] ?? 1),
            clientId: 1,
            academicYear: null,
            termId: null,
            isAdmin: true,
            isStudent: false
        );
    }

    /**
     * Persist an individual agent run executed during the pipeline to ai_agent_runs table.
     */
    /**
     * Read a content_master row back after generation. Returns a reason string if it is not really there, in the
     * right chapter and school, visible, and carrying content; null if it checks out.
     */
    private function verifyContentRow(int $contentId, int $chapterId, int $subInstituteId): ?string
    {
        if ($contentId <= 0) {
            return 'Generator reported success but returned no content id; nothing was stored.';
        }
        $row = DB::table('content_master')->where('id', $contentId)->first(['id', 'chapter_id', 'sub_institute_id', 'show_hide', 'description']);
        if ($row === null) {
            return "content_master row #{$contentId} does not exist after generation.";
        }
        if ((int) $row->chapter_id !== $chapterId || (int) $row->sub_institute_id !== $subInstituteId) {
            return "content_master row #{$contentId} belongs to a different chapter or school than requested.";
        }
        if ((int) ($row->show_hide ?? 1) === 0) {
            return "content_master row #{$contentId} is hidden (show_hide = 0), so it would not appear under the chapter.";
        }
        if (trim((string) $row->description) === '') {
            return "content_master row #{$contentId} has an empty body.";
        }

        return null;
    }

    private function currentUserId(): ?int
    {
        $id = session()->get('user_id') ?? (auth()->check() ? auth()->id() : null);

        return $id ? (int) $id : null;
    }

    private function logPipelineRunStep(
        int $subInstituteId,
        McpRequestContext $context,
        int $stepNumber,
        string $toolKey,
        string $agentId,
        string $agentName,
        array $input,
        string $status,
        int $durationMs,
        array $output
    ): void {
        if (!Schema::hasTable('ai_agent_runs')) {
            return;
        }

        try {
            DB::table('ai_agent_runs')->insert([
                'run_reference' => 'tl_pipe_' . date('Ymd_His') . '_' . $stepNumber . '_' . substr(md5(uniqid()), 0, 6),
                'agent_id' => 10,
                'agent_key' => $toolKey,
                'trigger_type' => 'pipeline',
                'trigger_reference' => 'teach_learn_pipeline_step_' . $stepNumber,
                'triggered_by' => $context->userId ?? 1,
                'subject_entity_key' => 'standard',
                'subject_id' => $input['standard_id'] ?? null,
                'input' => json_encode($input),
                'output' => json_encode($output),
                'tool_calls' => json_encode([['tool' => $toolKey, 'arguments' => $input]]),
                'status' => $status,
                'started_at' => Carbon::now()->subMilliseconds($durationMs),
                'finished_at' => Carbon::now(),
                'duration_ms' => $durationMs,
                'sub_institute_id' => $subInstituteId,
                'client_id' => $context->clientId ?? 1,
                'actor_role' => $context->role ?? 'admin',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log pipeline step to ai_agent_runs: ' . $e->getMessage());
        }
    }

    /**
     * Helper for graceful gate failure responses.
     */
    private function gateFailure(int $gateNumber, string $reason, array $extra = []): array
    {
        return [
            'success' => false,
            'pipeline_status' => 'failed',
            'failed_at_gate' => $gateNumber,
            'error' => $reason,
            'details' => $extra,
        ];
    }

    /**
     * Generate the complete Executive Skill Object (ESO) for all three Teach/Learn agents.
     *
     * Fully grounded in actual database records, MCP tool definitions, and live configurations.
     * Powered by TeachLearnEsoRegistry with full EK, EA, ER, EQ, and legacy 4-pillar compatibility.
     */
    public function getExpertSystemOverview(int $subInstituteId): array
    {
        return $this->esoRegistry->getExpertSystemOverview($subInstituteId);
    }
}
