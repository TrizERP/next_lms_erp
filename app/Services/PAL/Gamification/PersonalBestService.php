<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\GamificationNotification;
use App\Models\PAL\Gamification\PersonalBest;
use App\Models\PAL\Gamification\PersonalBestEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * New PAL → Gamification: the Personal Best system (document §2).
 *
 * The single most important design decision in the module: every student
 * competes only against their own prior performance. A learner improving from
 * 0.40 to 0.65 is celebrated exactly as much as one improving from 0.80 to 0.93,
 * because both moved by the same amount relative to where they were.
 *
 * The consequence for this class is that it computes CANDIDATES from live
 * activity and compares them ONLY to that learner's own stored record. There is
 * no query in this file that touches another learner, and §2.4's forbidden
 * framings (rank, peers ahead, class average) have no code path here at all.
 */
class PersonalBestService
{
    public function __construct(
        private readonly LearnerActivitySource $activity,
        private readonly StreakService $streaks,
    ) {
    }

    /**
     * Recompute every metric from real activity, promote any that beat the
     * learner's own record, and return the records that were broken.
     *
     * @return array<int,array<string,mixed>> newly set records
     */
    public function refresh(int $learnerId): array
    {
        $definitions = (array) config('pal_gamification.personal_best.metrics', []);
        $broken = [];

        foreach ($this->candidates($learnerId) as $candidate) {
            $definition = $definitions[$candidate['metric_key']] ?? null;
            if ($definition === null) {
                continue;
            }

            $record = $this->promote($learnerId, $definition, $candidate);
            if ($record !== null) {
                $broken[] = $record;
            }
        }

        return $broken;
    }

    /**
     * Every metric's current value, measured from real activity.
     *
     * @return array<int,array<string,mixed>>
     */
    private function candidates(int $learnerId): array
    {
        $concepts = $this->activity->conceptRecords($learnerId);
        $attempts = $this->activity->attempts($learnerId);
        $out = [];

        // ---- Per-concept fluency records ---------------------------------
        foreach ($concepts as $ref => $concept) {
            if ($concept['best_net_fluency'] === null) {
                continue;
            }
            $out[] = [
                'metric_key' => 'net_fluency',
                'scope_type' => 'concept',
                'scope_ref' => (string) $ref,
                'scope_label' => (string) $concept['concept_label'],
                'value' => (float) $concept['best_net_fluency'],
                'achieved_at' => $concept['last_seen_at'],
                'context' => [
                    'subject' => $concept['subject_name'],
                    'tier' => $concept['tier'],
                    'sessions' => $concept['sessions'],
                ],
            ];
        }

        // ---- Fastest concept to Mountain, in sessions ---------------------
        foreach ($this->sessionsToMastery($learnerId) as $row) {
            $out[] = [
                'metric_key' => 'fastest_concept_sessions',
                'scope_type' => 'concept',
                'scope_ref' => $row['concept_ref'],
                'scope_label' => $row['concept_label'],
                'value' => (float) $row['sessions'],
                'achieved_at' => $row['reached_at'],
                'context' => ['tier' => $row['tier']],
            ];
        }

        // ---- Mastery counts ------------------------------------------------
        $tierCounts = $this->tierCounts($concepts);
        foreach (['sky' => 'concepts_at_sky', 'mountain' => 'concepts_at_mountain'] as $tier => $metric) {
            if (($tierCounts[$tier] ?? 0) > 0) {
                $out[] = [
                    'metric_key' => $metric,
                    'scope_type' => 'global',
                    'scope_ref' => '',
                    'scope_label' => null,
                    'value' => (float) $tierCounts[$tier],
                    'achieved_at' => now(),
                    'context' => ['tier_counts' => $tierCounts],
                ];
            }
        }

        // ---- Longest streak -------------------------------------------------
        $longestStreak = $this->streaks->longestStreak($learnerId);
        if ($longestStreak > 0) {
            $out[] = [
                'metric_key' => 'streak_days',
                'scope_type' => 'global',
                'scope_ref' => '',
                'scope_label' => null,
                'value' => (float) $longestStreak,
                'achieved_at' => now(),
                'context' => [],
            ];
        }

        // ---- Session records ------------------------------------------------
        $daily = $this->activity->dailyActivity($learnerId);
        if ($daily !== []) {
            $longestDay = collect($daily)->max('productive_minutes');
            if ($longestDay > 0) {
                $out[] = [
                    'metric_key' => 'longest_productive_session_min',
                    'scope_type' => 'global',
                    'scope_ref' => '',
                    'scope_label' => null,
                    'value' => round((float) $longestDay, 2),
                    'achieved_at' => now(),
                    'context' => [],
                ];
            }

            $mostInDay = collect($daily)->max('concept_count');
            if ($mostInDay > 0) {
                $out[] = [
                    'metric_key' => 'most_concepts_in_one_day',
                    'scope_type' => 'global',
                    'scope_ref' => '',
                    'scope_label' => null,
                    'value' => (float) $mostInDay,
                    'achieved_at' => now(),
                    'context' => [],
                ];
            }

            $weekly = $this->conceptsPerWeek($attempts);
            if ($weekly !== []) {
                $out[] = [
                    'metric_key' => 'most_concepts_in_one_week',
                    'scope_type' => 'global',
                    'scope_ref' => '',
                    'scope_label' => null,
                    'value' => (float) max($weekly),
                    'achieved_at' => now(),
                    'context' => ['weeks_tracked' => count($weekly)],
                ];
            }
        }

        // ---- Best single-session mastery gain --------------------------------
        $gain = $this->bestSingleSessionGain($attempts);
        if ($gain !== null && $gain['value'] > 0) {
            $out[] = [
                'metric_key' => 'best_single_session_mastery_gain',
                'scope_type' => 'global',
                'scope_ref' => '',
                'scope_label' => null,
                'value' => $gain['value'],
                'achieved_at' => $gain['at'],
                'context' => ['concept' => $gain['concept_label']],
            ];
        }

        return $out;
    }

