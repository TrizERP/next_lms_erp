<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\LearnerStreak;
use App\Models\PAL\Gamification\StreakDay;
use Illuminate\Support\Carbon;

/**
 * New PAL → Gamification: the streak system (document §7).
 *
 * Two design rules from the document shape this whole class:
 *
 *  1. A day only counts on REAL productive engagement. Opening the app, or
 *     watching a video without interacting, is not a streak day. The bar is a
 *     completed learning cell (or three spaced-review items, or a peer teaching
 *     session, or a team-challenge contribution) AND the minimum productive
 *     minutes. Days that had activity but missed the bar are still returned, so
 *     the learner can be told what was missing instead of silently losing a day.
 *
 *  2. One missed day is forgiven, once a week. Illness, school events and
 *     family situations must not carry a penalty — the grace period exists to
 *     remove guilt, not to add a mechanic.
 *
 * The streak is RECOMPUTED from the day ledger rather than incremented, so it
 * can never drift: a backfilled attempt or a corrected timestamp produces the
 * right answer on the next read.
 */
class StreakService
{
    public function __construct(private readonly LearnerActivitySource $activity)
    {
    }

    /**
     * Recompute the learner's day ledger and streak head from real activity,
     * persist both, and return the streak.
     *
     * @return array<string,mixed>
     */
    public function recompute(int $learnerId): array
    {
        $cfg = (array) config('pal_gamification.streak', []);
        $minMinutes = (float) ($cfg['min_productive_minutes'] ?? 10);
        $activities = (array) ($cfg['qualifying_activities'] ?? []);

        $days = $this->activity->dailyActivity($learnerId);

        $ledger = [];
        foreach ($days as $date => $day) {
            $met = [];
            foreach ($activities as $key => $rule) {
                $count = (int) ($day['activities'][$key] ?? 0);
                if ($count >= (int) ($rule['min_count'] ?? 1)) {
                    $met[] = $key;
                }
            }

            $qualified = $met !== [] && $day['productive_minutes'] >= $minMinutes;

            $ledger[$date] = [
                'date' => $date,
                'productive_minutes' => round((float) $day['productive_minutes'], 2),
                'qualifying_events' => count($met),
                'qualifying_activities' => $met,
                'sources' => $day['sources'] ?? [],
                'concept_count' => (int) ($day['concept_count'] ?? 0),
                'qualified' => $qualified,
                // What was missing, for an honest "so close" message.
                'shortfall' => $qualified ? null : ($met === []
                    ? 'no_qualifying_activity'
                    : 'below_minimum_minutes'),
            ];

            StreakDay::updateOrCreate(
                ['learner_id' => $learnerId, 'activity_date' => $date],
                [
                    'productive_minutes' => (int) round($ledger[$date]['productive_minutes']),
                    'qualifying_events' => $ledger[$date]['qualifying_events'],
                    'sources' => $ledger[$date]['sources'],
                    'qualified' => $qualified,
                ]
            );
        }

        $streak = $this->walk(array_keys(array_filter($ledger, fn ($d) => $d['qualified'])), $cfg);

        LearnerStreak::updateOrCreate(
            ['learner_id' => $learnerId],
            [
                'current_streak' => $streak['current_streak'],
                'current_started_on' => $streak['current_started_on'],
                'longest_streak' => $streak['longest_streak'],
                'longest_streak_ended_on' => $streak['longest_streak_ended_on'],
                'last_active_date' => $streak['last_active_date'],
                'grace_used_on' => $streak['grace_used_on'],
                'total_active_days' => $streak['total_active_days'],
                'recomputed_at' => now(),
            ]
        );

        return $streak + ['ledger' => $ledger];
    }

    /**
     * Walk the qualifying days and derive current / longest streaks.
     *
     * The grace rule: a single missed day inside a run is forgiven, and a grace
     * may only be spent once per `grace_reset_days`. A two-day gap always ends
     * the run — and the run that follows is a NEW streak, never a "broken" one.
     *
     * @param array<int,string> $qualifyingDates Y-m-d, ascending
     */
    private function walk(array $qualifyingDates, array $cfg): array
    {
        sort($qualifyingDates);

        $graceDays = (int) ($cfg['grace_period_days'] ?? 1);
        $graceReset = (int) ($cfg['grace_reset_days'] ?? 7);

        $current = 0;
        $currentStart = null;
        $longest = 0;
        $longestEnd = null;
        $graceUsedOn = null;
        $previous = null;

        foreach ($qualifyingDates as $date) {
            $day = Carbon::parse($date);

            if ($previous === null) {
                $current = 1;
                $currentStart = $date;
                $previous = $day;
                continue;
            }

            $gap = (int) $previous->diffInDays($day);

            if ($gap === 1) {
                $current++;
            } elseif ($gap - 1 <= $graceDays && $this->graceAvailable($graceUsedOn, $day, $graceReset)) {
                // One forgiven day, at most once per reset window.
                $current++;
                $graceUsedOn = $previous->copy()->addDay()->toDateString();
            } else {
                if ($current > $longest) {
                    $longest = $current;
                    $longestEnd = $previous->toDateString();
                }
                $current = 1;
                $currentStart = $date;
            }

            $previous = $day;
        }

        if ($current > $longest) {
            $longest = $current;
            $longestEnd = $previous?->toDateString();
        }

        // A run only counts as "current" if it reaches today or yesterday (or
        // the day before, when a grace is still available).
        $today = Carbon::today();
        $lastActive = $previous;
        if ($lastActive !== null) {
            $sinceLast = (int) $lastActive->diffInDays($today);
            $stillAlive = $sinceLast <= 1
                || ($sinceLast - 1 <= $graceDays && $this->graceAvailable($graceUsedOn, $today, $graceReset));
            if (! $stillAlive) {
                $current = 0;
                $currentStart = null;
            }
        }

        return [
            'current_streak' => $current,
            'current_started_on' => $currentStart,
            'longest_streak' => $longest,
            'longest_streak_ended_on' => $longestEnd,
            'last_active_date' => $lastActive?->toDateString(),
            'grace_used_on' => $graceUsedOn,
            'total_active_days' => count($qualifyingDates),
        ];
    }

