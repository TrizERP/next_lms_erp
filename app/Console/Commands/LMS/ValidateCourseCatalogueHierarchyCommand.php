<?php

namespace App\Console\Commands\LMS;

use App\Services\lms\CourseCatalogueHierarchyValidator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Console command for the Course Catalogue Reader Agent.
 *
 * Continuously validates the complete 5-tier Teach/Learn content hierarchy:
 *   Standard -> Subject -> Chapter -> Topic -> Intelligence
 *
 * Runs either as a one-shot scheduled execution (e.g. from Platform Scheduler or cron)
 * or as a continuous background worker daemon (--continuous).
 */
class ValidateCourseCatalogueHierarchyCommand extends Command
{
    protected $signature = 'lms:validate-catalogue-hierarchy
                            {--institute=1 : Target institute ID (sub_institute_id)}
                            {--force : Force revalidation even if data fingerprint is unchanged}
                            {--start : Start continuous background validation loop (persisted across restarts)}
                            {--stop : Stop continuous background validation loop}
                            {--continuous : Run continuous monitoring loop in foreground/worker}
                            {--interval=60 : Seconds between cycles (default: 60s)}
                            {--status : Show latest validation status, background loop state, and metrics}
                            {--show-issues : Display detailed breakdown of detected issues}';

    protected $description = 'Course Catalogue Reader Agent: Continuously validate the 5-tier Teach/Learn content hierarchy';

    protected bool $shouldKeepRunning = true;

    public function handle(CourseCatalogueHierarchyValidator $validator): int
    {
        $instituteId = (int) $this->option('institute');
        if ($instituteId <= 0) {
            $this->error('Invalid --institute ID specified.');
            return self::FAILURE;
        }

        // Stop continuous loop if requested
        if ($this->option('stop')) {
            $res = $validator->deactivateContinuousMonitoring($instituteId);
            $this->warn("================================================================================");
            $this->warn("Course Catalogue Reader Agent: " . $res['message']);
            $this->warn("================================================================================");
            return self::SUCCESS;
        }

        // Just display latest status if requested
        if ($this->option('status')) {
            return $this->displayLatestStatus($validator, $instituteId);
        }

        $force = (bool) $this->option('force');
        $startOnly = (bool) $this->option('start');
        $continuous = (bool) $this->option('continuous');
        $interval = max(5, (int) $this->option('interval'));

        // If --start was requested, activate state and run initial pass
        if ($startOnly) {
            $this->info("================================================================================");
            $this->info("Course Catalogue Reader Agent - Activating Continuous Background Monitoring");
            $this->info("Institute: {$instituteId} | Interval: {$interval}s | Auto-restart: YES");
            $this->info("================================================================================");

            $activation = $validator->activateContinuousMonitoring($instituteId, $interval);
            $this->info("[SUCCESS] {$activation['message']}");
            $this->info("The agent will continue running automatically in the background even if the browser is closed.");
            $this->info("Next cycle scheduled at: {$activation['next_run_at']}");

            return $this->displayReportSummary($activation['initial_report'] ?? []);
        }

        // By default, ensure continuous monitoring state is active so scheduler continues it
        $validator->activateContinuousMonitoring($instituteId, $interval);

        $this->info("================================================================================");
        $this->info("Course Catalogue Reader Agent - 5-Tier Content Hierarchy Validation");
        $this->info("Institute: {$instituteId} | Mode: " . ($continuous ? "Continuous Loop (every {$interval}s)" : "Started (Continuous Background Enabled)") . " | Force: " . ($force ? "Yes" : "No"));
        $this->info("Hierarchy: Standard -> Subject -> Chapter -> Topic -> Intelligence");
        $this->info("================================================================================");

        if ($continuous) {
            $this->setupSignalHandlers();
            $pid = function_exists('getmypid') ? getmypid() : null;
            $validator->heartbeat($instituteId, $pid);

            $this->info("Continuous background loop active (PID: " . ($pid ?? 'N/A') . "). Press Ctrl+C to terminate.\n");

            while ($this->shouldKeepRunning) {
                $validator->heartbeat($instituteId, $pid);
                $this->runPass($validator, $instituteId, $force, $interval);

                if (!$this->shouldKeepRunning) {
                    break;
                }

                $this->comment("Next cycle in {$interval}s. Continuous monitoring active (survives app/browser close)...\n");
                $sleepUntil = time() + $interval;
                while (time() < $sleepUntil && $this->shouldKeepRunning) {
                    sleep(1);
                    if (function_exists('pcntl_signal_dispatch')) {
                        pcntl_signal_dispatch();
                    }
                }

                // Subsequent passes check data fingerprint unless forced
                $force = false;
            }

            $this->info("Continuous validation worker terminated gracefully.");
            return self::SUCCESS;
        }

        return $this->runPass($validator, $instituteId, $force, $interval);
    }

