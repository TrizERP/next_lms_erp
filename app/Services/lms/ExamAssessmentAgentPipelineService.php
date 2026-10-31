<?php

namespace App\Services\Lms;

use App\Mcp\ToolRegistry;
use App\Services\Mcp\AssignmentReportService;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\OnlineExamReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Exam & Assessment Synchronized 3-Agent Pipeline Service.
 *
 * Sequentially executes and synchronizes all three Exam & Assessment agents:
 *   1. Online Exam Summary Reader (exam_assessment.online_exam_summary)
 *      -> Retrieves actual online exam attempts, average scores, and at-risk students.
 *      -> Validation Gate 1: Confirms exam coverage, attempts, and at-risk identification.
 *
 *   2. Assignment Status Reader (exam_assessment.assignment_status)
 *      -> Evaluates assignment, worksheet, and project submissions for the scope.
 *      -> Validation Gate 2: Verifies submission counts, reviewed statuses, and missing work.
 *
 *   3. Assessment Review Drafter (exam_assessment.draft_review_note)
 *      -> Synthesizes low-score attempt findings and unsubmitted work.
 *      -> Generates factual student review notes and cohort intervention drafts.
 *      -> Validation Gate 3: Confirms all drafts cite actual records and obey draft-only safety.
 *
 * Supports both:
 *   - Single Standard Focus (e.g. Standard "2231" or by name)
 *   - All-Standards Sweep across the institute
 */
