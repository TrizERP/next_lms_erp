<?php

namespace App\Console\Commands\LMS;

use App\Services\Lms\TeachLearnAgentPipelineService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Artisan command to execute the 3-agent synchronized Teach/Learn pipeline.
 *
 * Sequence:
 *   1. Course Catalogue Agent (Standard -> Subject -> Chapter) -> Gate 1
 *   2. Activity Timeline Agent (Sessions & Homework) -> Gate 2
 *   3. Content Model Coverage Agent (4-Type Model & Learning Gaps) -> Gate 3
 *   Final: Unified Teach/Learn Synchronization Report
 */
class RunTeachLearnAgentPipelineCommand extends Command
{
    protected $signature = 'lms:run-agent-pipeline
                            {--standard=7 : Standard or grade number to audit}
                            {--institute=1 : Sub-institute ID}
                            {--days=15 : Days window back and ahead for activities}
                            {--limit=10 : Max activity items to retrieve}
                            {--content-limit=50 : Max chapters for content coverage}';

    protected $description = 'Executes the synchronized 3-agent Teach/Learn pipeline and outputs the unified report';

    public function __construct(
        private readonly TeachLearnAgentPipelineService $pipelineService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $standard = (string) $this->option('standard');
        $instituteId = (int) $this->option('institute');
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $contentLimit = (int) $this->option('content-limit');

        $this->info("========================================================");
        $this->info("  TEACH/LEARN SYNCHRONIZED 3-AGENT PIPELINE EXECUTION   ");
        $this->info("========================================================");
        $this->line("Target Standard:   {$standard}");
        $this->line("Institute ID:      {$instituteId}");
        $this->line("Activity Window:   +/- {$days} days");
        $this->newLine();

        $this->info("Starting synchronized sequential workflow...");

        try {
            $report = $this->pipelineService->runPipeline($instituteId, [
                'standard_name' => $standard,
                'days_back' => $days,
                'days_ahead' => $days,
                'limit' => $limit,
                'content_limit' => $contentLimit,
            ]);

            if (!($report['success'] ?? false)) {
                $this->error("PIPELINE FAILED at Gate " . ($report['failed_at_gate'] ?? '?'));
                $this->error($report['error'] ?? 'Unknown failure');
                return self::FAILURE;
            }

            if (($report['mode'] ?? '') === 'all_standards_sweep') {
                $this->newLine();
                $this->info("=== ALL-STANDARDS SEQUENTIAL PIPELINE SWEEP ===");
                $table = [];
                foreach ($report['standards'] as $s) {
                    $table[] = [
                        $s['standard_name'] . ' (ID: ' . $s['standard_id'] . ')',
                        $s['curriculum']['subjects_count'] . ' subs, ' . $s['curriculum']['chapters_count'] . ' ch',
                        $s['activity_timeline']['activities_count'] . ' acts (' . $s['activity_timeline']['sample_activity'] . ')',
                        $s['content_readiness']['analyzed'] . ' ch (' . $s['content_readiness']['fully_covered'] . ' full, ' . $s['content_readiness']['partially_covered'] . ' part)',
                        $s['gates']['gate_1_curriculum'],
                        $s['gates']['gate_2_timeline'],
                        $s['gates']['gate_3_content_model'],
                        $s['duration_ms'] . ' ms',
                    ];
                }
                $this->table(['Standard', 'Curriculum (Gate 1)', 'Timeline (Gate 2)', 'Content Model (Gate 3)', 'G1', 'G2', 'G3', 'Duration'], $table);

                $this->newLine();
                $this->info("=== INSTITUTE SWEEP SUMMARY ===");
                $this->line("Standards Scanned:        " . $report['summary']['total_standards_scanned']);
                $this->line("Standards Passed 100%:    " . $report['summary']['standards_passed_all_gates']);
                $this->line("Total Chapters Audited:   " . $report['summary']['total_chapters_configured']);
                $this->line("Total Activities Scoped:  " . $report['summary']['total_activities_found']);
                $this->line("Total Sweep Duration:     " . $report['total_duration_ms'] . " ms");
                $this->newLine();
                $this->info("All standards executed standard-wise through each sequential step successfully.");
                return self::SUCCESS;
            }

            // Display Step Execution Summary
            $this->newLine();
            $this->info("=== PIPELINE EXECUTION STEPS ===");
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
            $this->info("=== VALIDATION GATES ===");
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
            $this->info("=== UNIFIED TEACH/LEARN REPORT HIGHLIGHTS ===");
            $this->line("Standard: " . $report['standard']['name'] . " (ID: " . $report['standard']['id'] . ")");
            $this->line("Curriculum Subjects: " . $report['standard']['total_subjects'] . ", Chapters: " . $report['standard']['total_chapters']);
            $this->line("Activities on Timeline: " . $report['activity_timeline']['total_activities']);
            $this->line("Content Coverage Analyzed: " . $report['content_readiness']['total_chapters_analyzed'] . " chapters");
            $this->line("Total Pipeline Duration: " . $report['total_duration_ms'] . " ms");

            if (!empty($report['activity_timeline']['activities'])) {
                $this->newLine();
                $this->line("--- Active Timeline Items ---");
                foreach ($report['activity_timeline']['activities'] as $act) {
                    $this->line("  * [{$act['kind']}] {$act['title']} | {$act['subject']} | {$act['date']} ({$act['status']})");
                }
            }

            $missing = $report['content_readiness']['missing_components_summary'] ?? [];
            if (!empty($missing)) {
                $this->newLine();
                $this->line("--- Content Readiness Gaps ---");
                foreach ($missing as $k => $v) {
                    $this->line("  * {$k}: {$v} missing");
                }
            }

            $this->newLine();
            $this->info("Complete 3-agent pipeline synchronized and verified successfully.");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Pipeline exception: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}

