<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\GamificationNotification;
use App\Models\PAL\Gamification\PersonalBestEvent;
use Illuminate\Support\Carbon;

/**
 * New PAL → Gamification: the session summary and celebration layer (§8).
 *
 * §8.2's session summary has one job: show the learner the MOVEMENT. Before and
 * after, the specific thing they got right, what is next, the streak, the
 * career-quest step, and any badge earned. Every one of those is a fact read
 * from the session that actually happened — the wording may be shaped, the
 * numbers never are.
 *
 * §8.1's celebration budget is applied here too: one genuine celebration per
 * session. Everything else is downgraded to an inline note. Celebration
 * inflation is the failure mode that makes a reward system stop meaning
 * anything, and it is cheaper to prevent in one place than to police in the UI.
 */
class SessionSummaryService
{
    public function __construct(
        private readonly LearnerActivitySource $activity,
        private readonly StreakService $streaks,
        private readonly BadgeService $badges,
        private readonly PersonalBestService $personalBests,
        private readonly CareerQuestService $careerQuest,
    ) {
    }

    /**
     * Build the end-of-session summary for a learner.
     *
     * `date` defaults to the learner's most recent day of activity rather than
     * to today, so opening the summary the morning after still shows the
     * session it is about instead of an empty screen.
     */
    public function summary(int $learnerId, ?string $date = null): array
    {
        $attempts = $this->activity->attempts($learnerId);
        if ($attempts->isEmpty()) {
            return [
                'available' => false,
                'reason' => 'no_sessions_yet',
                'message' => 'There is no session to summarise yet. The summary appears after the first completed practice session.',
            ];
        }

        $day = $date !== null
            ? Carbon::parse($date)->toDateString()
            : $attempts->last()['occurred_at']?->toDateString();

        $sessionAttempts = $attempts->filter(
            fn ($a) => $a['occurred_at'] !== null && $a['occurred_at']->toDateString() === $day
        )->values();

        if ($sessionAttempts->isEmpty()) {
            return [
                'available' => false,
                'reason' => 'no_session_on_date',
                'message' => 'No practice was recorded on ' . $day . '.',
                'date' => $day,
            ];
        }

        $concepts = $this->activity->conceptRecords($learnerId);
        $worked = $this->conceptMovement($sessionAttempts, $attempts, $concepts, $day);

        $streak = $this->streaks->summary($learnerId);
        $badgeState = $this->badges->collection($learnerId);
        $earnedToday = array_values(array_filter(
            $badgeState['earned'],
            fn ($badge) => collect($badge['awards'])->contains(
                fn ($award) => $award['awarded_at'] !== null && Carbon::parse($award['awarded_at'])->toDateString() === $day
            )
        ));

        $recordsToday = PersonalBestEvent::where('learner_id', $learnerId)
            ->whereDate('achieved_at', $day)
            ->orderByDesc('achieved_at')
            ->get()
            ->map(fn (PersonalBestEvent $e) => [
                'metric_key' => $e->metric_key,
                'scope_label' => $e->scope_label,
                'value' => (float) $e->value,
                'previous_value' => $e->previous_value !== null ? (float) $e->previous_value : null,
                'improvement_pct' => $e->improvement_pct !== null ? (float) $e->improvement_pct : null,
            ])
            ->all();

        $questProgress = $this->careerQuest->progress($learnerId);

        return [
            'available' => true,
            'date' => $day,
            'session' => [
                'attempts' => $sessionAttempts->count(),
                'items' => (int) $sessionAttempts->sum('items'),
                'minutes' => round($sessionAttempts->sum('duration_seconds') / 60, 1),
                'concepts_worked' => array_values(array_unique($sessionAttempts->pluck('concept_label')->all())),
            ],
            'progress' => $worked,
            // §8.2 "one specific praise" — anchored to the strongest measured
            // thing in this session, never a generic compliment.
            'specific_praise' => $this->praise($sessionAttempts, $worked),
            'upcoming' => $this->upcoming($learnerId, $concepts, $sessionAttempts),
            'streak' => [
                'current_streak' => $streak['current_streak'],
                'headline' => $streak['headline'],
                'active_today' => $streak['active_today'],
            ],
            'career_quest' => [
                'pathway_label' => $questProgress['pathway_label'] ?? null,
                'skill_progress' => $questProgress['skill_progress'] ?? null,
            ],
            // §8.2 — no badges earned means no mention. Absence is never
            // called out, because calling it out is what makes it a punishment.
            'badges_earned' => $earnedToday,
            'personal_bests' => $recordsToday,
            'celebration' => $this->celebration($worked, $earnedToday, $recordsToday, $streak),
        ];
    }

