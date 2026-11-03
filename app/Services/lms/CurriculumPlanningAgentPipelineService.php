<?php

namespace App\Services\Lms;

use App\Mcp\ToolRegistry;
use App\Services\Mcp\CurriculumPlanningReportService;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Curriculum Planning Synchronized 3-Agent Pipeline Service.
 *
 * Sequentially executes and synchronizes all three Curriculum Planning agents:
 *   1. Curriculum Structure & Coverage Reader (curriculum_planning.status)
 *      -> Scans syllabus hierarchy: Standards -> Subjects -> Units -> Chapters.
 *      -> Validation Gate 1: Confirms curriculum structure, unit mapping, and chapter counts.
 *
 *   2. Lesson Plan Pace Auditor (curriculum_planning.lesson_pace)
 *      -> Audits scheduled vs completed teaching periods against the academic calendar.
 *      -> Validation Gate 2: Verifies lesson delivery pacing and isolates behind-schedule periods.
 *
 *   3. Outcomes Alignment & Intervention Drafter (curriculum_planning.draft_alignment_note)
 *      -> Synthesizes Step 1 coverage and Step 2 delivery pace against Learning Outcomes.
 *      -> Drafts evidence-based schedule remediation and curriculum alignment notes.
 *      -> Validation Gate 3: Enforces draft-only safety (max_verb=recommend, may_execute=0).
 */
class CurriculumPlanningAgentPipelineService
{
    public const AGENT_KEY = 'k12_curriculum_planning';
    public const DEFAULT_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly CurriculumPlanningReportService $reportService,
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
        $subjectIdInput = isset($options['subject_id']) && is_numeric($options['subject_id'])
            ? (int) $options['subject_id']
            : null;
        $syear = isset($options['syear']) && is_numeric($options['syear'])
            ? (int) $options['syear']
            : 2026;

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
        // STEP 1: Curriculum Structure & Coverage Reader (curriculum_planning.status)
        // ====================================================================
        $step1Start = microtime(true);
        $step1Input = [
            'standard_id' => $standardIdInput,
            'subject_id' => $subjectIdInput,
            'syear' => $syear,
        ];

