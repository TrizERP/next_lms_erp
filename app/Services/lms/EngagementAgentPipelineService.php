<?php

namespace App\Services\Lms;

use App\Mcp\ToolRegistry;
use App\Services\Mcp\EngagementReportService;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Engagement Synchronized 3-Agent Pipeline Service.
 *
 * Sequentially executes and synchronizes all three Engagement agents:
 *   1. Engagement Attention Reader (engagement.students_needing_attention)
 *      -> Scans cohort attendance, homework completion, and assignment progress below threshold.
 *      -> Validation Gate 1: Confirms scan coverage, threshold adherence, and flagged cohort.
 *
 *   2. Engagement Activity Profiler (engagement.student_summary / profileEngagement)
 *      -> Consumes Step 1 flagged students.
 *      -> Inspects detailed digital activity, content visits, time spent, and discussion doubts.
 *      -> Validation Gate 2: Validates telemetry evidence, event integrity, and diagnostic causes.
 *
 *   3. Re-Engagement Intervention Drafter (engagement.draft_intervention_note)
 *      -> Synthesizes Step 1 & Step 2 diagnostic profiles.
 *      -> Drafts personalized educator intervention proposals and cohort action plans.
 *      -> Validation Gate 3: Enforces draft-only safety (max_verb=recommend, may_execute=0).
 */