    /**
     * Compare a candidate against the learner's own record and promote it if it
     * is genuinely better. Returns the record when one was broken, else null.
     */
    private function promote(int $learnerId, array $definition, array $candidate): ?array
    {
        $existing = PersonalBest::where('learner_id', $learnerId)
            ->where('metric_key', $candidate['metric_key'])
            ->where('scope_ref', $candidate['scope_ref'])
            ->first();

        $lowerIsBetter = ($definition['direction'] ?? 'higher') === 'lower';
        $value = round((float) $candidate['value'], 4);

        if ($existing !== null) {
            $best = (float) $existing->best_value;
            $isBetter = $lowerIsBetter ? $value < $best : $value > $best;
            if (! $isBetter) {
                return null;
            }
        }

        $previous = $existing !== null ? (float) $existing->best_value : null;
        $achievedAt = $candidate['achieved_at'] instanceof Carbon
            ? $candidate['achieved_at']
            : ($candidate['achieved_at'] ? Carbon::parse($candidate['achieved_at']) : now());

        $record = PersonalBest::updateOrCreate(
            [
                'learner_id' => $learnerId,
                'metric_key' => $candidate['metric_key'],
                'scope_ref' => $candidate['scope_ref'],
            ],
            [
                'scope_type' => $candidate['scope_type'],
                'scope_label' => $candidate['scope_label'],
                'best_value' => $value,
                'best_achieved_at' => $achievedAt,
                'previous_value' => $previous,
                'previous_achieved_at' => $existing?->best_achieved_at,
                'context' => $candidate['context'] ?? [],
            ]
        );

        // A first-ever measurement is a baseline, not a broken record — the
        // learner has nothing to have beaten yet, so nothing is celebrated.
        if ($previous === null) {
            return null;
        }

        $improvement = $previous > 0
            ? round((($value - $previous) / $previous) * 100, 2)
            : null;

        PersonalBestEvent::create([
            'learner_id' => $learnerId,
            'metric_key' => $candidate['metric_key'],
            'scope_type' => $candidate['scope_type'],
            'scope_ref' => $candidate['scope_ref'],
            'scope_label' => $candidate['scope_label'],
            'value' => $value,
            'previous_value' => $previous,
            'improvement_pct' => $improvement,
            'achieved_at' => $achievedAt,
            'context' => $candidate['context'] ?? [],
        ]);

        $message = $this->message($definition, $candidate, $value, $previous);

        GamificationNotification::create([
            'learner_id' => $learnerId,
            'type' => 'personal_best',
            'level' => 'large',
            'title' => 'Personal best',
            'body' => $message,
            'context' => [
                'metric_key' => $candidate['metric_key'],
                'scope_ref' => $candidate['scope_ref'],
                'value' => $value,
                'previous_value' => $previous,
                'improvement_pct' => $improvement,
            ],
        ]);

        return [
            'metric_key' => $candidate['metric_key'],
            'label' => $definition['label'] ?? $candidate['metric_key'],
            'scope_label' => $candidate['scope_label'],
            'value' => $value,
            'previous_value' => $previous,
            'improvement_pct' => $improvement,
            'message' => $message,
            'achieved_at' => $achievedAt->toIso8601String(),
            'record_id' => $record->id,
        ];
    }

