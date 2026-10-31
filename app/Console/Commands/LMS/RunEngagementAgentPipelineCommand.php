<?php

namespace App\Console\Commands\LMS;

use App\Services\Lms\EngagementAgentPipelineService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Artisan command to execute the 3-agent synchronized Engagement pipeline.
 *
 * Sequence:
 *   1. Engagement Attention Reader (engagement.students_needing_attention) -> Gate 1
 *   2. Engagement Activity Profiler (engagement.student_summary / profileEngagement) -> Gate 2
 *   3. Re-Engagement Intervention Drafter (engagement.draft_intervention_note) -> Gate 3
 *   Final: Unified Student Engagement & Intervention Report
 */
class RunEngagementAgentPipelineCommand extends Command
{
    protected $signature = 'lms:run-engagement-pipeline
                            {--institute=1 : Sub-institute ID}
                            {--standard= : Target standard ID or standard name (e.g. 39 or "all")}
                            {--threshold=60 : Engagement threshold percentage}
                            {--days-back=30 : Days window to scan}
                            {--limit=50 : Max records to retrieve}';

    protected $description = 'Executes the synchronized 3-agent Engagement pipeline and outputs the unified report';

    public function __construct(
        private readonly EngagementAgentPipelineService $pipelineService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $instituteId = (int) ($this->option('institute') ?: 1);
        $standardOption = $this->option('standard');
        $threshold = (float) ($this->option('threshold') ?: 60.0);
        $daysBack = (int) ($this->option('days-back') ?: 30);
        $limit = (int) ($this->option('limit') ?: 50);

        $this->info('========================================================');
        $this->info('      ENGAGEMENT SYNCHRONIZED 3-AGENT PIPELINE          ');
        $this->info('========================================================');
        $this->line("Institute ID:          {$instituteId}");
        $this->line('Standard:              ' . ($standardOption ?: 'All standards'));
        $this->line("Engagement Threshold:  {$threshold}%");
        $this->line("Window Days:           {$daysBack} days");
        $this->newLine();

        $this->info('Starting synchronized sequential 3-agent workflow...');

        try {
            $options = [
                'threshold_percent' => $threshold,
                'days_back' => $daysBack,
                'limit' => $limit,
            ];

            if ($standardOption) {
                if (is_numeric($standardOption)) {
                    $options['standard_id'] = (int) $standardOption;
                } else {
                    $options['standard_name'] = $standardOption;
                }
            }

            $report = $this->pipelineService->runPipeline($instituteId, $options);

            if (($report['status'] ?? '') === 'failed') {
                $this->error("Pipeline Execution Failed at Gate {$report['failed_gate']}: {$report['reason']}");
                return self::FAILURE;
            }

            $this->newLine();
            $this->info('=== EXECUTION TIMELINE (3-AGENT PIPELINE) ===');
            $steps = $report['pipeline_execution']['steps'] ?? [];
            foreach ($steps as $step) {
                $sNum = $step['step'];
                $sName = $step['agent_name'];
                $sStatus = strtoupper($step['status']);
                $sDur = $step['duration_ms'];
                $sSummary = $step['output_summary'];

                $this->line("[Step {$sNum}] {$sName} | Status: {$sStatus} | Duration: {$sDur}ms");
                $this->line("         Summary: {$sSummary}");
            }

            $this->newLine();
            $this->info('=== VALIDATION GATES ===');
            $gates = $report['validation_gates'] ?? [];
            foreach ($gates as $gate) {
                $gNum = $gate['gate'];
                $gName = $gate['name'];
                $gStatus = strtoupper($gate['status']);
                $this->line("[Gate {$gNum}] {$gName}: {$gStatus}");
            }

            $this->newLine();
            $this->info('=== SUMMARY METRICS ===');
            $metrics = $report['summary_metrics'] ?? [];
            $this->line("Flagged Students:         " . ($metrics['flagged_students'] ?? 0));
            $this->line("Critical Attention Count: " . ($metrics['critical_attention_count'] ?? 0));
            $this->line("Draft Interventions:      " . ($metrics['draft_interventions'] ?? 0));
            $this->line("Total Duration:           {$report['duration_ms']}ms");

            $this->newLine();
            $this->info('Pipeline run completed successfully.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Pipeline Command Exception: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}