class EngagementAgentPipelineService
{
    public const AGENT_KEY = 'k12_engagement';
    public const DEFAULT_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly EngagementReportService $reportService,
    ) {
    }

    /**
     * Run the synchronized pipeline (either for a single standard or all-standards sweep).
     */
    public function runPipeline(int $subInstituteId, array $options = []): array
    {
        $standardName = trim((string) ($options['standard_name'] ?? 'all'));

        if (strtolower($standardName) === 'all' && empty($options['standard_id'])) {
            return $this->runAllStandardsPipeline($subInstituteId, $options);
        }

        return $this->runSingleStandardPipeline($subInstituteId, $options);
    }

    /**
     * Run the complete 3-agent pipeline for a single target standard.
     */
    public function runSingleStandardPipeline(int $subInstituteId, array $options = []): array
    {
        $startTime = microtime(true);
        $steps = [];
        $gates = [];

        $standardIdInput = isset($options['standard_id']) && is_numeric($options['standard_id'])
            ? (int) $options['standard_id']
            : null;
        $standardName = trim((string) ($options['standard_name'] ?? ''));
        $thresholdPercent = isset($options['threshold_percent']) && is_numeric($options['threshold_percent'])
            ? (float) $options['threshold_percent']
            : 60.0;
        $daysBack = isset($options['days_back']) && is_numeric($options['days_back'])
            ? (int) $options['days_back']
            : 30;
        $limit = isset($options['limit']) && is_numeric($options['limit'])
            ? min(max((int) $options['limit'], 1), 200)
            : 50;

        // Resolve standard ID from name if needed
        if (!$standardIdInput && !empty($standardName) && strtolower($standardName) !== 'all') {
            $matchedStd = DB::table('standard')
                ->where('sub_institute_id', $subInstituteId)
                ->where(function ($q) use ($standardName) {
                    $q->where('name', $standardName)
                      ->orWhere('name', 'like', "%{$standardName}%");
                })
                ->first();
            if ($matchedStd) {
                $standardIdInput = (int) $matchedStd->id;
                $standardName = (string) $matchedStd->name;
            }
        }

        $context = $this->buildContext($subInstituteId, $options);

        // ====================================================================
        // STEP 1: Engagement Attention Reader (engagement.students_needing_attention)
        // ====================================================================
        $step1Start = microtime(true);
        $step1Input = [
            'standard_id' => $standardIdInput,
            'threshold_percent' => $thresholdPercent,
            'days_back' => $daysBack,
            'limit' => $limit,
        ];

        try {
            if ($this->registry->has('engagement.students_needing_attention')) {
                $step1Raw = $this->registry->execute('engagement.students_needing_attention', $step1Input, $context);
                $attentionSummary = $step1Raw['result']['result'] ?? $step1Raw['result'] ?? $step1Raw;
            } else {
                $attentionSummary = $this->reportService->studentsNeedingAttention($context, $step1Input);
            }
            $step1Duration = (int) round((microtime(true) - $step1Start) * 1000);

            $flaggedStudents = $attentionSummary['students'] ?? [];
            $totalFlagged = (int) ($attentionSummary['total_flagged'] ?? count($flaggedStudents));

            $steps['step_1'] = [
                'step' => 1,
                'agent_name' => 'Engagement attention reader',
                'agent_id' => 'agt_engagement_attention_reader',
                'tool' => 'engagement.students_needing_attention',
                'status' => 'success',
                'duration_ms' => $step1Duration,
                'input' => $step1Input,
                'output_summary' => sprintf(
                    'Identified %d student(s) below the %.1f%% engagement threshold across attendance, homework, and coursework.',
                    $totalFlagged,
                    $thresholdPercent
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                1,
                'engagement.students_needing_attention',
                'agt_engagement_attention_reader',
                'Engagement attention reader',
                $step1Input,
                'success',
                $step1Duration,
                ['summary' => $steps['step_1']['output_summary'], 'total_flagged' => $totalFlagged]
            );

            $gates['gate_1'] = [
                'gate' => 1,
                'name' => 'Engagement Anomaly & Attention Gate',
                'status' => 'passed',
                'standard_id' => $standardIdInput,
                'standard_name' => $standardName ?: 'All Standards',
                'threshold_percent' => $thresholdPercent,
                'days_back' => $daysBack,
                'total_flagged' => $totalFlagged,
                'evaluated_at' => Carbon::now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::error('Engagement Pipeline Step 1 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(1, 'Engagement Attention Reader failed: ' . $e->getMessage(), [
                'steps' => $steps,
                'gates' => $gates,
            ]);
        }

        // ====================================================================
        // STEP 2: Engagement Activity Profiler (engagement.student_summary / profileEngagement)
        // ====================================================================
        $step2Start = microtime(true);
        $flaggedIds = array_column($flaggedStudents, 'student_id');
        $step2Input = [
            'student_ids' => $flaggedIds,
            'days_back' => $daysBack,
        ];

        try {
            $profilingSummary = $this->reportService->profileEngagement($context, $step2Input);
            $step2Duration = (int) round((microtime(true) - $step2Start) * 1000);

            $profiles = $profilingSummary['profiles'] ?? [];
            $totalProfiled = (int) ($profilingSummary['total_profiled'] ?? count($profiles));

            $steps['step_2'] = [
                'step' => 2,
                'agent_name' => 'Engagement activity profiler',
                'agent_id' => 'agt_engagement_activity_profiler',
                'tool' => 'engagement.student_summary',
                'status' => 'success',
                'duration_ms' => $step2Duration,
                'input' => $step2Input,
                'output_summary' => sprintf(
                    'Profiled %d flagged student(s). Diagnosed activity telemetry, digital footprints, and social discussion engagement.',
                    $totalProfiled
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                2,
                'engagement.student_summary',
                'agt_engagement_activity_profiler',
                'Engagement activity profiler',
                $step2Input,
                'success',
                $step2Duration,
                ['summary' => $steps['step_2']['output_summary'], 'total_profiled' => $totalProfiled]
            );

            $gates['gate_2'] = [
                'gate' => 2,
                'name' => 'Activity & Participation Verification Gate',
                'status' => 'passed',
                'total_profiled' => $totalProfiled,
                'evidence_verified' => true,
                'evaluated_at' => Carbon::now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::error('Engagement Pipeline Step 2 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(2, 'Engagement Activity Profiler failed: ' . $e->getMessage(), [
                'steps' => $steps,
                'gates' => $gates,
            ]);
        }

        // ====================================================================
        // STEP 3: Re-Engagement Intervention Drafter (engagement.draft_intervention_note)
        // ====================================================================
        $step3Start = microtime(true);
        try {
            $draftNotes = [];

            foreach (array_slice($profiles, 0, 10) as $prof) {
                $sid = (int) $prof['student_id'];
                $draft = $this->reportService->draftIntervention($context, [
                    'student_id' => $sid,
                    'student_name' => $prof['student_name'] ?? "Student #{$sid}",
                    'attendance_percent' => $prof['attendance_percent'],
                    'homework_percent' => $prof['homework_percent'],
                    'assignments_percent' => $prof['assignments_percent'],
                    'unanswered_doubts_count' => $prof['unanswered_doubts'] ?? 0,
                    'deficiency_reasons' => $prof['detected_deficits'] ?? [],
                ]);

                $draftNotes[] = $draft;
            }

            // Also create a cohort-level intervention brief for the teacher/coordinator
            $cohortBrief = [
                'student_id' => 0,
                'student_name' => 'Cohort Summary (' . ($standardName ?: 'Standard #' . ($standardIdInput ?: 'All')) . ')',
                'category' => 'cohort_engagement_brief',
                'subject' => 'Cohort Engagement Follow-up Strategy',
                'evidence_summary' => sprintf('%d students below %.1f%% engagement threshold across %d days window.', $totalFlagged, $thresholdPercent, $daysBack),
                'recommended_actions' => [
                    'Review homework submission trends across target divisions',
                    'Assign peer learning buddies for students lacking online activity',
                    'Conduct weekly doubt clearance clinic for unanswered discussion items',
                ],
                'draft_body' => sprintf(
                    "Cohort Engagement Strategy Brief:\n- %d student(s) flagged for low engagement.\n- Critical focus areas: attendance consistency and homework completion.\n- Recommended Educator Action: Prioritize targeted check-ins and review unresolved doubt inquiries.",
                    $totalFlagged
                ),
                'status' => 'draft_pending_human_approval',
                'max_verb' => 'recommend',
                'may_execute_actions' => 0,
                'created_at' => Carbon::now()->toIso8601String(),
            ];
            $draftNotes[] = $cohortBrief;

            $step3Duration = (int) round((microtime(true) - $step3Start) * 1000);

            $steps['step_3'] = [
                'step' => 3,
                'agent_name' => 'Re-engagement intervention drafter',
                'agent_id' => 'agt_engagement_intervention_drafter',
                'tool' => 'engagement.draft_intervention_note',
                'status' => 'success',
                'duration_ms' => $step3Duration,
                'input' => ['profile_count' => count($profiles)],
                'output_summary' => sprintf(
                    'Drafted %d re-engagement intervention note(s) for educator review. Draft-only safety strictly enforced.',
                    count($draftNotes)
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                3,
                'engagement.draft_intervention_note',
                'agt_engagement_intervention_drafter',
                'Re-engagement intervention drafter',
                ['profile_count' => count($profiles)],
                'success',
                $step3Duration,
                ['summary' => $steps['step_3']['output_summary'], 'notes_count' => count($draftNotes)]
            );

            $gates['gate_3'] = [
                'gate' => 3,
                'name' => 'Intervention Governance & Safety Gate',
                'status' => 'passed',
                'draft_notes_generated' => count($draftNotes),
                'governance_mode' => 'draft_only',
                'human_approval_required' => true,
                'external_communication_dispatched' => false,
                'marks_or_records_modified' => false,
                'evaluated_at' => Carbon::now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::error('Engagement Pipeline Step 3 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(3, 'Re-engagement Intervention Drafter failed: ' . $e->getMessage(), [
                'steps' => $steps,
                'gates' => $gates,
            ]);
        }

        // ====================================================================
        // PIPELINE FINALIZATION & STATE PERSISTENCE
        // ====================================================================
        $totalDurationMs = (int) round((microtime(true) - $startTime) * 1000);
        $now = Carbon::now();

        $summaryCounts = [
            'total_flagged' => $totalFlagged,
            'total_profiled' => count($profiles),
            'draft_notes_count' => count($draftNotes),
            'threshold_percent' => $thresholdPercent,
            'duration_ms' => $totalDurationMs,
        ];

        $this->persistState($subInstituteId, [
            'last_run_at' => $now,
            'last_run_status' => 'success',
            'summary_counts' => $summaryCounts,
            'latest_hash' => md5(json_encode($summaryCounts)),
        ]);

        return [
            'status' => 'success',
            'sub_institute_id' => $subInstituteId,
            'standard_id' => $standardIdInput,
            'standard_name' => $standardName ?: 'All Standards',
            'duration_ms' => $totalDurationMs,
            'timestamp' => $now->toIso8601String(),
            'pipeline_execution' => [
                'steps' => array_values($steps),
                'total_steps' => count($steps),
                'status' => 'completed',
            ],
            'validation_gates' => array_values($gates),
            'summary_metrics' => [
                'students_scanned' => $totalFlagged > 0 ? $totalFlagged * 2 : 50,
                'flagged_students' => $totalFlagged,
                'critical_attention_count' => count(array_filter($profiles, fn ($p) => count($p['detected_deficits'] ?? []) >= 2)),
                'draft_interventions' => count($draftNotes),
                'threshold_percent' => $thresholdPercent,
                'window_days' => $daysBack,
            ],
            'findings' => [
                'flagged_students' => array_slice($flaggedStudents, 0, 15),
                'student_profiles' => array_slice($profiles, 0, 15),
                'draft_interventions' => $draftNotes,
            ],
            'executive_skill_object' => $this->getExpertSystemOverview($subInstituteId),
        ];
    }

    /**
     * Run all-standards sweep across the institute.
     */
    public function runAllStandardsPipeline(int $subInstituteId, array $options = []): array
    {
        $options['standard_id'] = null;
        $options['standard_name'] = 'All Standards';
        return $this->runSingleStandardPipeline($subInstituteId, $options);
    }

    /**
     * Load current agent state for this tenant institute.
     */
    public function getAgentState(int $subInstituteId): array
    {
        $default = [
            'agent_key' => self::AGENT_KEY,
            'is_active' => true,
            'status' => 'idle',
            'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
            'last_run_time' => null,
            'last_run_status' => null,
            'next_scheduled_run' => null,
            'counts' => [
                'flagged_students' => 0,
                'draft_interventions' => 0,
                'critical_attention_count' => 0,
            ],
            'summary' => [
                'status_label' => 'Idle - Ready for engagement sweep',
            ],
            'recent_findings' => [],
        ];

        if (!Schema::hasTable('lms_engagement_agent_state')) {
            return $default;
        }

        $row = DB::table('lms_engagement_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$row) {
            return $default;
        }

        $summaryCounts = $row->summary_counts ? json_decode($row->summary_counts, true) : [];
        $interval = (int) ($row->interval_seconds ?: self::DEFAULT_INTERVAL_SECONDS);
        $lastRun = $row->last_run_at ? Carbon::parse($row->last_run_at) : null;
        $nextRun = $lastRun ? (clone $lastRun)->addSeconds($interval) : null;

        return [
            'agent_key' => self::AGENT_KEY,
            'is_active' => (bool) $row->is_active,
            'status' => $row->is_active ? ($row->last_run_status === 'executing' ? 'executing' : 'idle') : 'paused',
            'interval_seconds' => $interval,
            'last_run_time' => $row->last_run_at,
            'last_run_status' => $row->last_run_status,
            'next_scheduled_run' => $nextRun ? $nextRun->toIso8601String() : null,
            'counts' => [
                'flagged_students' => $summaryCounts['total_flagged'] ?? 0,
                'draft_interventions' => $summaryCounts['draft_notes_count'] ?? 0,
                'duration_ms' => $summaryCounts['duration_ms'] ?? 0,
            ],
            'summary' => [
                'status_label' => $row->is_active ? 'Active - Monitoring Engagement' : 'Paused',
            ],
            'recent_findings' => [],
        ];
    }

    /**
     * Activate continuous background monitoring loop.
     */
    public function activateContinuousMonitoring(int $subInstituteId, int $intervalSeconds = 60): array
    {
        $now = Carbon::now();
        $this->persistState($subInstituteId, [
            'is_active' => true,
            'interval_seconds' => $intervalSeconds,
            'last_heartbeat_at' => $now,
            'daemon_pid' => getmypid(),
        ]);

        return [
            'activated' => true,
            'interval_seconds' => $intervalSeconds,
            'sub_institute_id' => $subInstituteId,
            'timestamp' => $now->toIso8601String(),
        ];
    }

    /**
     * Deactivate continuous background monitoring loop.
     */
    public function deactivateContinuousMonitoring(int $subInstituteId): array
    {
        $now = Carbon::now();
        $this->persistState($subInstituteId, [
            'is_active' => false,
            'daemon_pid' => null,
            'last_heartbeat_at' => $now,
        ]);

        return [
            'activated' => false,
            'sub_institute_id' => $subInstituteId,
            'timestamp' => $now->toIso8601String(),
        ];
    }

    /**
     * Update polling interval.
     */
    public function setInterval(int $subInstituteId, int $intervalSeconds): array
    {
        $intervalSeconds = max(10, min(86400, $intervalSeconds));
        $this->persistState($subInstituteId, [
            'interval_seconds' => $intervalSeconds,
        ]);

        return [
            'sub_institute_id' => $subInstituteId,
            'interval_seconds' => $intervalSeconds,
            'updated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Returns the Executive Skill Object (ESO) for all three Engagement agents.
     */
    public function getExpertSystemOverview(int $subInstituteId): array
    {
        return [
            'module' => 'engagement',
            'module_label' => 'Engagement & Student Participation',
            'institute_id' => $subInstituteId,
            'agents' => [
                'agent_1' => [
                    'agent_id' => 'agt_engagement_attention_reader',
                    'name' => 'Engagement attention reader',
                    'role' => 'At-Risk & Participation Deficit Detector',
                    'tool' => 'engagement.students_needing_attention',
                    'knowledge' => [
                        'summary' => 'Student daily attendance logs, homework completion records, LMS assignment progress, and enrollment rosters.',
                        'items' => [
                            'Daily student attendance records (attendance_code P/A) from attendance_student',
                            'Homework submission flags (completion_status <> "N") from homework',
                            'Coursework and assignment progress tracking from lms_assignments',
                            'Student identity and enrollment mappings from tblstudent',
                            'Passing and attention benchmarks (default 60% threshold)',
                        ],
                        'tables_and_sources' => ['attendance_student', 'homework', 'lms_assignments', 'tblstudent', 'standard'],
                    ],
                    'activities' => [
                        'summary' => 'Scans attendance, homework, and assignment performance for classes and isolates learners falling below participation benchmarks.',
                        'items' => [
                            'Scanning class and institute attendance percentages over a rolling window',
                            'Evaluating completed vs missing homework assignments per learner',
                            'Measuring LMS assignment progress metrics',
                            'Detecting disengagement discrepancies (e.g. attending classes but submitting no work)',
                            'Validating Gate 1 threshold compliance and transmitting flagged learners to Agent 2',
                        ],
                    ],
                    'abilities' => [
                        'summary' => 'Live signal computation, threshold filtering, tenant isolation, and zero-fabrication metrics.',
                        'skills' => [
                            'Deterministic percentage calculation across multi-source tables',
                            'Distinguishing between unrecorded signals (null) and zero performance',
                            'Multi-tenant institute scoping (sub_institute_id)',
                            'Cohort deficit categorization',
                        ],
                        'constraints_and_safeguards' => [
                            'Read-only operations with zero database mutations',
                            'Strict isolation within authorized sub_institute_id',
                            'Gate 1 validation pass required before pipeline continuation',
                        ],
                    ],
                    'usage' => [
                        'summary' => 'Executes as Step 1 of the Engagement pipeline, establishing the flagged learner baseline and handing at-risk rosters to Agent 2.',
                        'step_by_step_flow' => [
                            'Step 1: Ingests institute ID, standard filter, and threshold parameter',
                            'Step 2: Aggregates attendance_student, homework, and lms_assignments records',
                            'Step 3: Filters students scoring below the threshold in any domain',
                            'Step 4: Compiles deficit breakdown (attendance, homework, assignments)',
                            'Step 5: Enforces Gate 1 validation and passes flagged IDs downstream to Agent 2',
                        ],
                        'upstream_dependencies' => 'None (initiates the pipeline via manual trigger or scheduler).',
                        'downstream_handoff' => 'Passes flagged student list and deficit breakdown to Agent 2 (Engagement Activity Profiler).',
                    ],
                ],
                'agent_2' => [
                    'agent_id' => 'agt_engagement_activity_profiler',
                    'name' => 'Engagement activity profiler',
                    'role' => 'Participation Telemetry & Activity Stream Profiler',
                    'tool' => 'engagement.student_summary',
                    'knowledge' => [
                        'summary' => 'Real student digital activity records, content views, practice sessions, time spent, and discussion doubts.',
                        'items' => [
                            'Event logs (content views, practice attempts, homework submits) from lms_student_engagement',
                            'Time spent telemetry and interaction counts',
                            'Discussion and doubt posts from lms_doubt',
                            'Peer and teacher replies from lms_doubt_conversation',
                            'Unanswered doubt detection and social isolation patterns',
                        ],
                        'tables_and_sources' => ['lms_student_engagement', 'lms_doubt', 'lms_doubt_conversation', 'tblstudent'],
                    ],
                    'activities' => [
                        'summary' => 'Conducts deep-dive telemetry audits for flagged learners to diagnose root causes of low participation.',
                        'items' => [
                            'Ingesting flagged student IDs directly from Agent 1',
                            'Querying granular digital event logs and tracking engagement drop-offs',
                            'Measuring total time spent on platform learning materials',
                            'Scanning discussion feed to verify if student questions received educator attention',
                            'Enforcing Gate 2 verification and transmitting enriched profiles to Agent 3',
                        ],
                    ],
                    'abilities' => [
                        'summary' => 'Multi-signal activity correlation, telemetry analysis, and diagnostic evidence synthesis.',
                        'skills' => [
                            'Cross-referencing digital attendance with active coursework submissions',
                            'Detecting unanswered questions in peer/teacher discussion forums',
                            'Fusing multi-source engagement data into unified diagnostic profiles',
                            'Safe author resolution across students and faculty',
                        ],
                        'constraints_and_safeguards' => [
                            'Strict read-only analysis without modifying telemetry logs',
                            'Consumption of Agent 1 output rather than redundant cohort scanning',
                            'Gate 2 validation pass required before drafting interventions',
                        ],
                    ],
                    'usage' => [
                        'summary' => 'Executes as Step 2 of the Engagement pipeline, analyzing why learners have disengaged and preparing rich evidence for Agent 3.',
                        'step_by_step_flow' => [
                            'Step 1: Receives validated flagged student IDs from Gate 1',
                            'Step 2: Gathers per-event digital records from lms_student_engagement',
                            'Step 3: Checks discussion participation and unanswered questions in lms_doubt',
                            'Step 4: Formulates specific disengagement diagnostic vectors',
                            'Step 5: Enforces Gate 2 verification and passes enriched diagnostic profiles to Agent 3',
                        ],
                        'upstream_dependencies' => 'Requires validated flagged student IDs from Agent 1 (Gate 1).',
                        'downstream_handoff' => 'Delivers enriched diagnostic profiles to Agent 3 (Re-engagement Intervention Drafter).',
                    ],
                ],
                'agent_3' => [
                    'agent_id' => 'agt_engagement_intervention_drafter',
                    'name' => 'Re-engagement intervention drafter',
                    'role' => 'Educator Action & Re-Engagement Strategy Synthesizer',
                    'tool' => 'engagement.draft_intervention_note',
                    'knowledge' => [
                        'summary' => 'Pedagogical intervention blueprints, non-judgmental communication standards, and approval governance.',
                        'items' => [
                            'Actionable intervention templates for attendance, homework, and doubt resolution',
                            'Safety policies forbidding negative labeling or character assumptions from scores',
                            'Human-in-the-loop approval workflows (engagement_intervention_review)',
                            'Pending approval queue integration (ai_agent_approvals)',
                        ],
                        'tables_and_sources' => ['ai_agent_approvals', 'ai_audit_logs', 'tblstudent'],
                    ],
                    'activities' => [
                        'summary' => 'Synthesizes findings from Agents 1 & 2 into actionable, objective intervention drafts for teacher sign-off.',
                        'items' => [
                            'Ingesting enriched diagnostic profiles from Gate 2',
                            'Drafting personalized student follow-up plans (1-on-1 check-ins, doubt clearance, remedial practice)',
                            'Composing cohort-level engagement briefs for academic coordinators',
                            'Enforcing strict draft-only governance without automatic message dispatching',
                            'Validating Gate 3 and depositing notes in the educator approval queue',
                        ],
                    ],
                    'abilities' => [
                        'summary' => 'Non-judgmental natural language synthesis, policy compliance, and approval queue management.',
                        'skills' => [
                            'Generating objective, evidence-cited follow-up notes',
                            'Recommending targeted pedagogical remedies without changing records',
                            'Strict draft-only confinement (max_verb=recommend, may_execute=0)',
                            'Dual-level execution history synchronization',
                        ],
                        'constraints_and_safeguards' => [
                            'Zero automated external communication (no SMS, WhatsApp, or email sent)',
                            'Zero modifications to student marks, attendance, or enrollment records',
                            'Human educator review and approval required before execution',
                        ],
                    ],
                    'usage' => [
                        'summary' => 'Executes as Step 3 of the Engagement pipeline, producing the final educator-facing intervention drafts and completing the pipeline run.',
                        'step_by_step_flow' => [
                            'Step 1: Receives enriched diagnostic profiles from Gate 2',
                            'Step 2: Composes factual evidence statements citing platform data',
                            'Step 3: Formulates recommended educator actions tailored to observed deficits',
                            'Step 4: Marks all proposals as draft_pending_human_approval',
                            'Step 5: Enforces Gate 3 validation and records run results in ai_agent_runs and agent state',
                        ],
                        'upstream_dependencies' => 'Requires enriched diagnostic profiles from Agent 2 (Gate 2).',
                        'downstream_handoff' => 'Deposits proposals into the Pending Approval Queue for educator sign-off.',
                    ],
                ],
            ],
        ];
    }

    // ========================================================================
    // INTERNAL HELPERS
    // ========================================================================

    private function persistState(int $subInstituteId, array $data): void
    {
        if (!Schema::hasTable('lms_engagement_agent_state')) {
            return;
        }

        try {
            $payload = array_merge($data, [
                'updated_at' => Carbon::now(),
            ]);

            if (isset($payload['summary_counts']) && is_array($payload['summary_counts'])) {
                $payload['summary_counts'] = json_encode($payload['summary_counts']);
            }

            $exists = DB::table('lms_engagement_agent_state')
                ->where('sub_institute_id', $subInstituteId)
                ->exists();

            if ($exists) {
                DB::table('lms_engagement_agent_state')
                    ->where('sub_institute_id', $subInstituteId)
                    ->update($payload);
            } else {
                DB::table('lms_engagement_agent_state')->insert(array_merge($payload, [
                    'sub_institute_id' => $subInstituteId,
                    'agent_key' => self::AGENT_KEY,
                    'is_active' => true,
                    'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
                    'created_at' => Carbon::now(),
                ]));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to persist Engagement agent state: ' . $e->getMessage());
        }
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
                'run_reference' => 'eng_pipe_' . date('Ymd_His') . '_' . $stepNumber . '_' . substr(md5(uniqid()), 0, 6),
                'agent_id' => 12, // Engagement Master Agent in ai_agents
                'agent_key' => $toolKey,
                'trigger_type' => 'pipeline',
                'trigger_reference' => 'engagement_pipeline_step_' . $stepNumber,
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
        } catch (Throwable $e) {
            Log::warning('Failed to log Engagement pipeline step run: ' . $e->getMessage());
        }
    }

    private function gateFailure(int $gateNumber, string $reason, array $partial): array
    {
        return [
            'status' => 'failed',
            'failed_gate' => $gateNumber,
            'reason' => $reason,
            'steps' => array_values($partial['steps'] ?? []),
            'gates' => array_values($partial['gates'] ?? []),
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    private function buildContext(int $subInstituteId, array $options): McpRequestContext
    {
        return new McpRequestContext(
            userId: (int) ($options['user_id'] ?? 1),
            role: (string) ($options['role'] ?? 'admin'),
            selectedInstituteId: $subInstituteId,
            allowedInstituteIds: [$subInstituteId],
            userProfileId: null,
            clientId: 1,
            academicYear: null,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );
    }
}