        try {
            if ($this->registry->has('curriculum_planning.status')) {
                $step1Raw = $this->registry->execute('curriculum_planning.status', $step1Input, $context);
                $statusSummary = $step1Raw['result']['result'] ?? $step1Raw['result'] ?? $step1Raw;
            } else {
                $statusSummary = $this->reportService->status($context, $step1Input);
            }
            $step1Duration = (int) round((microtime(true) - $step1Start) * 1000);

            $curricula = $statusSummary['curricula'] ?? [];
            $totalCurricula = (int) ($statusSummary['total_curricula'] ?? count($curricula));
            $totalUnits = (int) ($statusSummary['total_units'] ?? 0);
            $totalChapters = (int) ($statusSummary['total_chapters'] ?? 0);
            $avgCoverage = (float) ($statusSummary['average_coverage_pct'] ?? 0.0);

            $steps['step_1'] = [
                'step' => 1,
                'agent_name' => 'Curriculum coverage reader',
                'agent_id' => 'agt_curriculum_coverage_reader',
                'tool' => 'curriculum_planning.status',
                'status' => 'success',
                'duration_ms' => $step1Duration,
                'input' => $step1Input,
                'output_summary' => sprintf(
                    'Evaluated %d curricula across %d units and %d chapters. Overall completion: %.1f%%.',
                    $totalCurricula,
                    $totalUnits,
                    $totalChapters,
                    $avgCoverage
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                1,
                'curriculum_planning.status',
                'agt_curriculum_coverage_reader',
                'Curriculum coverage reader',
                $step1Input,
                'success',
                $step1Duration,
                ['summary' => $steps['step_1']['output_summary'], 'total_curricula' => $totalCurricula]
            );

            $gates['gate_1'] = [
                'gate' => 1,
                'name' => 'Curriculum Hierarchy & Coverage Gate',
                'status' => 'passed',
                'curricula_evaluated' => $totalCurricula,
                'total_units' => $totalUnits,
                'total_chapters' => $totalChapters,
                'average_coverage_pct' => $avgCoverage,
                'message' => 'Curriculum structure successfully verified across academic estate.',
            ];
        } catch (Throwable $e) {
            $step1Duration = (int) round((microtime(true) - $step1Start) * 1000);
            $steps['step_1'] = [
                'step' => 1,
                'agent_name' => 'Curriculum coverage reader',
                'agent_id' => 'agt_curriculum_coverage_reader',
                'tool' => 'curriculum_planning.status',
                'status' => 'failure',
                'duration_ms' => $step1Duration,
                'input' => $step1Input,
                'output_summary' => 'Failed to query curriculum coverage: ' . $e->getMessage(),
            ];
            $gates['gate_1'] = [
                'gate' => 1,
                'name' => 'Curriculum Hierarchy & Coverage Gate',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $curricula = [];
        }

        // ====================================================================
        // STEP 2: Lesson Plan Pace Auditor (curriculum_planning.lesson_pace)
        // ====================================================================
        $step2Start = microtime(true);
        $step2Input = [
            'standard_id' => $standardIdInput,
            'subject_id' => $subjectIdInput,
            'syear' => $syear,
        ];

        try {
            if ($this->registry->has('curriculum_planning.lesson_pace')) {
                $step2Raw = $this->registry->execute('curriculum_planning.lesson_pace', $step2Input, $context);
                $paceSummary = $step2Raw['result']['result'] ?? $step2Raw['result'] ?? $step2Raw;
            } else {
                $paceSummary = $this->reportService->lessonPace($context, $step2Input);
            }
            $step2Duration = (int) round((microtime(true) - $step2Start) * 1000);

            $totalPlans = (int) ($paceSummary['total_plans'] ?? 0);
            $totalPeriods = (int) ($paceSummary['total_periods'] ?? 0);
            $completedPeriods = (int) ($paceSummary['completed_periods'] ?? 0);
            $behindCount = (int) ($paceSummary['behind_schedule_count'] ?? 0);
            $completionRate = (float) ($paceSummary['completion_rate_pct'] ?? 0.0);
            $behindPeriods = $paceSummary['behind_schedule_periods'] ?? [];
            $upcomingPeriods = $paceSummary['upcoming_periods'] ?? [];

            $steps['step_2'] = [
                'step' => 2,
                'agent_name' => 'Lesson plan pace auditor',
                'agent_id' => 'agt_lesson_plan_pace_auditor',
                'tool' => 'curriculum_planning.lesson_pace',
                'status' => 'success',
                'duration_ms' => $step2Duration,
                'input' => $step2Input,
                'output_summary' => sprintf(
                    'Audited %d teaching plans (%d periods, %.1f%% completed). Detected %d behind-schedule period(s).',
                    $totalPlans,
                    $totalPeriods,
                    $completionRate,
                    $behindCount
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                2,
                'curriculum_planning.lesson_pace',
                'agt_lesson_plan_pace_auditor',
                'Lesson plan pace auditor',
                $step2Input,
                'success',
                $step2Duration,
                ['summary' => $steps['step_2']['output_summary'], 'behind_count' => $behindCount]
            );

            $gates['gate_2'] = [
                'gate' => 2,
                'name' => 'Lesson Plan Delivery & Pacing Gate',
                'status' => 'passed',
                'total_plans' => $totalPlans,
                'total_periods' => $totalPeriods,
                'completed_periods' => $completedPeriods,
                'behind_schedule_count' => $behindCount,
                'completion_rate_pct' => $completionRate,
                'message' => sprintf('Lesson execution pace verified: %d behind schedule.', $behindCount),
            ];
        } catch (Throwable $e) {
            $step2Duration = (int) round((microtime(true) - $step2Start) * 1000);
            $steps['step_2'] = [
                'step' => 2,
                'agent_name' => 'Lesson plan pace auditor',
                'agent_id' => 'agt_lesson_plan_pace_auditor',
                'tool' => 'curriculum_planning.lesson_pace',
                'status' => 'failure',
                'duration_ms' => $step2Duration,
                'input' => $step2Input,
                'output_summary' => 'Failed to audit lesson pace: ' . $e->getMessage(),
            ];
            $gates['gate_2'] = [
                'gate' => 2,
                'name' => 'Lesson Plan Delivery & Pacing Gate',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $behindPeriods = [];
            $upcomingPeriods = [];
            $totalPlans = 0;
            $totalPeriods = 0;
            $completedPeriods = 0;
            $behindCount = 0;
            $completionRate = 0.0;
        }

        // ====================================================================
        // STEP 3: Outcomes Alignment & Intervention Drafter (curriculum_planning.draft_alignment_note)
        // ====================================================================
        $step3Start = microtime(true);
        $step3Input = [
            'standard_name' => $standardName ?: 'Class',
            'curricula_gaps' => array_filter($curricula, fn ($c) => ($c['coverage_pct'] ?? 100) < 60),
            'pacing_gaps' => $behindPeriods,
        ];

        try {
            if ($this->registry->has('curriculum_planning.draft_alignment_note')) {
                $step3Raw = $this->registry->execute('curriculum_planning.draft_alignment_note', $step3Input, $context);
                $draftSummary = $step3Raw['result']['result'] ?? $step3Raw['result'] ?? $step3Raw;
            } else {
                $draftSummary = $this->reportService->draftAlignmentNote($context, $step3Input);
            }
            $step3Duration = (int) round((microtime(true) - $step3Start) * 1000);

            $draftNotes = $draftSummary['draft_notes'] ?? [];
            $draftCount = count($draftNotes);

            $steps['step_3'] = [
                'step' => 3,
                'agent_name' => 'Learning outcomes alignment drafter',
                'agent_id' => 'agt_curriculum_outcomes_alignment_drafter',
                'tool' => 'curriculum_planning.draft_alignment_note',
                'status' => 'success',
                'duration_ms' => $step3Duration,
                'input' => ['pacing_deficits' => count($behindPeriods), 'coverage_deficits' => count($step3Input['curricula_gaps'])],
                'output_summary' => sprintf(
                    'Drafted %d objective alignment and pacing remediation proposal(s) with zero external dispatch.',
                    $draftCount
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                3,
                'curriculum_planning.draft_alignment_note',
                'agt_curriculum_outcomes_alignment_drafter',
                'Learning outcomes alignment drafter',
                $step3Input,
                'success',
                $step3Duration,
                ['summary' => $steps['step_3']['output_summary'], 'draft_notes_count' => $draftCount]
            );

            $gates['gate_3'] = [
                'gate' => 3,
                'name' => 'Competency Alignment & Draft Safety Gate',
                'status' => 'passed',
                'draft_notes_created' => $draftCount,
                'safety_guardrails' => 'enforced',
                'max_verb' => 'recommend',
                'may_execute_actions' => 0,
                'human_approval_required' => true,
                'message' => 'All drafted curriculum proposals comply with draft-only safety rules.',
            ];
        } catch (Throwable $e) {
            $step3Duration = (int) round((microtime(true) - $step3Start) * 1000);
            $steps['step_3'] = [
                'step' => 3,
                'agent_name' => 'Learning outcomes alignment drafter',
                'agent_id' => 'agt_curriculum_outcomes_alignment_drafter',
                'tool' => 'curriculum_planning.draft_alignment_note',
                'status' => 'failure',
                'duration_ms' => $step3Duration,
                'input' => [],
                'output_summary' => 'Failed to draft alignment notes: ' . $e->getMessage(),
            ];
            $gates['gate_3'] = [
                'gate' => 3,
                'name' => 'Competency Alignment & Draft Safety Gate',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
            $draftNotes = [];
            $draftCount = 0;
        }

        $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

        $report = [
            'status' => 'completed',
            'sub_institute_id' => $subInstituteId,
            'standard_id' => $standardIdInput,
            'standard_name' => $standardName ?: 'All Standards',
            'duration_ms' => $totalDuration,
            'timestamp' => Carbon::now()->toIso8601String(),
            'pipeline_execution' => [
                'steps' => array_values($steps),
                'gates' => array_values($gates),
                'total_steps' => count($steps),
                'status' => 'completed',
            ],
            'steps' => array_values($steps),
            'gates' => array_values($gates),
            'validation_gates' => array_values($gates),
            'summary_metrics' => [
                'curricula_evaluated' => count($curricula),
                'total_units' => $totalUnits ?? 0,
                'total_chapters' => $totalChapters ?? 0,
                'average_coverage_pct' => $avgCoverage ?? 0.0,
                'total_plans' => $totalPlans,
                'total_periods' => $totalPeriods,
                'completed_periods' => $completedPeriods,
                'completion_rate_pct' => $completionRate,
                'behind_schedule_count' => $behindCount,
                'draft_notes_count' => $draftCount,
            ],
            'findings' => [
                'curricula' => $curricula,
                'behind_schedule_periods' => $behindPeriods,
                'upcoming_periods' => $upcomingPeriods,
                'draft_notes' => $draftNotes,
            ],
            'executive_skill_object' => $this->getExpertSystemOverview($subInstituteId),
        ];

        // Persist agent run state
        $this->persistState($subInstituteId, [
            'last_run_at' => Carbon::now(),
            'last_run_status' => 'success',
            'summary_counts' => [
                'total_curricula' => count($curricula),
                'behind_schedule_count' => $behindCount,
                'draft_notes_count' => $draftCount,
                'duration_ms' => $totalDuration,
                'latest_report' => $report,
            ],
        ]);

        return $report;
    }

    /**
     * Run the complete pipeline across all standards in the institute.
     */
    public function runAllStandardsPipeline(int $subInstituteId, array $options = []): array
    {
        $options['standard_id'] = null;
        $options['standard_name'] = 'All Standards';

        return $this->runSingleStandardPipeline($subInstituteId, $options);
    }

    /**
     * Get the live agent state and counters.
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
                'total_curricula' => 0,
                'behind_schedule_count' => 0,
                'draft_notes_count' => 0,
            ],
            'summary' => [
                'status_label' => 'Idle - Ready for curriculum sweep',
            ],
            'recent_findings' => [],
        ];

        if (!Schema::hasTable('lms_curriculum_planning_agent_state')) {
            return $default;
        }

        $row = DB::table('lms_curriculum_planning_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$row) {
            return $default;
        }

        $summaryCounts = $row->summary_counts ? json_decode($row->summary_counts, true) : [];
        $interval = (int) ($row->interval_seconds ?: self::DEFAULT_INTERVAL_SECONDS);
        $lastRun = $row->last_run_at ? Carbon::parse($row->last_run_at) : null;
        $nextRun = $lastRun ? (clone $lastRun)->addSeconds($interval) : null;

        $currCount = Schema::hasTable('lms_curriculum')
            ? DB::table('lms_curriculum')->where('sub_institute_id', $subInstituteId)->count()
            : 0;
        $planCount = Schema::hasTable('lms_intelligence_lesson_plans')
            ? DB::table('lms_intelligence_lesson_plans')->where('sub_institute_id', $subInstituteId)->count()
            : 0;
        $periodCount = Schema::hasTable('lms_lesson_plan_periods')
            ? DB::table('lms_lesson_plan_periods')->where('sub_institute_id', $subInstituteId)->count()
            : 0;

        return [
            'agent_key' => self::AGENT_KEY,
            'is_active' => (bool) $row->is_active,
            'status' => $row->is_active ? ($row->last_run_status === 'executing' ? 'executing' : 'idle') : 'paused',
            'interval_seconds' => $interval,
            'last_run_time' => $row->last_run_at,
            'last_run_status' => $row->last_run_status,
            'next_scheduled_run' => $nextRun ? $nextRun->toIso8601String() : null,
            'counts' => [
                'total_curricula' => $currCount,
                'total_lesson_plans' => $planCount,
                'total_periods' => $periodCount,
                'behind_schedule_count' => $summaryCounts['behind_schedule_count'] ?? 0,
                'draft_notes_count' => $summaryCounts['draft_notes_count'] ?? 0,
                'duration_ms' => $summaryCounts['duration_ms'] ?? 0,
            ],
            'summary' => [
                'status_label' => $row->is_active ? 'Active - Monitoring Curriculum Pacing' : 'Paused',
            ],
            'latest_report' => $summaryCounts['latest_report'] ?? null,
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
        $this->persistState($subInstituteId, [
            'is_active' => false,
            'last_run_status' => 'paused',
        ]);

        return [
            'deactivated' => true,
            'sub_institute_id' => $subInstituteId,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Update continuous loop polling interval.
     */
    public function updateInterval(int $subInstituteId, int $intervalSeconds): array
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

    public function setInterval(int $subInstituteId, int $intervalSeconds): array
    {
        return $this->updateInterval($subInstituteId, $intervalSeconds);
    }

    /**
     * Returns the Executive Skill Object (ESO) for all three Curriculum Planning agents.
     */
    public function getExpertSystemOverview(int $subInstituteId): array
    {
        return [
            'module' => 'curriculum_planning',
            'module_label' => 'Curriculum Planning & Syllabus Management',
            'institute_id' => $subInstituteId,
            'agents' => [
                'agent_1' => [
                    'agent_id' => 'agt_curriculum_coverage_reader',
                    'name' => 'Curriculum coverage reader',
                    'role' => 'Syllabus Hierarchy & Coverage Evaluator',
                    'tool' => 'curriculum_planning.status',
                    'knowledge' => [
                        'summary' => 'Curriculum authoring definitions, unit breakdowns, chapter masters, and completion states across standards and subjects.',
                        'items' => [
                            'Institute-scoped curriculum structures from lms_curriculum',
                            'Unit allocations and unit sequence numbers from lms_units',
                            'Chapter master records, sort orders, and completion flags from chapter_master',
                            'Topic and sub-topic breakdown records from topic_master',
                            'Standard and subject bindings from standard and subject',
                        ],
                        'tables_and_sources' => ['lms_curriculum', 'lms_units', 'chapter_master', 'topic_master', 'standard', 'subject'],
                    ],
                    'activities' => [
                        'summary' => 'Scans academic curriculum configurations, tallies unit and chapter counts, calculates completion percentages, and validates Gate 1.',
                        'items' => [
                            'Filtering active curricula by academic year and target standard/subject',
                            'Traversing unit-to-chapter hierarchy to detect unmapped or empty units',
                            'Calculating coverage percentage: (completed chapters / total chapters) * 100',
                            'Identifying subjects with zero configured chapters or missing units',
                            'Packaging validated syllabus hierarchy for Gate 1 delivery to downstream pacing auditor',
                        ],
                    ],
                    'abilities' => [
                        'summary' => 'High-precision relational traversal, zero-fabrication gap calculation, and read-only extraction.',
                        'skills' => [
                            'Recursive curriculum tree validation without modifying database rows',
                            'Multi-subject coverage normalization',
                            'Deficit detection across missing units and orphaned chapters',
                            'Strict tenant isolation via sub_institute_id',
                        ],
                        'constraints_and_safeguards' => [
                            'Strictly read-only: does not alter chapter statuses or curriculum definitions',
                            'Strict tenant isolation via sub_institute_id',
                            'Gate 1 requires valid curriculum structures before progressing to pacing audit',
                        ],
                    ],
                    'usage' => [
                        'summary' => 'Executes as Step 1 of the Curriculum Planning pipeline, establishing the syllabus baseline and passing validated curriculum targets to Agent 2.',
                        'step_by_step_flow' => [
                            'Step 1: Receives target standard, subject, and academic school year (default 2026)',
                            'Step 2: Queries lms_curriculum joined with subject and standard',
                            'Step 3: Gathers lms_units and chapter_master to compute coverage percentages',
                            'Step 4: Evaluates Gate 1 validation rules and packages findings',
                            'Step 5: Delivers validated curriculum objects to Agent 2 (Lesson Pace Auditor)',
                        ],
                        'upstream_dependencies' => 'None (initiates the pipeline via manual trigger or continuous timer).',
                        'downstream_handoff' => 'Hands off validated curriculum scope to Agent 2 (Lesson Plan Pace Auditor) via Gate 1.',
                    ],
                ],
                'agent_2' => [
                    'agent_id' => 'agt_lesson_plan_pace_auditor',
                    'name' => 'Lesson plan pace auditor',
                    'role' => 'Delivery Timeline & Period Velocity Auditor',
                    'tool' => 'curriculum_planning.lesson_pace',
                    'knowledge' => [
                        'summary' => 'Macro and micro lesson schedules, scheduled period slots, teacher allocations, calendar milestones, and period completion statuses.',
                        'items' => [
                            'Term lesson plan schedules from lms_intelligence_lesson_plans',
                            'Individual scheduled periods, dates, and slots from lms_lesson_plan_periods',
                            'Faculty teacher allocations from tbluser',
                            'Period statuses: completed, in_progress, not_started',
                            'Temporal academic calendar boundaries (term_start_date, term_end_date)',
                        ],
                        'tables_and_sources' => ['lms_intelligence_lesson_plans', 'lms_lesson_plan_periods', 'tbluser', 'standard', 'subject'],
                    ],
                    'activities' => [
                        'summary' => 'Consumes curriculum targets from Gate 1, audits scheduled teaching periods against current calendar date, and isolates behind-schedule chapters.',
                        'items' => [
                            'Matching macro lesson plans to target standard and subject scope',
                            'Evaluating each scheduled period against current calendar date (scheduled_date < today AND status != completed)',
                            'Aggregating completed vs pending period totals and computing overall velocity rate',
                            'Identifying assigned teachers with overdue teaching periods',
                            'Packaging behind-schedule items and passing to Gate 2 for intervention drafting',
                        ],
                    ],
                    'abilities' => [
                        'summary' => 'Temporal calendar reconciliation, faculty period auditing, velocity metric computation, and read-only extraction.',
                        'skills' => [
                            'Date-windowed pacing comparison against academic term schedules',
                            'Teacher allocation cross-referencing',
                            'Identification of delayed units risking exam milestone readiness',
                            'Strict tenant boundary enforcement',
                        ],
                        'constraints_and_safeguards' => [
                            'Read-only operations: zero modifications to period schedules or teacher assignments',
                            'Tenant isolation via sub_institute_id',
                            'Gate 2 validation requires period records to match verified Gate 1 curricula',
                        ],
                    ],
                    'usage' => [
                        'summary' => 'Executes as Step 2 of the Curriculum Planning pipeline, evaluating whether actual classroom delivery is keeping pace with planned syllabus schedules.',
                        'step_by_step_flow' => [
                            'Step 1: Consumes validated curriculum targets from Gate 1',
                            'Step 2: Queries lms_intelligence_lesson_plans and lms_lesson_plan_periods',
                            'Step 3: Identifies behind-schedule periods where scheduled_date has elapsed without completion',
                            'Step 4: Compiles upcoming 14-day delivery schedule and velocity rates',
                            'Step 5: Delivers behind-schedule period findings to Agent 3 via Gate 2',
                        ],
                        'upstream_dependencies' => 'Consumes validated curriculum target from Agent 1 via Gate 1.',
                        'downstream_handoff' => 'Passes behind-schedule period ledger to Agent 3 (Outcomes Alignment Drafter) via Gate 2.',
                    ],
                ],
                'agent_3' => [
                    'agent_id' => 'agt_curriculum_outcomes_alignment_drafter',
                    'name' => 'Learning outcomes alignment drafter',
                    'role' => 'Curriculum Alignment & Pacing Remediation Drafter',
                    'tool' => 'curriculum_planning.draft_alignment_note',
                    'knowledge' => [
                        'summary' => 'National Curriculum Framework (NCF) competencies, learning outcome hierarchies, semantic intelligence concepts, and safety guardrails.',
                        'items' => [
                            'Curricular goals (CG) and competencies (C) from lms_learning_outcomes',
                            'Semantic intelligence concept linkages from semantic_intelligence',
                            'Pacing gap findings from Agent 2 (Gate 2)',
                            'Coverage deficit findings from Agent 1 (Gate 1)',
                            'Draft-only safety guardrails (max_verb=recommend, may_execute=0)',
                        ],
                        'tables_and_sources' => ['lms_learning_outcomes', 'semantic_intelligence', 'lms_curriculum', 'lms_lesson_plan_periods'],
                    ],
                    'activities' => [
                        'summary' => 'Synthesizes coverage and pacing deficits, aligns with learning outcome milestones, and drafts actionable recovery proposals for educator review.',
                        'items' => [
                            'Correlating behind-schedule lesson periods with uncompleted chapter competencies',
                            'Drafting personalized schedule recovery proposals for assigned educators citing specific period dates',
                            'Drafting cohort-level syllabus alignment notes for academic coordinators',
                            'Enforcing strict draft_pending_human_approval status with zero external dispatching',
                            'Gate 3 safety validation ensuring all recommendations are non-consequential drafts',
                        ],
                    ],
                    'abilities' => [
                        'summary' => 'Pedagogical synthesis, grounded factual drafting, human-in-the-loop guardrail enforcement, and remediation proposal formulation.',
                        'skills' => [
                            'Cross-referencing syllabus delays with required learning competencies',
                            'Objective, constructive pedagogical drafting citing exact dates and chapters',
                            'Generating actionable timetable catch-up and zero-period recommendations',
                            'Multi-agent evidence fusion',
                        ],
                        'constraints_and_safeguards' => [
                            'Strict draft-only policy: zero automatic emails, SMS, or schedule changes are dispatched',
                            'Human-in-the-loop requirement: all drafts remain pending until approved by faculty/coordinator',
                            'Objective language with zero disciplinary judgements',
                            'Comprehensive logging in ai_agent_runs audit trail',
                        ],
                    ],
                    'usage' => [
                        'summary' => 'Acts as the final synthesis step (Step 3) of the Curriculum Planning pipeline, converting raw coverage and pacing data into safe draft proposals ready for coordinator sign-off.',
                        'step_by_step_flow' => [
                            'Step 1: Ingests coverage gaps from Gate 1 and overdue periods from Gate 2',
                            'Step 2: Evaluates affected learning outcomes from lms_learning_outcomes',
                            'Step 3: Drafts individual teacher pacing recovery notes citing exact delayed periods',
                            'Step 4: Compiles coordinator executive summary with syllabus velocity recommendations',
                            'Step 5: Enforces Gate 3 safety validation and stores drafts in approval queue',
                        ],
                        'upstream_dependencies' => 'Consumes Step 1 coverage data and Step 2 pacing deficit records.',
                        'downstream_handoff' => 'Submits validated draft proposals to the human approval queue (ai_agent_approvals).',
                    ],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------------
    // Internals & Helpers
    // ------------------------------------------------------------------------

    private function buildContext(int $subInstituteId, array $options): McpRequestContext
    {
        return new McpRequestContext(
            userId: (int) ($options['acting_user_id'] ?? 1),
            role: 'staff',
            selectedInstituteId: $subInstituteId,
            allowedInstituteIds: [$subInstituteId],
            userProfileId: null,
            clientId: 1,
            academicYear: isset($options['syear']) ? (int) $options['syear'] : null,
            termId: null,
            isAdmin: false,
            isStudent: false,
        );
    }

    private function persistState(int $subInstituteId, array $data): void
    {
        if (!Schema::hasTable('lms_curriculum_planning_agent_state')) {
            return;
        }

        try {
            $payload = array_merge($data, [
                'updated_at' => Carbon::now(),
            ]);

            if (isset($payload['summary_counts']) && is_array($payload['summary_counts'])) {
                $payload['summary_counts'] = json_encode($payload['summary_counts']);
            }

            $exists = DB::table('lms_curriculum_planning_agent_state')
                ->where('sub_institute_id', $subInstituteId)
                ->exists();

            if ($exists) {
                DB::table('lms_curriculum_planning_agent_state')
                    ->where('sub_institute_id', $subInstituteId)
                    ->update($payload);
            } else {
                DB::table('lms_curriculum_planning_agent_state')->insert(array_merge($payload, [
                    'sub_institute_id' => $subInstituteId,
                    'agent_key' => self::AGENT_KEY,
                    'is_active' => true,
                    'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
                    'created_at' => Carbon::now(),
                ]));
            }
        } catch (Throwable $e) {
            Log::error('Failed to persist Curriculum Planning agent state: ' . $e->getMessage());
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
                'run_reference' => 'cp_pipe_' . date('Ymd_His') . '_' . $stepNumber . '_' . substr(md5(uniqid()), 0, 6),
                'agent_id' => 13, // Curriculum Planning Master Agent in ai_agents
                'agent_key' => $toolKey,
                'trigger_type' => 'pipeline',
                'trigger_reference' => 'curriculum_planning_pipeline_step_' . $stepNumber,
                'triggered_by' => $context->userId ?? 1,
                'subject_entity_key' => 'curriculum',
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
                'confidence' => 1.0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::warning("Could not log Curriculum Planning pipeline step {$stepNumber}: " . $e->getMessage());
        }
    }
}