    /**
     * Before / after mastery for each concept touched in the session. The
     * movement IS the reward, so it is computed from the real attempt sequence:
     * mastery as it stood before the session's first attempt, and as it stands
     * after the last.
     */
    private function conceptMovement($sessionAttempts, $allAttempts, array $concepts, string $day): array
    {
        $out = [];

        foreach ($sessionAttempts->groupBy('concept_ref') as $ref => $group) {
            $history = $allAttempts->filter(fn ($a) => $a['concept_ref'] === $ref)->values();

            $before = null;
            $after = null;
            $mastery = null;
            foreach ($history as $attempt) {
                $isSessionDay = $attempt['occurred_at'] !== null
                    && $attempt['occurred_at']->toDateString() === $day;

                if ($isSessionDay && $before === null) {
                    // Leave null when there is no prior estimate. Defaulting to
                    // 0.0 manufactured growth out of nothing: a learner whose
                    // first ever attempt was in this session would show a rise
                    // from "zero" they were never measured at. Callers suppress
                    // the growth statement when `before` is null (ADR-001 §5.2).
                    $before = $mastery;
                }

                $mastery = $mastery === null
                    ? (float) $attempt['accuracy']
                    : $mastery + 0.5 * (((float) $attempt['accuracy']) - $mastery);

                if ($isSessionDay) {
                    $after = $mastery;
                }
            }

            $record = $concepts[(string) $ref] ?? null;
            $tierBefore = $before !== null ? $this->activity->tierFor($before) : null;
            $tierAfter = $after !== null ? $this->activity->tierFor($after) : null;

            $out[] = [
                'concept_ref' => (string) $ref,
                'concept_label' => (string) $group->first()['concept_label'],
                'subject_name' => (string) $group->first()['subject_name'],
                'mastery_before' => $before !== null ? round($before, 4) : null,
                'mastery_after' => $after !== null ? round($after, 4) : null,
                'delta' => ($before !== null && $after !== null) ? round($after - $before, 4) : null,
                'tier_before' => $tierBefore,
                'tier_after' => $tierAfter,
                'tier_changed' => $tierBefore !== null && $tierAfter !== null && $tierBefore !== $tierAfter,
                'best_net_fluency' => $record['best_net_fluency'] ?? null,
                'items' => (int) $group->sum('items'),
                'accuracy' => round((float) $group->avg('accuracy'), 4),
            ];
        }

        return $out;
    }

    /** The most specific true thing that can be said about this session. */
    private function praise($sessionAttempts, array $worked): ?string
    {
        $tierUp = collect($worked)->first(fn ($c) => $c['tier_changed'] && $c['tier_after'] !== 'stream');
        if ($tierUp !== null) {
            $label = (string) config("pal_gamification.mastery_tiers.{$tierUp['tier_after']}.label", $tierUp['tier_after']);

            return "You reached {$label} on {$tierUp['concept_label']}. Remember when this felt impossible?";
        }

        $biggest = collect($worked)->filter(fn ($c) => $c['delta'] !== null && $c['delta'] > 0)->sortByDesc('delta')->first();
        if ($biggest !== null) {
            return "Your grip on {$biggest['concept_label']} moved forward this session — that is the whole point of showing up.";
        }

        $best = $sessionAttempts->sortByDesc('accuracy')->first();
        if ($best !== null && (float) $best['accuracy'] >= 0.6) {
            return 'You got ' . $best['right'] . ' of ' . $best['items'] . " right on {$best['concept_label']}. That is real work.";
        }

        return 'You worked on something hard today. That is how the difficult things stop being difficult.';
    }

