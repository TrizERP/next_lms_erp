<?php

namespace App\Console\Commands\PAL;

use App\Services\PAL\Flow\EsoFlowResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Is the flow engine resolving the same things it was before the flip?
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * Rollout step 4 switches nextAction() from the hardcoded cascade to the stage
 * pipeline. EsoFlowParityTest proves the two resolve identically over every
 * branch, but it proves it against FIXTURES — a dozen hand-built learners on a
 * handful of concepts. Production has thousands of learners on content the
 * fixtures never modelled, and the failure that matters is the quiet one: a
 * stage that stops firing for a shape of data nobody wrote a test for.
 *
 * `eso_decision_log` is where that shows up, because every resolve writes
 * exactly one row naming the rule that fired. If `check_understanding`
 * disappears overnight, or `content_unavailable` triples, the distribution
 * says so before anyone files a ticket.
 *
 * Read-only. It writes nothing and changes nothing.
 *
 * ---------------------------------------------------------------------------
 * WHY IT COMPARES RATES AND NOT COUNTS
 * ---------------------------------------------------------------------------
 * The watch window (48h by default) and the baseline (the preceding week) are
 * different lengths, and school traffic is not flat — a Tuesday and a Sunday
 * are not comparable. Counts would make every reading look like a collapse.
 * Both sides are normalised to a per-day rate, which is still crude but at
 * least compares like with like.
 *
 * Treat a single run as a smoke test, not as evidence. What matters is an
 * action VANISHING or APPEARING, which is reported separately and is the thing
 * a broken stage actually looks like.
 *
 * ---------------------------------------------------------------------------
 * CLOCK SKEW - READ BEFORE CHANGING THE QUERIES
 * ---------------------------------------------------------------------------
 * The MariaDB host clock runs roughly 2.5 hours behind PHP (PHP is
 * Asia/Kolkata), documented on App\Models\Eso\ResponseLog lines 20-32.
 *
 * eso_decision_log.created_at is written by Eloquent from PHP time, so the
 * window boundaries below are built in PHP and passed as bound values. Using
 * SQL NOW() instead would compare a PHP-written column against a DB-generated
 * clock and silently shift every window by two and a half hours - which at a
 * 48h window would quietly reclassify a tenth of the rows.
 */
class FlowEngineStatusCommand extends Command
{
    protected $signature = 'pal:flow-engine-status
        {--hours=48 : Size of the watch window, in hours}
        {--baseline-days=7 : Size of the comparison window, in days, ending where the watch window starts}
        {--institute= : Restrict to one sub_institute_id}
        {--threshold=40 : Percentage change in a per-day rate worth flagging}
        {--min-rate=1 : Baseline actions rarer than this per day are too rare to judge}';

    protected $description = 'Compare eso_decision_log action rates since the flow-engine flip against the preceding baseline. Read-only.';