    /** §2.3 trigger copy — own-progress framing only, filled from real values. */
    private function message(array $definition, array $candidate, float $value, float $previous): string
    {
        $template = (string) ($definition['trigger_copy'] ?? ':value');

        return strtr($template, [
            ':value' => $this->format($value, (string) ($definition['format'] ?? 'count')),
            ':previous' => $this->format($previous, (string) ($definition['format'] ?? 'count')),
            ':scope' => (string) ($candidate['scope_label'] ?? 'this concept'),
        ]);
    }

    private function format(float $value, string $format): string
    {
        return match ($format) {
            'ratio' => number_format($value, 2),
            'days' => (string) (int) round($value),
            'minutes' => (string) (int) round($value),
            'sessions' => (string) (int) round($value),
            default => (string) (int) round($value),
        };
    }

    /**
     * The learner's whole personal-best board, grouped exactly the way §2.2
     * groups it: fluency / streak / mastery / session records.
     */
    public function board(int $learnerId): array
    {
        $this->refresh($learnerId);

        $definitions = (array) config('pal_gamification.personal_best.metrics', []);
        $records = PersonalBest::where('learner_id', $learnerId)->get();

        $groups = [];
        foreach ($records as $record) {
            $definition = $definitions[$record->metric_key] ?? null;
            if ($definition === null) {
                continue;
            }

            $group = (string) ($definition['group'] ?? 'other');
            $groups[$group]['group'] = $group;
            $groups[$group]['label'] = $this->groupLabel($group);
            $groups[$group]['records'][] = [
                'metric_key' => $record->metric_key,
                'label' => (string) ($definition['label'] ?? $record->metric_key),
                'format' => (string) ($definition['format'] ?? 'count'),
                'direction' => (string) ($definition['direction'] ?? 'higher'),
                'scope_type' => $record->scope_type,
                'scope_ref' => $record->scope_ref,
                'scope_label' => $record->scope_label,
                'best_value' => (float) $record->best_value,
                'best_achieved_at' => $record->best_achieved_at?->toIso8601String(),
                'previous_value' => $record->previous_value !== null ? (float) $record->previous_value : null,
                'previous_achieved_at' => $record->previous_achieved_at?->toIso8601String(),
                'improvement_pct' => $this->improvement($record),
                'context' => $record->context ?? [],
            ];
        }

        // Newest records first inside each group.
        foreach ($groups as $key => $group) {
            usort($groups[$key]['records'], fn ($a, $b) => strcmp((string) $b['best_achieved_at'], (string) $a['best_achieved_at']));
        }

        $recent = $this->history($learnerId, 5);

        return [
            'groups' => array_values($groups),
            'total_records' => $records->count(),
            'recent' => $recent['events'],
            // A first measurement is a BASELINE, not an achievement — saying
            // otherwise would celebrate a learner for simply having started.
            'headline' => match (true) {
                $recent['events'] !== [] => (string) $recent['events'][0]['message'],
                $records->isNotEmpty() => 'Your first records are set. From here, every one of them is yours to beat.',
                default => 'No personal bests yet — your first measured attempt sets your first record.',
            },
        ];
    }

    private function groupLabel(string $group): string
    {
        return match ($group) {
            'fluency_records' => 'Fluency records',
            'streak_records' => 'Streak records',
            'mastery_records' => 'Mastery records',
            'session_records' => 'Session records',
            default => ucfirst(str_replace('_', ' ', $group)),
        };
    }

    private function improvement(PersonalBest $record): ?float
    {
        $previous = $record->previous_value;
        if ($previous === null || (float) $previous == 0.0) {
            return null;
        }

        return round(((((float) $record->best_value) - (float) $previous) / (float) $previous) * 100, 2);
    }