    /** What comes next — the next concept and anything due for review. */
    private function upcoming(int $learnerId, array $concepts, $sessionAttempts): array
    {
        $touchedToday = $sessionAttempts->pluck('concept_ref')->unique()->all();

        // The weakest concept the learner has already met is the honest "next".
        $next = collect($concepts)
            ->reject(fn ($c) => in_array($c['concept_ref'], $touchedToday, true))
            ->sortBy('mastery')
            ->first();

        // Review due: strong once, but untouched for a while.
        $reviewThreshold = (float) config('pal_gamification.mastery_tiers.mountain.min_mastery', 0.70);
        $review = collect($concepts)
            ->filter(fn ($c) => (float) $c['mastery'] >= $reviewThreshold && $c['last_seen_at'] !== null)
            ->sortBy(fn ($c) => $c['last_seen_at']->timestamp)
            ->first();

        return [
            'next_concept' => $next === null ? null : [
                'concept_ref' => $next['concept_ref'],
                'concept_label' => $next['concept_label'],
                'tier' => $next['tier'],
            ],
            'review_due' => $review === null ? null : [
                'concept_ref' => $review['concept_ref'],
                'concept_label' => $review['concept_label'],
                'days_since' => (int) $review['last_seen_at']->diffInDays(Carbon::today()),
            ],
        ];
    }

    /**
     * Pick the ONE celebration this session gets (§8.1).
     *
     * The hierarchy is strict: a badge or personal best outranks a tier change,
     * which outranks a streak milestone. Everything not chosen is returned as a
     * quiet note so nothing is lost — it just is not a fanfare.
     */
    private function celebration(array $worked, array $badgesEarned, array $records, array $streak): array
    {
        $candidates = [];

        foreach ($badgesEarned as $badge) {
            $candidates[] = [
                'level' => 'large',
                'kind' => 'badge',
                'title' => $badge['name'],
                'message' => (string) ($badge['awards'][0]['student_message'] ?? $badge['description']),
            ];
        }

        foreach ($records as $record) {
            $candidates[] = [
                'level' => 'large',
                'kind' => 'personal_best',
                'title' => 'Personal best',
                'message' => 'You beat your own record' . ($record['scope_label'] ? " on {$record['scope_label']}" : '') . '.',
            ];
        }

        foreach ($worked as $concept) {
            if ($concept['tier_changed'] && $concept['tier_after'] !== 'stream') {
                $label = (string) config("pal_gamification.mastery_tiers.{$concept['tier_after']}.label", $concept['tier_after']);
                $candidates[] = [
                    'level' => 'medium',
                    'kind' => 'tier',
                    'title' => "{$label} on {$concept['concept_label']}",
                    'message' => (string) config("pal_gamification.mastery_tiers.{$concept['tier_after']}.student_message", ''),
                ];
            }
        }

        if (in_array($streak['current_streak'], (array) config('pal_gamification.streak.milestones', []), true)) {
            $candidates[] = [
                'level' => 'milestone',
                'kind' => 'streak',
                'title' => "Day {$streak['current_streak']}",
                'message' => $streak['headline'],
            ];
        }

        $rank = ['large' => 3, 'medium' => 2, 'milestone' => 1, 'small' => 0];
        usort($candidates, fn ($a, $b) => ($rank[$b['level']] ?? 0) <=> ($rank[$a['level']] ?? 0));

        $budget = (int) config('pal_gamification.celebration.max_per_session', 1);

        return [
            'primary' => $candidates[0] ?? null,
            'quiet_notes' => array_slice($candidates, $budget),
            'budget' => $budget,
        ];
    }

    /**
     * The notification queue (§8.1 "milestone" level: a card at session start,
     * never an interruption mid-session).
     */
    public function notifications(int $learnerId, bool $unreadOnly = true, int $limit = 20): array
    {
        $items = GamificationNotification::where('learner_id', $learnerId)
            ->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (GamificationNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'level' => $n->level,
                'title' => $n->title,
                'body' => $n->body,
                'context' => $n->context ?? [],
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'items' => $items,
            'unread' => GamificationNotification::where('learner_id', $learnerId)->whereNull('read_at')->count(),
        ];
    }

    public function markNotificationsRead(int $learnerId, array $ids = []): int
    {
        return GamificationNotification::where('learner_id', $learnerId)
            ->whereNull('read_at')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->update(['read_at' => now()]);
    }
}