    public function handle(): int
    {
        if (! Schema::hasTable('eso_decision_log')) {
            $this->error('eso_decision_log does not exist on this connection.');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $baselineDays = max(1, (int) $this->option('baseline-days'));
        $institute = $this->option('institute') === null ? null : (int) $this->option('institute');
        $threshold = max(0, (float) $this->option('threshold'));
        $minRate = max(0, (float) $this->option('min-rate'));

        // Built in PHP, not SQL - see the clock-skew note on the class.
        $watchFrom = now()->subHours($hours);
        $baselineFrom = (clone $watchFrom)->subDays($baselineDays);

        $this->reportEngine($institute);
        $this->reportPins($institute);

        $watch = $this->rates($watchFrom, now(), $hours / 24, $institute);
        $baseline = $this->rates($baselineFrom, $watchFrom, $baselineDays, $institute);

        if ($watch === [] && $baseline === []) {
            $this->warn('No decisions recorded in either window. Nothing to compare yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['Action', 'Baseline /day', 'Watch /day', 'Change', ''],
            $this->rows($baseline, $watch, $threshold, $minRate)
        );

        $this->line('');
        $this->line(sprintf(
            '  Watch:    %s to now (%dh)',
            $watchFrom->toDateTimeString(),
            $hours
        ));
        $this->line(sprintf(
            '  Baseline: %s to %s (%dd)',
            $baselineFrom->toDateTimeString(),
            $watchFrom->toDateTimeString(),
            $baselineDays
        ));
        $this->line('');
        $this->warn('  Both windows cover whatever engine was running AT THE TIME. A run taken');
        $this->warn('  shortly after a flip is reading pre-flip traffic and says nothing about');
        $this->warn('  the new engine. Let the watch window fill first.');

        return $this->verdict($baseline, $watch, $minRate);
    }

    /**
     * Which engine and which flow this estate is actually running.
     *
     * Reported first because every number below is meaningless without it — a
     * distribution that shifted because someone reassigned a profile is not
     * the same finding as one that shifted because a stage broke.
     */
    private function reportEngine(?int $institute): void
    {
        $engine = (string) config('pal_flow.guards.engine', 'legacy');

        $this->line('');
        $this->line('  Engine:   <fg=cyan>' . $engine . '</>' . ($engine === 'legacy' ? '  (the hardcoded cascade)' : '  (the stage pipeline)'));

        try {
            $plan = app(EsoFlowResolver::class)->resolve($institute ?? 0);
            $this->line('  Profile:  ' . $plan->profileKey() . '  [' . implode(' > ', $plan->phaseOrder()) . ']');
        } catch (Throwable $e) {
            // A resolver failure is itself the most important thing this
            // command can report, so it is surfaced rather than swallowed.
            $this->line('  Profile:  <fg=red>unresolvable - ' . $e->getMessage() . '</>');
        }

        $this->line('');
    }

    /**
     * Is the flow-version pin actually being stamped?
     *
     * This is the whole reason step 6 ships before anyone is assigned a
     * non-standard flow. While every institute runs `standard`, a broken pin
     * is INVISIBLE — every learner resolves the same flow whether or not their
     * rows carry a version — so the only way to know the stamp works is to
     * look at the column. By the time a pin matters it is too late to discover
     * it was never written.
     *
     * ---------------------------------------------------------------------
     * IT CALIBRATES ITSELF, AND THAT IS THE POINT
     * ---------------------------------------------------------------------
     * The naive check — "unpinned rows created in the watch window" — reports
     * a failure that is not one. The column was added without a backfill on
     * purpose, so every row predating it is correctly null, and a 48h window
     * reaches back past the migration on the day it runs. Measured: it
     * reported "3 NEW ROWS UNPINNED - the stamp is failing" against a
     * perfectly working stamp.
     *
     * So the boundary is taken from the DATA rather than from the clock: the
     * oldest pinned row is the moment stamping demonstrably began, and only
     * unpinned rows created AFTER that are defects. Before any row is pinned
     * there is nothing to calibrate against, and the honest answer is that
     * this cannot be judged yet — which it says, rather than guessing.
     *
     * Same failure mode as the action table below, and the same fix: a monitor
     * that reports a change it cannot yet see is worse than one that says so.
     */
    private function reportPins(?int $institute): void
    {
        if (! Schema::hasTable('learner_node_state') || ! Schema::hasColumn('learner_node_state', 'flow_version_id')) {
            $this->line('  Pins:     <fg=gray>flow_version_id not present on this connection</>');
            $this->line('');

            return;
        }

        $scope = static function () use ($institute) {
            $q = DB::table('learner_node_state');

            return $institute === null ? $q : $q->where('sub_institute_id', $institute);
        };

        $total = (clone $scope())->count();
        $pinned = (clone $scope())->whereNotNull('flow_version_id')->count();

        $this->line(sprintf('  Pins:     %d of %d rows carry a flow version', $pinned, $total));

        // The moment stamping demonstrably started. Nothing before it can be
        // judged, because the column did not exist or the engine was legacy.
        $since = (clone $scope())->whereNotNull('flow_version_id')->min('created_at');

        if ($since === null) {
            $engine = (string) config('pal_flow.guards.engine');

            $this->line($engine === 'pipeline'
                ? '            <fg=gray>no row has been pinned yet - nothing to judge until real traffic creates one</>'
                : '            <fg=gray>the legacy engine does not pin, by design</>');
            $this->line('');

            return;
        }

        $unpinnedSince = (clone $scope())->where('created_at', '>=', $since)->whereNull('flow_version_id')->count();
        $pinnedSince = (clone $scope())->where('created_at', '>=', $since)->whereNotNull('flow_version_id')->count();

        $verdict = $unpinnedSince === 0
            ? '<fg=green>every row since is pinned</>'
            : '<fg=red>' . $unpinnedSince . ' UNPINNED since stamping began - the stamp is failing</>';

        $this->line(sprintf(
            '            since first pin (%s): %d pinned, %d unpinned  %s',
            $since,
            $pinnedSince,
            $unpinnedSince,
            $verdict
        ));

        $this->line('');
    }

    /**
     * Actions per day in a window, keyed by action.
     *
     * @return array<string, float>
     */
    private function rates(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to, float $days, ?int $institute): array
    {
        $query = DB::table('eso_decision_log')
            ->selectRaw('action, COUNT(*) as total')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->groupBy('action');

        if ($institute !== null) {
            $query->where('sub_institute_id', $institute);
        }

        $out = [];

        foreach ($query->get() as $row) {
            $out[(string) $row->action] = round(((int) $row->total) / max($days, 0.01), 1);
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $baseline
     * @param  array<string, float>  $watch
     * @return array<int, array<int, string>>
     */
    private function rows(array $baseline, array $watch, float $threshold, float $minRate): array
    {
        $actions = array_unique(array_merge(array_keys($baseline), array_keys($watch)));
        sort($actions);

        $rows = [];

        foreach ($actions as $action) {
            $was = $baseline[$action] ?? 0.0;
            $now = $watch[$action] ?? 0.0;

            [$change, $flag] = $this->describe($was, $now, $threshold, $minRate);

            $rows[] = [$action, (string) $was, (string) $now, $change, $flag];
        }

        return $rows;
    }

    /** @return array{0:string, 1:string} */
    private function describe(float $was, float $now, float $threshold, float $minRate): array
    {
        // An action that STOPPED is the signal a broken stage actually gives,
        // and it is the one finding worth interrupting someone for.
        //
        // But only when it was common enough for its absence to mean anything.
        // A dev estate resolves `remediate_prerequisite` roughly once every ten
        // days; two quiet days is the null hypothesis, not a regression. Without
        // this floor the command flags six "STOPPED" actions on a healthy
        // system and is ignored within a week.
        if ($was > 0.0 && $now === 0.0) {
            return $was >= $minRate
                ? ['gone', '<fg=red>STOPPED</>']
                : ['gone', '<fg=gray>too rare to judge</>'];
        }

        if ($was === 0.0 && $now > 0.0) {
            return ['new', '<fg=yellow>NEW</>'];
        }

        if ($was === 0.0) {
            return ['-', ''];
        }

        $delta = (($now - $was) / $was) * 100;
        $text = sprintf('%+.0f%%', $delta);

        return [$text, abs($delta) >= $threshold ? '<fg=yellow>check</>' : ''];
    }

    /**
     * Non-zero only when an action stopped entirely.
     *
     * A rate that moved is a prompt to look; a rate that went to zero is a
     * regression until proven otherwise, and returning FAILURE lets this be
     * wired into a scheduled check without anyone reading the table.
     *
     * @param  array<string, float>  $baseline
     * @param  array<string, float>  $watch
     */
    private function verdict(array $baseline, array $watch, float $minRate): int
    {
        $stopped = [];

        foreach ($baseline as $action => $was) {
            if ($was >= $minRate && ($watch[$action] ?? 0.0) === 0.0) {
                $stopped[] = $action;
            }
        }

        if ($stopped === []) {
            $this->info('  Every action seen in the baseline is still being resolved.');

            return self::SUCCESS;
        }

        $this->error('  Stopped resolving: ' . implode(', ', $stopped));
        $this->line('  Revert with PAL_FLOW_ENGINE=legacy (no deploy needed) and compare again.');

        return self::FAILURE;
    }
}