    /**
     * Run a single validation cycle and display summary.
     */
    protected function runPass(CourseCatalogueHierarchyValidator $validator, int $instituteId, bool $force, int $interval = 60): int
    {
        $this->line("[" . Carbon::now()->toDateTimeString() . "] Running hierarchy validation for Institute {$instituteId}...");

        $report = $validator->validate($instituteId, $force, $interval);

        if ($report['status'] === 'locked') {
            $this->warn("Task locked: {$report['message']}");
            return self::SUCCESS;
        }

        return $this->displayReportSummary($report);
    }

    /**
     * Display structured summary table for a report.
     */
    protected function displayReportSummary(array $report): int
    {
        $counts = $report['counts'] ?? [];

        $this->info("\n--- Course Catalogue Reader: 5-Tier Hierarchy Summary ---");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Agent Key', $report['agent_key'] ?? CourseCatalogueHierarchyValidator::AGENT_KEY],
                ['Status', strtoupper($report['status'] ?? 'UNKNOWN')],
                ['Data Hash', substr($report['data_hash'] ?? 'N/A', 0, 16) . '...'],
                ['Execution Duration', ($report['duration_ms'] ?? 0) . ' ms'],
                ['Last Execution Time', $report['last_run_time'] ?? 'N/A'],
                ['Next Scheduled Execution', $report['next_scheduled_run'] ?? 'N/A'],
                ['Standards Checked', $counts['standards_checked'] ?? 0],
                ['Subjects Checked', $counts['subjects_checked'] ?? 0],
                ['Chapters Checked', $counts['chapters_checked'] ?? 0],
                ['Topics Checked', $counts['topics_checked'] ?? 0],
                ['Intelligence Configs Checked', $counts['intelligence_configs_checked'] ?? 0],
                ['Complete Hierarchies Validated', $counts['complete_hierarchies_validated'] ?? 0],
                ['Missing Subjects', $counts['missing_subjects_count'] ?? 0],
                ['Missing Chapters', $counts['missing_chapters_count'] ?? 0],
                ['Missing Topics', $counts['missing_topics_count'] ?? 0],
                ['Missing Intelligence Configs', $counts['missing_intelligence_count'] ?? 0],
                ['Broken / Orphaned Mappings', $counts['broken_mappings_count'] ?? 0],
                ['Total Issues Detected', $counts['total_issues_detected'] ?? 0],
                ['Newly Discovered Issues', count($report['newly_discovered_issues'] ?? [])],
                ['Resolved Issues', count($report['resolved_issues'] ?? [])],
            ]
        );

        if (!empty($report['message'])) {
            $this->comment("Note: {$report['message']}");
        }

        if ($this->option('show-issues') && !empty($report['issues'])) {
            $this->displayIssuesBreakdown($report['issues']);
        }

        return self::SUCCESS;
    }

    /**
     * Display the latest stored status including continuous monitoring state.
     */
    protected function displayLatestStatus(CourseCatalogueHierarchyValidator $validator, int $instituteId): int
    {
        $latest = $validator->getLatestRun($instituteId);
        $agentState = $validator->getAgentState($instituteId);

        $this->info("================================================================================");
        $this->info("Course Catalogue Reader Agent - Continuous Monitoring Status (Institute {$instituteId})");
        $this->info("================================================================================");

        if ($agentState) {
            $this->table(
                ['Continuous Background Monitor', 'State'],
                [
                    ['Continuous Loop Active', $agentState['is_active'] ? '<info>YES (ACTIVE)</info>' : '<comment>NO (STOPPED)</comment>'],
                    ['Configured Interval', $agentState['interval_seconds'] . ' seconds'],
                    ['Auto-Restart on System Boot', '<info>ENABLED (Automatic Scheduler Recovery)</info>'],
                    ['Background Process PID', $agentState['daemon_pid'] ?? 'Scheduler/Daemon Managed'],
                    ['Last Heartbeat', $agentState['last_heartbeat_at'] ?? 'N/A'],
                    ['Next Scheduled Cycle', $agentState['next_run_at'] ?? 'N/A'],
                ]
            );
        }

        if (!$latest) {
            $this->warn("No validation runs recorded yet for Institute {$instituteId}.");
            return self::SUCCESS;
        }

        $counts = $latest['counts'];

        $this->info("\n--- Latest Hierarchy Validation Findings ---");
        $this->table(
            ['Field', 'Detail'],
            [
                ['Status', strtoupper($latest['status'])],
                ['Data Hash', $latest['data_hash']],
                ['Last Execution Time', $latest['last_run_time']],
                ['Finished At', $latest['finished_at'] ?? 'N/A'],
                ['Next Scheduled Run', $latest['next_scheduled_run']],
                ['Standards Checked', $counts['standards_checked']],
                ['Subjects Checked', $counts['subjects_checked']],
                ['Chapters Checked', $counts['chapters_checked']],
                ['Topics Checked', $counts['topics_checked']],
                ['Intelligence Configs Checked', $counts['intelligence_configs_checked']],
                ['Complete Hierarchies Validated', $counts['complete_hierarchies_validated']],
                ['Missing Subjects', $counts['missing_subjects_count']],
                ['Missing Chapters', $counts['missing_chapters_count']],
                ['Missing Topics', $counts['missing_topics_count']],
                ['Missing Intelligence Configs', $counts['missing_intelligence_count']],
                ['Broken Mappings', $counts['broken_mappings_count']],
                ['Total Issues Detected', $counts['total_issues_detected']],
            ]
        );

        if ($this->option('show-issues') && !empty($latest['issues'])) {
            $this->displayIssuesBreakdown($latest['issues']);
        }

        return self::SUCCESS;
    }

    /**
     * Print detailed breakdown of issues.
     */
    protected function displayIssuesBreakdown(array $issues): void
    {
        $this->line("\n=== DETAILED ISSUES BREAKDOWN ===");

        // Missing chapters sample
        if (!empty($issues['missing_chapters'])) {
            $this->warn("\n[Missing Chapters] (" . count($issues['missing_chapters']) . " total)");
            $rows = array_map(function ($item) {
                return [
                    $item['standard_id'] . ' (' . $item['standard_name'] . ')',
                    $item['subject_id'] . ' (' . $item['subject_name'] . ')',
                    $item['issue'],
                ];
            }, array_slice($issues['missing_chapters'], 0, 5));
            $this->table(['Standard', 'Subject', 'Issue'], $rows);
        }

        // Missing topics sample
        if (!empty($issues['missing_topics'])) {
            $this->warn("\n[Missing Topics in Chapters] (" . count($issues['missing_topics']) . " total)");
            $rows = array_map(function ($item) {
                return [
                    $item['standard_name'],
                    $item['subject_name'],
                    $item['chapter_id'] . ': ' . $item['chapter_name'],
                    $item['issue'],
                ];
            }, array_slice($issues['missing_topics'], 0, 5));
            $this->table(['Standard', 'Subject', 'Chapter', 'Issue'], $rows);
        }

        // Missing intelligence sample
        if (!empty($issues['missing_intelligence'])) {
            $this->warn("\n[Missing Intelligence in Topics] (" . count($issues['missing_intelligence']) . " total)");
            $rows = array_map(function ($item) {
                return [
                    $item['standard_name'],
                    $item['subject_name'],
                    $item['chapter_name'],
                    $item['topic_id'] . ': ' . $item['topic_name'],
                    $item['issue'],
                ];
            }, array_slice($issues['missing_intelligence'], 0, 5));
            $this->table(['Standard', 'Subject', 'Chapter', 'Topic', 'Issue'], $rows);
        }

        // Broken mappings sample
        if (!empty($issues['broken_mappings'])) {
            $this->error("\n[Broken / Orphaned Mappings] (" . count($issues['broken_mappings']) . " total)");
            $rows = array_map(function ($item) {
                return [
                    $item['topic_id'] ?? $item['chapter_id'] ?? 'N/A',
                    $item['topic_name'] ?? $item['chapter_name'] ?? 'N/A',
                    $item['issue'] ?? 'Broken reference',
                ];
            }, array_slice($issues['broken_mappings'], 0, 5));
            $this->table(['ID', 'Name', 'Broken Reference Issue'], $rows);
        }
    }

    /**
     * Handle OS signals for graceful shutdown in daemon mode.
     */
    protected function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->warn("\nReceived SIGINT. Shutting down gracefully...");
                $this->shouldKeepRunning = false;
            });
            pcntl_signal(SIGTERM, function () {
                $this->warn("\nReceived SIGTERM. Shutting down gracefully...");
                $this->shouldKeepRunning = false;
            });
        }
    }
}