    /** Every time this learner beat their own record, newest first (§2.3). */
    public function history(int $learnerId, int $limit = 50): array
    {
        $definitions = (array) config('pal_gamification.personal_best.metrics', []);

        $events = PersonalBestEvent::where('learner_id', $learnerId)
            ->orderByDesc('achieved_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(function (PersonalBestEvent $event) use ($definitions) {
                $definition = $definitions[$event->metric_key] ?? [];

                return [
                    'id' => $event->id,
                    'metric_key' => $event->metric_key,
                    'label' => (string) ($definition['label'] ?? $event->metric_key),
                    'format' => (string) ($definition['format'] ?? 'count'),
                    'scope_label' => $event->scope_label,
                    'value' => (float) $event->value,
                    'previous_value' => $event->previous_value !== null ? (float) $event->previous_value : null,
                    'improvement_pct' => $event->improvement_pct !== null ? (float) $event->improvement_pct : null,
                    'achieved_at' => $event->achieved_at?->toIso8601String(),
                    'message' => $this->message(
                        $definition,
                        ['scope_label' => $event->scope_label],
                        (float) $event->value,
                        (float) ($event->previous_value ?? 0)
                    ),
                ];
            })
            ->all();

        return ['events' => $events, 'total' => count($events)];
    }

    // =====================================================================
    // Measurements
    // =====================================================================

    /** @return array<string,int> tier => concept count */
    public function tierCounts(array $concepts): array
    {
        $counts = array_fill_keys(array_keys((array) config('pal_gamification.mastery_tiers', [])), 0);
        foreach ($concepts as $concept) {
            $tier = (string) $concept['tier'];
            $counts[$tier] = ($counts[$tier] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * How many sessions each concept took to first reach Mountain — replayed
     * over the real attempt sequence, so the answer is what actually happened
     * rather than a count of attempts made since.
     *
     * @return array<int,array<string,mixed>>
     */
    public function sessionsToMastery(int $learnerId, string $tier = 'mountain'): array
    {
        $threshold = (float) config("pal_gamification.mastery_tiers.{$tier}.min_mastery", 0.70);
        $out = [];

        foreach ($this->activity->attempts($learnerId)->groupBy('concept_ref') as $ref => $attempts) {
            $mastery = null;
            $sessions = 0;
            foreach ($attempts->values() as $attempt) {
                $sessions++;
                $mastery = $mastery === null
                    ? (float) $attempt['accuracy']
                    : $mastery + 0.5 * (((float) $attempt['accuracy']) - $mastery);

                if ($mastery >= $threshold) {
                    $out[] = [
                        'concept_ref' => (string) $ref,
                        'concept_label' => (string) $attempt['concept_label'],
                        'sessions' => $sessions,
                        'reached_at' => $attempt['occurred_at'],
                        'tier' => $tier,
                    ];
                    break;
                }
            }
        }

        return $out;
    }

    /** @return array<string,int> ISO week => distinct concepts practised */
    private function conceptsPerWeek(Collection $attempts): array
    {
        $weeks = [];
        foreach ($attempts as $attempt) {
            if ($attempt['occurred_at'] === null) {
                continue;
            }
            $week = $attempt['occurred_at']->format('o-\WW');
            $weeks[$week][$attempt['concept_ref']] = true;
        }

        return array_map('count', $weeks);
    }

    /**
     * The biggest single-attempt mastery movement the learner has made, using
     * the same running estimate the concept records use.
     */
    private function bestSingleSessionGain(Collection $attempts): ?array
    {
        $best = null;

        foreach ($attempts->groupBy('concept_ref') as $group) {
            $mastery = null;
            foreach ($group->values() as $attempt) {
                if ($mastery === null) {
                    $mastery = (float) $attempt['accuracy'];
                    continue;
                }
                $next = $mastery + 0.5 * (((float) $attempt['accuracy']) - $mastery);
                $delta = round($next - $mastery, 4);
                if ($delta > 0 && ($best === null || $delta > $best['value'])) {
                    $best = [
                        'value' => $delta,
                        'at' => $attempt['occurred_at'],
                        'concept_label' => (string) $attempt['concept_label'],
                    ];
                }
                $mastery = $next;
            }
        }

        return $best;
    }
}