    private function graceAvailable(?string $graceUsedOn, Carbon $at, int $resetDays): bool
    {
        if ($graceUsedOn === null) {
            return true;
        }

        return Carbon::parse($graceUsedOn)->diffInDays($at) >= $resetDays;
    }

    /**
     * The student-facing streak card.
     *
     * Copy comes from §7.2's growth-focused list only. Nothing here can produce
     * "you broke your streak", a countdown, or a comparison to classmates —
     * those framings are named in config as forbidden and have no code path.
     */
    public function summary(int $learnerId): array
    {
        $streak = $this->recompute($learnerId);
        $cfg = (array) config('pal_gamification.streak', []);
        $ledger = $streak['ledger'] ?? [];

        $today = Carbon::today()->toDateString();
        $activeToday = ($ledger[$today]['qualified'] ?? false) === true;

        $current = (int) $streak['current_streak'];
        $longest = (int) $streak['longest_streak'];

        $headline = match (true) {
            $current === 0 && $longest === 0 => 'Your first learning day starts whenever you are ready.',
            $current === 0 => (string) ($cfg['language']['reset_copy'] ?? 'New streak starting.'),
            $current === 1 => (string) ($cfg['language']['return_copy'] ?? 'You came back. That is the whole game.'),
            $current >= $longest && $longest > 0 => "Day {$current} — you have matched your own best.",
            default => "Day {$current} — you are building a habit.",
        };

        // Days with real activity that did not clear the bar. Shown so the rule
        // is transparent, never as a reprimand.
        $nearMisses = collect($ledger)
            ->filter(fn ($d) => ! $d['qualified'] && $d['productive_minutes'] > 0)
            ->sortByDesc('date')
            ->take(10)
            ->values()
            ->all();

        return [
            'current_streak' => $current,
            'longest_streak' => $longest,
            'longest_streak_ended_on' => $streak['longest_streak_ended_on'],
            'current_started_on' => $streak['current_started_on'],
            'last_active_date' => $streak['last_active_date'],
            'total_active_days' => (int) $streak['total_active_days'],
            'active_today' => $activeToday,
            'grace_used_on' => $streak['grace_used_on'],
            'grace_available' => $this->graceAvailable(
                $streak['grace_used_on'],
                Carbon::today(),
                (int) ($cfg['grace_reset_days'] ?? 7)
            ),
            'headline' => $headline,
            'rules' => [
                'min_productive_minutes' => (int) ($cfg['min_productive_minutes'] ?? 10),
                'qualifying_activities' => array_map(
                    fn ($key, $rule) => [
                        'key' => $key,
                        'label' => $rule['label'] ?? $key,
                        'min_count' => (int) ($rule['min_count'] ?? 1),
                    ],
                    array_keys((array) ($cfg['qualifying_activities'] ?? [])),
                    array_values((array) ($cfg['qualifying_activities'] ?? []))
                ),
                'grace_period_days' => (int) ($cfg['grace_period_days'] ?? 1),
                'grace_reset_days' => (int) ($cfg['grace_reset_days'] ?? 7),
            ],
            'milestones' => array_map(fn ($days) => [
                'days' => (int) $days,
                'reached' => $longest >= (int) $days,
            ], (array) ($cfg['milestones'] ?? [])),
            'near_misses' => $nearMisses,
        ];
    }

    /** The full day ledger, newest first — the streaks page calendar. */
    public function history(int $learnerId, int $days = 120): array
    {
        $streak = $this->recompute($learnerId);
        $cutoff = Carbon::today()->subDays(max(1, $days))->toDateString();

        $ledger = collect($streak['ledger'] ?? [])
            ->filter(fn ($d) => $d['date'] >= $cutoff)
            ->sortByDesc('date')
            ->values()
            ->all();

        return [
            'from' => $cutoff,
            'to' => Carbon::today()->toDateString(),
            'days' => $ledger,
            'qualified_days' => count(array_filter($ledger, fn ($d) => $d['qualified'])),
            'active_days' => count($ledger),
        ];
    }

    /** The learner's longest-ever streak — the personal-best feed reads this. */
    public function longestStreak(int $learnerId): int
    {
        return (int) ($this->recompute($learnerId)['longest_streak'] ?? 0);
    }

    /** Gap in days before the most recent qualifying day, for "Comeback kid". */
    public function longestReturnGap(int $learnerId): int
    {
        $ledger = $this->recompute($learnerId)['ledger'] ?? [];
        $dates = array_keys(array_filter($ledger, fn ($d) => $d['qualified']));
        sort($dates);

        $maxGap = 0;
        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $gap = (int) Carbon::parse($dates[$i - 1])->diffInDays(Carbon::parse($dates[$i]));
            $maxGap = max($maxGap, $gap - 1);
        }

        return $maxGap;
    }
}
