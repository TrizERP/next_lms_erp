<?php

namespace App\Console\Commands\LMS;

use App\Services\Lms\ExamAssessmentAgentPipelineService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Artisan command to execute the 3-agent synchronized Exam & Assessment pipeline.
 *
 * Sequence:
 *   1. Online Exam Summary Reader (exam_assessment.online_exam_summary) -> Gate 1
 *   2. Assignment Status Reader (exam_assessment.assignment_status) -> Gate 2
 *   3. Assessment Review Drafter (exam_assessment.draft_review_note) -> Gate 3
 *   Final: Unified Exam & Assessment Performance & Intervention Report
 */
class RunExamAssessmentAgentPipelineCommand extends Command
{
    protected $signature = 'lms:run-exam-assessment-pipeline
                            {--institute=195 : Sub-institute ID}
                            {--standard= : Target standard ID or standard name (e.g. 2231 or "all")}
                            {--subject= : Optional target subject ID}
                            {--student= : Optional target student ID}
                            {--at-risk=40 : At-risk percentage threshold}
                            {--limit=50 : Max records to retrieve}';

    protected $description = 'Executes the synchronized 3-agent Exam & Assessment pipeline and outputs the unified report';

    public function __construct(
        private readonly ExamAssessmentAgentPipelineService $pipelineService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $instituteId = (int) ($this->option('institute') ?: 195);
        $standardOption = $this->option('standard');
        $subjectId = $this->option('subject') ? (int) $this->option('subject') : null;
        $studentId = $this->option('student') ? (int) $this->option('student') : null;
        $atRisk = (float) ($this->option('at-risk') ?: 40.0);
        $limit = (int) ($this->option('limit') ?: 50);

        $this->info('========================================================');
        $this->info('  EXAM & ASSESSMENT SYNCHRONIZED 3-AGENT PIPELINE       ');
        $this->info('========================================================');
        $this->line("Institute ID:      {$instituteId}");
        $this->line('Standard:          ' . ($standardOption ?: 'All standards'));
        if ($subjectId) {
            $this->line("Subject ID:        {$subjectId}");
        }
        if ($studentId) {
            $this->line("Student ID:        {$studentId}");
        }
        $this->line("At-Risk Threshold: {$atRisk}%");
        $this->newLine();

        $this->info('Starting synchronized sequential 3-agent workflow...');

        try {
            $options = [
                'at_risk_percent' => $atRisk,
                'limit' => $limit,
            ];

            if ($standardOption) {
                if (is_numeric($standardOption)) {
                    $options['standard_id'] = (int) $standardOption;
                } else {
                    $options['standard_name'] = $standardOption;
                }
            } else {
                $options['standard_name'] = 'all';
            }

            if ($subjectId) {
                $options['subject_id'] = $subjectId;
            }
            if ($studentId) {
                $options['student_id'] = $studentId;
            }

            $report = $this->pipelineService->runPipeline($instituteId, $options);

            if (!($report['success'] ?? false)) {
                $this->error('PIPELINE FAILED at Gate ' . ($report['failed_at_gate'] ?? '?'));
                $this->error($report['error'] ?? 'Unknown failure');
                return self::FAILURE;
            }

            if (($report['mode'] ?? '') === 'all_standards_sweep') {
                $this->newLine();
                $this->info('=== ALL-STANDARDS SEQUENTIAL PIPELINE SWEEP ===');
                $table = [];
                foreach ($report['standards'] as $s) {
                    $table[] = [
                        $s['standard_name'] . ' (ID: ' . $s['standard_id'] . ')',
                        $s['exams_published'] . ' exams, ' . $s['attempts_recorded'] . ' attempts',
                        $s['at_risk_count'] . ' at-risk',
                        $s['assignments_total'] . ' work, ' . $s['assignments_not_submitted'] . ' unsubmitted',
                        $s['drafts_count'] . ' drafts',
                        $s['all_gates_passed'] ? 'PASSED' : 'PARTIAL',
                        $s['duration_ms'] . ' ms',
                    ];
                }
                $this->table(['Standard', 'Exams (Gate 1)', 'At-Risk', 'Assignments (Gate 2)', 'Drafts (Gate 3)', 'All Gates', 'Duration'], $table);

                $this->newLine();
                $this->info('=== INSTITUTE ASSESSMENT SWEEP SUMMARY ===');
                $this->line('Standards Scanned:            ' . ($report['summary']['total_standards_scanned'] ?? 0));
                $this->line('Standards Passed All Gates:   ' . ($report['summary']['standards_passed_all_gates'] ?? 0));
                $this->line('Total Exams Published:        ' . ($report['summary']['total_exams_published'] ?? 0));
                $this->line('Total Exam Attempts:          ' . ($report['summary']['total_attempts_recorded'] ?? 0));
                $this->line('Total At-Risk Attempts:       ' . ($report['summary']['total_at_risk_attempts'] ?? 0));
                $this->line('Total Assignments Tracked:    ' . ($report['summary']['total_assignments_tracked'] ?? 0));
                $this->line('Total Review Drafts Prepared: ' . ($report['summary']['total_draft_reviews_prepared'] ?? 0));
                $this->line('Total Sweep Duration:         ' . $report['total_duration_ms'] . ' ms');
                $this->newLine();
                $this->info('Institute assessment sweep completed successfully.');
                return self::SUCCESS;
            }

            // Display Single-Standard Step Execution Summary
            $this->newLine();
            $this->info('=== PIPELINE EXECUTION STEPS ===');
            $stepsTable = [];
            foreach ($report['pipeline_execution']['steps'] as $s) {
                $stepsTable[] = [
                    $s['step'],
                    $s['agent_name'],
                    $s['tool'],
                    $s['status'],
                    $s['duration_ms'] . ' ms',
                    $s['output_summary'],
                ];
            }
            $this->table(['Step', 'Agent', 'Tool', 'Status', 'Duration', 'Summary'], $stepsTable);

            // Display Validation Gates
            $this->newLine();
            $this->info('=== VALIDATION GATES ===');
            $gatesTable = [];
            foreach ($report['pipeline_execution']['gates'] as $g) {
                $gatesTable[] = [
                    'Gate ' . $g['gate'],
                    $g['name'],
                    strtoupper($g['status']),
                ];
            }
            $this->table(['Gate', 'Name', 'Status'], $gatesTable);

            // Display Unified Report Highlights
            $this->newLine();
            $this->info('=== UNIFIED EXAM & ASSESSMENT REPORT HIGHLIGHTS ===');
            $this->line('Standard:                     ' . ($report['standard_name'] ?? 'All') . ' (ID: ' . ($report['standard_id'] ?? 'All') . ')');
            $this->line('Online Exams Published:       ' . ($report['exam_performance']['exams_published'] ?? 0));
            $this->line('Total Exam Attempts Recorded: ' . ($report['exam_performance']['attempts_recorded'] ?? 0));
            $this->line('Average Score Across Attempts:' . ($report['exam_performance']['average_score'] ?? 'N/A') . '%');
            $this->line('At-Risk Attempts (<' . $atRisk . '%):    ' . ($report['exam_performance']['needs_attention_count'] ?? 0));
            $this->line('Total Assignments & Work:     ' . ($report['assignment_status']['total_work'] ?? 0));
            $this->line('Total Work Submissions:       ' . ($report['assignment_status']['submitted'] ?? 0));
            $this->line('Work Reviewed / Evaluated:    ' . ($report['assignment_status']['reviewed'] ?? 0));
            $this->line('Draft Review Notes Generated: ' . ($report['draft_reviews']['student_drafts_count'] ?? 0));
            $this->line('Total Pipeline Duration:      ' . $report['total_duration_ms'] . ' ms');

            if (!empty($report['exam_performance']['at_risk_sample'])) {
                $this->newLine();
                $this->line('--- At-Risk Student Exam Attempts Sample ---');
                foreach (array_slice($report['exam_performance']['at_risk_sample'], 0, 5) as $student) {
                    $stdName = $student['student_name'] ?? ('Student #' . ($student['student_id'] ?? 'N/A'));
                    $paperId = $student['paper_id'] ?? ($student['exam_id'] ?? 'N/A');
                    $marks = ($student['obtained_marks'] ?? '?') . '/' . ($student['total_marks'] ?? '?');
                    $pct = $student['percentage'] ?? ($student['score_percent'] ?? '?');
                    $this->line("  * {$stdName} - Paper #{$paperId} Score: {$marks} ({$pct}%)");
                }
            }

            if (!empty($report['draft_reviews']['student_drafts'])) {
                $this->newLine();
                $this->line('--- Draft Review Notes Sample (Safely Generated, Pending Sign-off) ---');
                foreach (array_slice($report['draft_reviews']['student_drafts'], 0, 3) as $draft) {
                    $type = $draft['context_type'] ?? $draft['type'] ?? 'review';
                    $this->line("  * [{$type}] " . ($draft['subject'] ?? 'Untitled Draft'));
                    $this->line('    Target: ' . ($draft['student_name'] ?? 'Student') . ' | Status: ' . ($draft['status'] ?? 'draft'));
                    $this->line('    Preview: ' . substr(str_replace("\n", ' ', $draft['draft_body'] ?? ''), 0, 110) . '...');
                }
            }

            $this->newLine();
            $this->info('Exam & Assessment pipeline completed successfully.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('PIPELINE EXCEPTION: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
