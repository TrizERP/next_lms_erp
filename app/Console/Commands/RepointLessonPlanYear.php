<?php

namespace App\Console\Commands;

use App\Services\LessonIntelligence\LessonIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Move lesson plans from the year their source data lives in to the year the
 * calendar displays.
 *
 * Institute 1 keeps its terms, timetable and holidays under 2022 but every
 * lesson-plan reader pins it to 2026, so plans generated before the generator
 * learned to record under 2026 are stranded: they exist, they hold periods, and
 * nothing can display them. This walks them across.
 *
 * The unique key (institute, year, term, standard, subject, division) means a
 * plan can collide with one that already sits on the target year. The rule is
 * "richer wins": whichever side holds more periods survives, the other is
 * removed. Run with --dry-run first - it prints exactly what it would do.
 *
 *   php artisan lesson-plans:repoint-year --institute=1 --from=2022 --to=2026 --dry-run
 */
class RepointLessonPlanYear extends Command
{
    protected $signature = 'lesson-plans:repoint-year
                            {--institute= : sub_institute_id to act on}
                            {--from= : the year the plans are recorded under now}
                            {--to= : the year they should be recorded under}
                            {--dry-run : print the plan without writing anything}';

    protected $description = 'Move lesson plans between academic years, resolving collisions by period count';

    public function handle(): int
    {
        $institute = (int) $this->option('institute');
        $from      = (int) $this->option('from');
        $to        = (int) $this->option('to');
        $dryRun    = (bool) $this->option('dry-run');

        if (!$institute || !$from || !$to) {
            $this->error('--institute, --from and --to are all required.');

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error('--from and --to are the same year; nothing to do.');

            return self::FAILURE;
        }

        $source = DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)
            ->where('sub_institute_id', $institute)
            ->where('syear', $from)
            ->orderBy('id')
            ->get();

        if ($source->isEmpty()) {
            $this->info("No plans for institute {$institute} in {$from}. Nothing to do.");

            return self::SUCCESS;
        }

        $this->line(($dryRun ? 'DRY RUN - ' : '') . "Moving {$source->count()} plan(s) from {$from} to {$to}");
        $this->newLine();

        $moved = $dropped = $replaced = 0;

        foreach ($source as $plan) {
            $periods = $this->periodCount($plan->id);

            $clash = DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)
                ->where([
                    'sub_institute_id' => $institute,
                    'syear'            => $to,
                    'term_id'          => $plan->term_id,
                    'standard_id'      => $plan->standard_id,
                    'subject_id'       => $plan->subject_id,
                ])
                ->where(function ($q) use ($plan) {
                    $plan->division_id === null
                        ? $q->whereNull('division_id')
                        : $q->where('division_id', $plan->division_id);
                })
                ->first();

            if (!$clash) {
                $this->line("  plan {$plan->id} ({$periods} periods) -> {$to}");
                if (!$dryRun) {
                    $this->restamp($plan->id, $to);
                }
                $moved++;
                continue;
            }

            $clashPeriods = $this->periodCount($clash->id);

            if ($periods > $clashPeriods) {
                $this->line("  plan {$plan->id} ({$periods} periods) REPLACES plan {$clash->id} ({$clashPeriods} periods)");
                if (!$dryRun) {
                    DB::transaction(function () use ($clash, $plan, $to) {
                        // Delete first so the unique key is free before the move.
                        DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)->where('id', $clash->id)->delete();
                        $this->restamp($plan->id, $to);
                    });
                }
                $replaced++;
                continue;
            }

            $this->line("  plan {$plan->id} ({$periods} periods) DROPPED - plan {$clash->id} on {$to} has {$clashPeriods}");
            if (!$dryRun) {
                DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)->where('id', $plan->id)->delete();
            }
            $dropped++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would move' : 'Moved') . ": {$moved}, replaced: {$replaced}, dropped: {$dropped}");

        if ($dryRun) {
            $this->comment('Nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function periodCount(int $planId): int
    {
        return DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)
            ->where('lms_intelligence_lesson_plans_id', $planId)
            ->count();
    }

    /**
     * Move the plan and retitle it. Periods hang off the plan id, which does not
     * change, so they travel with it and need no update of their own.
     */
    private function restamp(int $planId, int $to): void
    {
        $plan = DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)->where('id', $planId)->first(['plan_title', 'syear']);

        DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)
            ->where('id', $planId)
            ->update([
                'syear'      => $to,
                'plan_title' => str_replace("({$plan->syear})", "({$to})", (string) $plan->plan_title),
                'updated_at' => now(),
            ]);
    }
}