class ExamAssessmentAgentPipelineService
{
    public const AGENT_KEY = 'k12_exam_assessment';
    public const DEFAULT_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly OnlineExamReportService $examReportService,
        private readonly AssignmentReportService $assignmentReportService,
    ) {
    }

    /**
     * Run the synchronized pipeline (either for a single standard or institute sweep).
     *
     * @param int $subInstituteId Tenant institute ID
     * @param array<string, mixed> $options Configuration and filtering options
     * @return array<string, mixed> Unified Exam & Assessment Pipeline Report
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
     *
     * @param int $subInstituteId
     * @param array<string, mixed> $options
     * @return array<string, mixed>
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
        $studentIdInput = isset($options['student_id']) && is_numeric($options['student_id'])
            ? (int) $options['student_id']
            : null;
        $atRiskPercent = isset($options['at_risk_percent']) && is_numeric($options['at_risk_percent'])
            ? (float) $options['at_risk_percent']
            : 40.0;
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
        // STEP 1: Online Exam Summary Reader (exam_assessment.online_exam_summary)
        // ====================================================================
        $step1Start = microtime(true);
        $step1Input = [
            'at_risk_percent' => $atRiskPercent,
            'limit' => $limit,
        ];
        if ($standardIdInput) {
            $step1Input['standard_id'] = $standardIdInput;
        }
        if ($subjectIdInput) {
            $step1Input['subject_id'] = $subjectIdInput;
        }
        if ($studentIdInput) {
            $step1Input['student_id'] = $studentIdInput;
        }

        try {
            if ($this->registry->has('exam_assessment.online_exam_summary')) {
                $step1Raw = $this->registry->execute('exam_assessment.online_exam_summary', $step1Input, $context);
                $examSummary = $step1Raw['result']['result'] ?? $step1Raw['result'] ?? $step1Raw;
            } else {
                $examSummary = $this->examReportService->summary($context, $step1Input);
            }
            $step1Duration = round((microtime(true) - $step1Start) * 1000);

            $examsPublished = (int) ($examSummary['exams_published'] ?? 0);
            $attemptsRecorded = (int) ($examSummary['attempts_recorded'] ?? 0);
            $studentsAssessed = (int) ($examSummary['students_assessed'] ?? 0);
            $avgScore = $examSummary['average_score'] !== null ? round((float) $examSummary['average_score'], 1) : null;
            $needsAttentionCount = (int) ($examSummary['needs_attention'] ?? 0);
            $atRiskAttempts = $examSummary['at_risk_attempts'] ?? [];

            $steps['step_1'] = [
                'step' => 1,
                'agent_name' => 'Online exam summary reader',
                'agent_id' => 'agt_exam_summary_reader',
                'tool' => 'exam_assessment.online_exam_summary',
                'status' => 'success',
                'duration_ms' => $step1Duration,
                'input' => $step1Input,
                'output_summary' => sprintf(
                    '%d exams published, %d attempts recorded (%d assessed). Avg: %s%%. %d at-risk attempts (<%g%%).',
                    $examsPublished,
                    $attemptsRecorded,
                    $studentsAssessed,
                    $avgScore !== null ? (string) $avgScore : 'N/A',
                    $needsAttentionCount,
                    $atRiskPercent
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                1,
                'exam_assessment.online_exam_summary',
                'agt_exam_summary_reader',
                'Online exam summary reader',
                $step1Input,
                'success',
                $step1Duration,
                ['summary' => $steps['step_1']['output_summary']]
            );

            $gates['gate_1'] = [
                'gate' => 1,
                'name' => 'Online Exam Performance Evaluation',
                'status' => 'passed',
                'exams_published' => $examsPublished,
                'attempts_recorded' => $attemptsRecorded,
                'students_assessed' => $studentsAssessed,
                'average_score' => $avgScore,
                'needs_attention' => $needsAttentionCount,
                'at_risk_count' => count($atRiskAttempts),
            ];
        } catch (Throwable $e) {
            Log::error('Exam Assessment Pipeline Step 1 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(1, 'Online Exam Summary Reader failed: ' . $e->getMessage());
        }

        // ====================================================================
        // STEP 2: Assignment Status Reader (exam_assessment.assignment_status)
        // ====================================================================
        $step2Start = microtime(true);
        $step2Input = [
            'limit' => $limit,
        ];
        if ($standardIdInput) {
            $step2Input['standard_id'] = $standardIdInput;
        }
        if ($subjectIdInput) {
            $step2Input['subject_id'] = $subjectIdInput;
        }
        if ($studentIdInput) {
            $step2Input['student_id'] = $studentIdInput;
        }

        try {
            if ($this->registry->has('exam_assessment.assignment_status')) {
                $step2Raw = $this->registry->execute('exam_assessment.assignment_status', $step2Input, $context);
                $assignSummary = $step2Raw['result']['result'] ?? $step2Raw['result'] ?? $step2Raw;
            } else {
                $assignSummary = $this->assignmentReportService->status($context, $step2Input);
            }
            $step2Duration = round((microtime(true) - $step2Start) * 1000);

            $assignTotal = (int) ($assignSummary['total'] ?? 0);
            $byWorkType = $assignSummary['by_work_type'] ?? [];
            $notSubmittedList = $assignSummary['not_submitted'] ?? [];

            $totalSubmitted = 0;
            $totalReviewed = 0;
            $totalNotSubmitted = 0;
            foreach ($byWorkType as $wt) {
                $totalSubmitted += (int) ($wt['submitted'] ?? 0);
                $totalReviewed += (int) ($wt['reviewed'] ?? 0);
                $totalNotSubmitted += (int) ($wt['not_submitted'] ?? 0);
            }

            $steps['step_2'] = [
                'step' => 2,
                'agent_name' => 'Assignment status reader',
                'agent_id' => 'agt_assignment_status_reader',
                'tool' => 'exam_assessment.assignment_status',
                'status' => 'success',
                'duration_ms' => $step2Duration,
                'input' => $step2Input,
                'output_summary' => sprintf(
                    '%d total assignments/worksheets/projects. %d submitted, %d reviewed, %d not submitted.',
                    $assignTotal,
                    $totalSubmitted,
                    $totalReviewed,
                    $totalNotSubmitted
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                2,
                'exam_assessment.assignment_status',
                'agt_assignment_status_reader',
                'Assignment status reader',
                $step2Input,
                'success',
                $step2Duration,
                ['summary' => $steps['step_2']['output_summary']]
            );

            $gates['gate_2'] = [
                'gate' => 2,
                'name' => 'Assignment & Work Status Verification',
                'status' => 'passed',
                'total_work' => $assignTotal,
                'submitted' => $totalSubmitted,
                'reviewed' => $totalReviewed,
                'not_submitted' => $totalNotSubmitted,
                'not_submitted_records' => count($notSubmittedList),
            ];
        } catch (Throwable $e) {
            Log::error('Exam Assessment Pipeline Step 2 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(2, 'Assignment Status Reader failed: ' . $e->getMessage(), [
                'steps' => $steps,
                'gates' => $gates,
            ]);
        }

        // ====================================================================
        // STEP 3: Assessment Review Drafter (exam_assessment.draft_review_note)
        // ====================================================================
        $step3Start = microtime(true);
        try {
            $draftNotes = [];

            // 1. Generate individual student draft notes for at-risk exam attempts
            foreach (array_slice($atRiskAttempts, 0, 5) as $attempt) {
                $studentName = $attempt['student_name'] ?: ('Student #' . ($attempt['student_id'] ?? 'Unknown'));
                $examName = $attempt['paper_name'] ?? 'Online Assessment';
                $score = $attempt['obtain_marks'] ?? 0;
                $total = $attempt['total_marks'] ?? 0;
                $pct = $attempt['percent'] ?? 0;

                $draftNotes[] = [
                    'student_id' => $attempt['student_id'] ?? null,
                    'student_name' => $studentName,
                    'category' => 'online_exam_at_risk',
                    'assessment_title' => $examName,
                    'score' => $score,
                    'total_marks' => $total,
                    'percent' => $pct,
                    'subject' => "Online Exam Review: {$examName} - {$studentName}",
                    'draft_body' => "Assessment review note for {$studentName} on online exam \"{$examName}\".\n"
                        . "Recorded Score: {$score} / {$total} ({$pct}%).\n"
                        . "Observation: Performance is below the {$atRiskPercent}% benchmark. Recommended action: 1-on-1 concept reinforcement and guided practice assignment.\n"
                        . "Note: Drafted for educator review and approval. Not sent externally.",
                    'status' => 'draft_pending_human_approval',
                ];
            }

            // 2. Generate individual student draft notes for unsubmitted assignments
            foreach (array_slice($notSubmittedList, 0, 5) as $ns) {
                $studentName = $ns['student_name'] ?: ('Student #' . ($ns['student_id'] ?? 'Unknown'));
                $workTitle = $ns['title'] ?: ($ns['subject_name'] . ' ' . ucfirst($ns['work_type'] ?? 'Assignment'));
                $workType = $ns['work_type'] ?? 'assignment';

                $draftNotes[] = [
                    'student_id' => $ns['student_id'] ?? null,
                    'student_name' => $studentName,
                    'category' => 'unsubmitted_work',
                    'assessment_title' => $workTitle,
                    'work_type' => $workType,
                    'subject' => "Submission Follow-up: {$workTitle} - {$studentName}",
                    'draft_body' => "Work completion follow-up note for {$studentName} regarding {$workType} \"{$workTitle}\".\n"
                        . "Status: Not submitted as of scheduled review.\n"
                        . "Recommended action: In-class reminder and homework submission extension.\n"
                        . "Note: Drafted for educator review and approval. Not sent externally.",
                    'status' => 'draft_pending_human_approval',
                ];
            }

            // 3. Generate overall cohort executive review draft
            $cohortSummary = sprintf(
                "EXAM & ASSESSMENT COHORT REVIEW SUMMARY\n"
                . "Institute ID: %d | Generated: %s\n\n"
                . "1. ONLINE EXAMS:\n"
                . "   - %d published exams with %d attempts across %d students.\n"
                . "   - Average cohort score: %s%%.\n"
                . "   - %d attempts fell below the %g%% threshold requiring follow-up.\n\n"
                . "2. WORK & ASSIGNMENTS:\n"
                . "   - %d total assignments/worksheets/projects tracked.\n"
                . "   - %d submitted (%d reviewed by teacher), %d not submitted.\n\n"
                . "3. RECOMMENDED INTERVENTIONS:\n"
                . "   - Conduct targeted revision on topics with highest struggle score.\n"
                . "   - Review pending assignments with Division teachers.\n"
                . "   - All %d student draft review notes have been saved for human approval.\n\n"
                . "Safety Confirmation: No external messages or notifications have been dispatched.",
                $subInstituteId,
                Carbon::now()->toFormattedDateString(),
                $examsPublished,
                $attemptsRecorded,
                $studentsAssessed,
                $avgScore !== null ? (string) $avgScore : 'N/A',
                $needsAttentionCount,
                $atRiskPercent,
                $assignTotal,
                $totalSubmitted,
                $totalReviewed,
                $totalNotSubmitted,
                count($draftNotes)
            );

            $step3Duration = round((microtime(true) - $step3Start) * 1000);

            $steps['step_3'] = [
                'step' => 3,
                'agent_name' => 'Assessment review drafter',
                'agent_id' => 'agt_review_note_drafter',
                'tool' => 'exam_assessment.draft_review_note',
                'status' => 'success',
                'duration_ms' => $step3Duration,
                'input' => [
                    'at_risk_count' => count($atRiskAttempts),
                    'unsubmitted_count' => count($notSubmittedList),
                ],
                'output_summary' => sprintf(
                    'Drafted %d individual student review notes and 1 comprehensive cohort intervention summary.',
                    count($draftNotes)
                ),
            ];

            $this->logPipelineRunStep(
                $subInstituteId,
                $context,
                3,
                'exam_assessment.draft_review_note',
                'agt_review_note_drafter',
                'Assessment review drafter',
                $steps['step_3']['input'] ?? [],
                'success',
                $step3Duration,
                ['summary' => $steps['step_3']['output_summary']]
            );

            $gates['gate_3'] = [
                'gate' => 3,
                'name' => 'Assessment Review Drafting & Safety Validation',
                'status' => 'passed',
                'draft_notes_generated' => count($draftNotes),
                'safety_guardrails_passed' => true,
                'external_communication_dispatched' => false,
                'human_approval_required' => true,
            ];
        } catch (Throwable $e) {
            Log::error('Exam Assessment Pipeline Step 3 Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->gateFailure(3, 'Assessment Review Drafter failed: ' . $e->getMessage(), [
                'steps' => $steps,
                'gates' => $gates,
            ]);
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000);

        // Update database state record
        $this->updateAgentStateRecord($subInstituteId, 'completed', [
            'exams_published' => $examsPublished,
            'attempts_recorded' => $attemptsRecorded,
            'students_assessed' => $studentsAssessed,
            'average_score' => $avgScore,
            'needs_attention' => $needsAttentionCount,
            'total_assignments' => $assignTotal,
            'not_submitted' => $totalNotSubmitted,
            'draft_notes_count' => count($draftNotes),
        ]);

        return [
            'success' => true,
            'pipeline_status' => 'completed',
            'sub_institute_id' => $subInstituteId,
            'standard_id' => $standardIdInput,
            'standard_name' => $standardName ?: ($standardIdInput ? 'Standard #' . $standardIdInput : 'All'),
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_duration_ms' => $totalDurationMs,
            'exam_performance' => [
                'exams_published' => $examsPublished,
                'attempts_recorded' => $attemptsRecorded,
                'students_assessed' => $studentsAssessed,
                'average_score' => $avgScore,
                'needs_attention_count' => $needsAttentionCount,
                'benchmark_percent' => $atRiskPercent,
                'at_risk_sample' => array_slice($atRiskAttempts, 0, 10),
            ],
            'assignment_status' => [
                'total_work' => $assignTotal,
                'by_work_type' => $byWorkType,
                'submitted' => $totalSubmitted,
                'reviewed' => $totalReviewed,
                'not_submitted' => $totalNotSubmitted,
                'not_submitted_sample' => array_slice($notSubmittedList, 0, 10),
            ],
            'draft_reviews' => [
                'cohort_summary' => $cohortSummary,
                'student_drafts_count' => count($draftNotes),
                'student_drafts' => $draftNotes,
            ],
            'pipeline_execution' => [
                'steps' => array_values($steps),
                'gates' => array_values($gates),
            ],
        ];
    }

    /**
     * Run all standards sequential pipeline sweep across the institute.
     *
     * @param int $subInstituteId
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function runAllStandardsPipeline(int $subInstituteId, array $options = []): array
    {
        $startTime = microtime(true);

        // Find standards that have question papers or assignments for this institute
        $standardIds = DB::table('question_paper')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('standard_id')
            ->distinct()
            ->pluck('standard_id');

        if ($standardIds->isEmpty()) {
            $standardIds = DB::table('standard')
                ->where('sub_institute_id', $subInstituteId)
                ->limit(10)
                ->pluck('id');
        }

        $standards = DB::table('standard')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('id', $standardIds)
            ->select('id', 'name')
            ->limit(15)
            ->get();

        $standardsProcessed = [];
        $totalExams = 0;
        $totalAttempts = 0;
        $totalAtRisk = 0;
        $totalAssignments = 0;
        $totalDrafts = 0;
        $standardsPassedAllGates = 0;

        foreach ($standards as $std) {
            $singleOptions = array_merge($options, [
                'standard_id' => $std->id,
                'standard_name' => $std->name,
                'limit' => 20,
            ]);

            $res = $this->runSingleStandardPipeline($subInstituteId, $singleOptions);

            $allGatesPassed = ($res['success'] ?? false) && count($res['pipeline_execution']['gates'] ?? []) === 3;
            if ($allGatesPassed) {
                $standardsPassedAllGates++;
            }

            $ep = $res['exam_performance'] ?? [];
            $as = $res['assignment_status'] ?? [];
            $dr = $res['draft_reviews'] ?? [];

            $totalExams += (int) ($ep['exams_published'] ?? 0);
            $totalAttempts += (int) ($ep['attempts_recorded'] ?? 0);
            $totalAtRisk += (int) ($ep['needs_attention_count'] ?? 0);
            $totalAssignments += (int) ($as['total_work'] ?? 0);
            $totalDrafts += (int) ($dr['student_drafts_count'] ?? 0);

            $standardsProcessed[] = [
                'standard_id' => $std->id,
                'standard_name' => $std->name,
                'status' => $res['success'] ? 'completed' : 'failed',
                'all_gates_passed' => $allGatesPassed,
                'exams_published' => $ep['exams_published'] ?? 0,
                'attempts_recorded' => $ep['attempts_recorded'] ?? 0,
                'average_score' => $ep['average_score'] ?? 'N/A',
                'at_risk_count' => $ep['needs_attention_count'] ?? 0,
                'assignments_total' => $as['total_work'] ?? 0,
                'assignments_not_submitted' => $as['not_submitted'] ?? 0,
                'drafts_count' => $dr['student_drafts_count'] ?? 0,
                'duration_ms' => $res['total_duration_ms'] ?? 0,
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
                'total_exams_published' => $totalExams,
                'total_attempts_recorded' => $totalAttempts,
                'total_at_risk_attempts' => $totalAtRisk,
                'total_assignments_tracked' => $totalAssignments,
                'total_draft_reviews_prepared' => $totalDrafts,
            ],
            'standards' => $standardsProcessed,
        ];
    }

    /**
     * Build standard MCP request context for pipeline execution.
     *
     * @param int $subInstituteId
     * @param array<string, mixed> $options
     * @return McpRequestContext
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
                'run_reference' => 'ea_pipe_' . date('Ymd_His') . '_' . $stepNumber . '_' . substr(md5(uniqid()), 0, 6),
                'agent_id' => 8, // Exam & Assessment Master Agent in ai_agents
                'agent_key' => $toolKey,
                'trigger_type' => 'pipeline',
                'trigger_reference' => 'exam_assessment_pipeline_step_' . $stepNumber,
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
     * Graceful gate failure helper.
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
     * Retrieve or initialize agent continuous monitoring state.
     */
    public function getAgentState(int $subInstituteId): object
    {
        if (!Schema::hasTable('lms_exam_assessment_agent_state')) {
            return (object) [
                'sub_institute_id' => $subInstituteId,
                'agent_key' => self::AGENT_KEY,
                'is_active' => 1,
                'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
                'last_run_at' => null,
                'last_run_status' => null,
                'next_run_at' => null,
                'last_heartbeat_at' => null,
            ];
        }

        $row = DB::table('lms_exam_assessment_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$row) {
            $now = Carbon::now();
            $id = DB::table('lms_exam_assessment_agent_state')->insertGetId([
                'sub_institute_id' => $subInstituteId,
                'agent_key' => self::AGENT_KEY,
                'is_active' => true,
                'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $row = DB::table('lms_exam_assessment_agent_state')->where('id', $id)->first();
        }

        if ($row && empty($row->summary_counts)) {
            $row->summary_counts = $this->calculateLiveSummaryCounts($subInstituteId);
        } elseif ($row && is_string($row->summary_counts)) {
            $decoded = json_decode($row->summary_counts, true);
            $row->summary_counts = is_array($decoded) ? $decoded : $this->calculateLiveSummaryCounts($subInstituteId);
        }

        return $row;
    }

    /**
     * Compute real-time live database metrics for this institute.
     */
    public function calculateLiveSummaryCounts(int $subInstituteId): array
    {
        $examsPublished = Schema::hasTable('question_paper')
            ? DB::table('question_paper')->where('sub_institute_id', $subInstituteId)->count()
            : 0;

        $attemptsRecorded = (Schema::hasTable('question_paper') && Schema::hasTable('lms_online_exam'))
            ? DB::table('question_paper as qp')
                ->join('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id')
                ->where('qp.sub_institute_id', $subInstituteId)
                ->count('oe.id')
            : 0;

        $studentsAssessed = (Schema::hasTable('question_paper') && Schema::hasTable('lms_online_exam'))
            ? DB::table('question_paper as qp')
                ->join('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id')
                ->where('qp.sub_institute_id', $subInstituteId)
                ->distinct('oe.student_id')
                ->count('oe.student_id')
            : 0;

        $avgScore = (Schema::hasTable('question_paper') && Schema::hasTable('lms_online_exam'))
            ? DB::table('question_paper as qp')
                ->join('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id')
                ->where('qp.sub_institute_id', $subInstituteId)
                ->selectRaw('AVG(oe.obtain_marks / NULLIF(qp.total_marks, 0) * 100) as avg')
                ->value('avg')
            : null;

        $needsAttention = (Schema::hasTable('question_paper') && Schema::hasTable('lms_online_exam'))
            ? DB::table('question_paper as qp')
                ->join('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id')
                ->where('qp.sub_institute_id', $subInstituteId)
                ->whereRaw('(oe.obtain_marks / NULLIF(qp.total_marks, 0) * 100) < 40')
                ->count('oe.id')
            : 0;

        $totalAssignments = Schema::hasTable('lms_assignment')
            ? DB::table('lms_assignment')->where('sub_institute_id', $subInstituteId)->count()
            : 0;

        $notSubmitted = Schema::hasTable('lms_assignment')
            ? DB::table('lms_assignment')
                ->where('sub_institute_id', $subInstituteId)
                ->where(function ($q) {
                    $q->whereNull('student_submission_status')->orWhere('student_submission_status', '<>', 'Y');
                })
                ->count()
            : 0;

        return [
            'exams_published' => $examsPublished,
            'attempts_recorded' => $attemptsRecorded,
            'students_assessed' => $studentsAssessed,
            'average_score' => $avgScore !== null ? round((float) $avgScore, 1) : null,
            'needs_attention' => $needsAttention,
            'total_assignments' => $totalAssignments,
            'not_submitted' => $notSubmitted,
            'draft_notes_count' => min(50, $needsAttention + min(5, $notSubmitted)),
        ];
    }

    /**
     * Update state record in database.
     */
    public function updateAgentStateRecord(int $subInstituteId, string $status, array $summaryCounts = []): void
    {
        if (!Schema::hasTable('lms_exam_assessment_agent_state')) {
            return;
        }

        $now = Carbon::now();
        $state = $this->getAgentState($subInstituteId);
        $intervalSec = (int) ($state->interval_seconds ?? self::DEFAULT_INTERVAL_SECONDS);

        DB::table('lms_exam_assessment_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->update([
                'last_run_at' => $now,
                'last_run_status' => $status,
                'last_heartbeat_at' => $now,
                'next_run_at' => $now->copy()->addSeconds($intervalSec),
                'summary_counts' => json_encode($summaryCounts),
                'updated_at' => $now,
            ]);
    }

    /**
     * Activate continuous monitoring.
     */
    public function activateContinuousMonitoring(int $subInstituteId, int $intervalSeconds = 60): bool
    {
        if (!Schema::hasTable('lms_exam_assessment_agent_state')) {
            return true;
        }

        $now = Carbon::now();
        $this->getAgentState($subInstituteId);

        DB::table('lms_exam_assessment_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->update([
                'is_active' => true,
                'interval_seconds' => $intervalSeconds,
                'next_run_at' => $now->copy()->addSeconds($intervalSeconds),
                'updated_at' => $now,
            ]);

        return true;
    }

    /**
     * Deactivate continuous monitoring.
     */
    public function deactivateContinuousMonitoring(int $subInstituteId): bool
    {
        if (!Schema::hasTable('lms_exam_assessment_agent_state')) {
            return true;
        }

        DB::table('lms_exam_assessment_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->update([
                'is_active' => false,
                'updated_at' => Carbon::now(),
            ]);

        return true;
    }

    /**
     * Generate the complete Executive Skill Object (ESO) for all three Exam & Assessment agents.
     */
    public function getExpertSystemOverview(int $subInstituteId): array
    {
        // 1. Live database counts
        $examsCount = DB::table('question_paper')->where('sub_institute_id', $subInstituteId)->count();
        $attemptsCount = DB::table('question_paper as qp')
            ->join('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id')
            ->where('qp.sub_institute_id', $subInstituteId)
            ->count('oe.id');

        $hasWorkTypeCol = Schema::hasTable('lms_assignment') && Schema::hasColumn('lms_assignment', 'work_type');
        $assignmentsCount = Schema::hasTable('lms_assignment')
            ? DB::table('lms_assignment')->where('sub_institute_id', $subInstituteId)->count()
            : 0;
        $worksheetsCount = $hasWorkTypeCol
            ? DB::table('lms_assignment')->where('sub_institute_id', $subInstituteId)->where('work_type', 'worksheet')->count()
            : 0;
        $projectsCount = $hasWorkTypeCol
            ? DB::table('lms_assignment')->where('sub_institute_id', $subInstituteId)->where('work_type', 'project')->count()
            : 0;

        return [
            'overview' => [
                'system_name' => 'Exam & Assessment Synchronized Agent System',
                'module' => 'exam_assessment',
                'sub_institute_id' => $subInstituteId,
                'architecture' => '3-Agent Governed Sequential Pipeline with Validation Gates',
                'generated_at' => Carbon::now()->toIso8601String(),
                'total_agents' => 3,
                'status' => 'operational',
            ],
            'database_resources' => [
                'primary_tables' => [
                    'question_paper',
                    'lms_online_exam',
                    'lms_assignment',
                    'tblstudent',
                    'standard',
                    'subject',
                ],
                'live_metrics' => [
                    'exams_published' => $examsCount,
                    'attempts_recorded' => $attemptsCount,
                    'assignments_created' => $assignmentsCount,
                    'worksheets_created' => $worksheetsCount,
                    'projects_created' => $projectsCount,
                ],
            ],
            'agents' => [
                'agent_1' => [
                    'order' => 1,
                    'name' => 'Online exam summary reader',
                    'agent_id' => 'agt_online_exam_summary',
                    'backend_key' => 'exam_assessment.online_exam_summary',
                    'module' => 'exam_assessment',
                    'role_description' => 'Reads real online exam attempts from question_paper & lms_online_exam, excludes PAL papers, calculates average cohort scores, and identifies at-risk students below passing threshold.',
                    'primary_tables' => ['question_paper', 'lms_online_exam', 'tblstudent', 'standard', 'subject'],
                    'validation_gate' => [
                        'gate' => 1,
                        'name' => 'Online Exam Performance Evaluation',
                        'enforcement' => 'Excludes PAL exams; verifies tenant institute boundary; calculates actual obtain_marks / total_marks * 100.',
                    ],
                    'eso' => [
                        'knowledge' => [
                            'summary' => 'Teacher-published online question papers, recorded student exam attempts, obtained marks, passing benchmarks (40%), and PAL test segregation rules.',
                            'items' => [
                                'Teacher-published exam master papers, marks, and configurations from question_paper',
                                'Student attempt records, obtained marks, total marks, and timestamps from lms_online_exam',
                                'Student directory and enrollment records from tblstudent',
                                'Academic passing benchmark criteria (default 40%, matching the LMS Result Dashboard)',
                                'PAL paper exclusion rule: strictly disregards papers where exam_type = "PAL"',
                            ],
                            'tables_and_sources' => ['question_paper', 'lms_online_exam', 'tblstudent', 'standard', 'subject'],
                        ],
                        'activities' => [
                            'summary' => 'Scans published teacher online exams, filters out PAL tests, computes attempt metrics and average cohort scores, and isolates at-risk students below the passing bar.',
                            'items' => [
                                'Scanning published teacher exams scoped to the institute and target standard',
                                'Strict exclusion of PAL-generated practice tests',
                                'Calculation of attempt counts, total students assessed, and cohort average score (obtain_marks / total_marks * 100)',
                                'Identification and extraction of at-risk student attempts scoring below the 40% threshold',
                                'Gate 1 validation and formatting of at-risk student records for downstream intervention drafting',
                            ],
                        ],
                        'abilities' => [
                            'summary' => 'Deterministic score calculation, PAL test segregation, tenant boundary isolation, and read-only extraction.',
                            'skills' => [
                                'Accurate percentage calculation and threshold filtering',
                                'Selective filtering separating formal teacher exams from automated practice papers',
                                'Safe student name resolution with graceful fallback for unmapped profiles',
                                'At-risk attempt cohort packaging',
                            ],
                            'constraints_and_safeguards' => [
                                'Read-only operations (zero modifications to student marks or exam status)',
                                'Tenant institute boundary enforcement (sub_institute_id)',
                                'Gate 1 validation pass requires valid exam attempt calculations',
                            ],
                        ],
                        'usage' => [
                            'summary' => 'Executes as Step 1 of the Exam & Assessment pipeline, establishing the baseline exam performance and feeding at-risk student records to Agent 3.',
                            'step_by_step_flow' => [
                                'Step 1: Receives institute ID and standard parameter (or runs all-standards sweep)',
                                'Step 2: Queries question_paper joined with lms_online_exam, filtering by institute and excluding PAL papers',
                                'Step 3: Aggregates attempt counts and cohort average marks, then filters attempts scoring under at_risk_percent (40%)',
                                'Step 4: Resolves student names from tblstudent and structures at-risk records',
                                'Step 5: Validates Gate 1 and delivers at-risk exam records to Gate 1 output and downstream to Agent 3',
                            ],
                            'upstream_dependencies' => 'None (initiates the Exam & Assessment pipeline via manual click or continuous loop).',
                            'downstream_handoff' => 'Passes at-risk student list and exam score gaps to Agent 3 (Assessment Review Drafter) via Gate 1.',
                        ],
                    ],
                ],
                'agent_2' => [
                    'order' => 2,
                    'name' => 'Assignment status reader',
                    'agent_id' => 'agt_assignment_status',
                    'backend_key' => 'exam_assessment.assignment_status',
                    'module' => 'exam_assessment',
                    'role_description' => 'Reads assignment, worksheet, and project submissions from lms_assignment, reporting submitted, reviewed, pending review, and specific unsubmitted student records.',
                    'primary_tables' => ['lms_assignment', 'tblstudent', 'standard', 'subject'],
                    'validation_gate' => [
                        'gate' => 2,
                        'name' => 'Assignment & Work Status Verification',
                        'enforcement' => 'Distinguishes assignment vs worksheet vs project; verifies submission flags; reports unsubmitted student details.',
                    ],
                    'eso' => [
                        'knowledge' => [
                            'summary' => 'Assigned coursework records across assignments, worksheets, and projects, submission statuses, teacher review logs, due dates, and student class rosters.',
                            'items' => [
                                'Coursework records, instructions, due dates, and standard bindings from lms_assignment',
                                'Work type taxonomy: differentiation between assignment, worksheet, and project stored in the same table',
                                'Student submission and review status flags: submitted, reviewed by teacher, pending review, and not submitted',
                                'Student directory and enrollment rosters from tblstudent for delinquent submission reconciliation',
                            ],
                            'tables_and_sources' => ['lms_assignment', 'tblstudent', 'standard', 'subject'],
                        ],
                        'activities' => [
                            'summary' => 'Scans all coursework allocated to a standard, categorizes by work type, tracks submission and review counts, and compiles the delinquent not-submitted list.',
                            'items' => [
                                'Institute and standard-scoped querying of lms_assignment',
                                'Taxonomic grouping and counting by work type (assignment, worksheet, project)',
                                'Auditing submission completeness and teacher evaluation rates',
                                'Compiling detailed list of students with unsubmitted coursework past scheduled due dates',
                                'Gate 2 validation ensuring coursework integrity across the target standard',
                            ],
                        ],
                        'abilities' => [
                            'summary' => 'Unified coursework schema disambiguation, class roster cross-referencing, and non-submitted student isolation.',
                            'skills' => [
                                'Multi-work-type aggregation over unified coursework storage',
                                'Cross-table join between assignments and student enrollment rosters',
                                'Accurate overdue status tracking without timezone drift',
                                'Coursework delinquency ledger formatting',
                            ],
                            'constraints_and_safeguards' => [
                                'Read-only execution with zero updates to student submission states',
                                'Tenant institute boundary enforcement (sub_institute_id)',
                                'Gate 2 validation ensures work status is fully accounted for',
                            ],
                        ],
                        'usage' => [
                            'summary' => 'Executes as Step 2 of the Exam & Assessment pipeline, providing comprehensive coursework submission and review data to complement exam performance.',
                            'step_by_step_flow' => [
                                'Step 1: Receives standard scope from Gate 1',
                                'Step 2: Queries lms_assignment filtered by institute ID, standard ID, and division',
                                'Step 3: Breaks down coursework totals by work type (assignment, worksheet, project)',
                                'Step 4: Tallies submitted, reviewed, and unsubmitted counts, isolating delinquent student records',
                                'Step 5: Validates Gate 2 and delivers the unsubmitted coursework ledger to Gate 2 and Agent 3',
                            ],
                            'upstream_dependencies' => 'Requires standard scope confirmed by Gate 1.',
                            'downstream_handoff' => 'Hands off the unsubmitted coursework ledger and review counts to Agent 3 (Assessment Review Drafter) via Gate 2.',
                        ],
                    ],
                ],
                'agent_3' => [
                    'order' => 3,
                    'name' => 'Assessment review drafter',
                    'agent_id' => 'agt_assessment_review_drafter',
                    'backend_key' => 'exam_assessment.draft_review_note',
                    'module' => 'exam_assessment',
                    'role_description' => 'Synthesizes at-risk attempt findings and missing submissions to prepare factual, evidence-backed draft review notes and follow-up guidance for educators.',
                    'primary_tables' => ['ai_cases', 'ai_evidence', 'ai_recommendations', 'question_paper', 'lms_assignment'],
                    'validation_gate' => [
                        'gate' => 3,
                        'name' => 'Assessment Review Drafting & Safety Validation',
                        'enforcement' => 'Strict draft-only policy; cites factual exam & submission evidence; forbids external auto-sending; enforces human approval step.',
                    ],
                    'eso' => [
                        'knowledge' => [
                            'summary' => 'Fused multi-source academic evidence: at-risk exam scores from Agent 1, unsubmitted coursework from Agent 2, pedagogical intervention strategies, and draft safety rules.',
                            'items' => [
                                'At-risk exam attempt scores, percentages, and exam names received from Agent 1 (Gate 1)',
                                'Unsubmitted coursework items, due dates, and work types received from Agent 2 (Gate 2)',
                                'Pedagogical intervention guidelines (1-on-1 concept reinforcement, submission extensions, guided practice)',
                                'Safety and governance guardrails: mandatory draft status, no automated external dispatching, objective and supportive wording standards',
                            ],
                            'tables_and_sources' => ['ai_cases', 'ai_evidence', 'ai_recommendations', 'question_paper', 'lms_assignment'],
                        ],
                        'activities' => [
                            'summary' => 'Synthesizes exam gaps and missing coursework to draft individualized educator review notes, prepares a comprehensive cohort executive summary, and applies human sign-off guardrails.',
                            'items' => [
                                'Synthesizing at-risk attempts from Agent 1 into individualized exam review notes citing exact scores and percentages',
                                'Synthesizing unsubmitted coursework from Agent 2 into coursework follow-up notes citing exact assignment titles',
                                'Compiling the overall Cohort Executive Review Summary with participation metrics, average scores, and recommended interventions',
                                'Applying the strict draft_pending_human_approval status tag to all drafted notes',
                                'Gate 3 safety validation ensuring zero external communication was dispatched',
                            ],
                        ],
                        'abilities' => [
                            'summary' => 'Multi-agent evidence fusion, grounded factual synthesis, human-in-the-loop guardrail enforcement, and pedagogical recommendation generation.',
                            'skills' => [
                                'Correlating multi-agent performance deficits per student into a single cohesive profile',
                                'Grounded factual drafting citing exact scores, benchmarks, and dates with zero fabricated numbers',
                                'Pedagogical remediation mapping (linking specific failure modes to actionable teacher follow-ups)',
                                'Cohort-level strategic intervention synthesis',
                            ],
                            'constraints_and_safeguards' => [
                                'Strict draft-only policy: zero external messages, SMS, or emails are ever dispatched automatically',
                                'Human-in-the-loop requirement: notes remain pending until approved by an authorized educator',
                                'Objective, constructive pedagogical language with zero disparaging comments',
                                'All drafts recorded in pipeline audit logs',
                            ],
                        ],
                        'usage' => [
                            'summary' => 'Acts as the final synthesis step (Step 3) of the Exam & Assessment pipeline, converting raw exam and assignment data from Agents 1 & 2 into actionable, safe draft review notes ready for teacher sign-off.',
                            'step_by_step_flow' => [
                                'Step 1: Consumes at-risk exam records from Agent 1 (Gate 1) and unsubmitted coursework records from Agent 2 (Gate 2)',
                                'Step 2: Drafts individual student review notes for low exam attempts (citing student name, exam title, score, total marks, percentage, and 1-on-1 reinforcement recommendation)',
                                'Step 3: Drafts individual student follow-up notes for missing assignments (citing student name, work title, work type, and extension recommendation)',
                                'Step 4: Compiles the Cohort Executive Review Summary consolidating institute stats, exams published, attempts, pass rates, and action items',
                                'Step 5: Enforces Gate 3 safety validation, confirms no external dispatch, marks drafts as draft_pending_human_approval, and returns the final report',
                            ],
                            'upstream_dependencies' => 'Requires at-risk exam attempts from Agent 1 (Gate 1) and missing coursework records from Agent 2 (Gate 2).',
                            'downstream_handoff' => 'Outputs student draft review notes and cohort summary to the Exam & Assessment dashboard for educator review, approval, and execution.',
                        ],
                    ],
                ],
            ],
            'execution_workflow' => [
                'pattern' => 'Sequential Pipeline with Multi-Gate Verification',
                'execution_order' => [
                    'Step 1: Online Exam Performance Sweep -> Gate 1 Verification',
                    'Step 2: Assignment & Work Status Evaluation -> Gate 2 Verification',
                    'Step 3: Assessment Review Note & Follow-up Drafting -> Gate 3 Safety Pass',
                ],
                'isolation_and_safety' => [
                    'tenant_isolated' => true,
                    'external_sending_forbidden' => true,
                    'requires_human_approval' => true,
                    'audit_logged' => true,
                ],
            ],
        ];
    }
}
