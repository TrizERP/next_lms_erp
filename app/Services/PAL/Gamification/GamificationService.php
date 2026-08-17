<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\GamificationNotification;
use Illuminate\Support\Facades\DB;

/**
 * New PAL → Gamification: the module's front door.
 *
 * One call assembles the overview screen — personal bests, streak, badges,
 * team challenges, career quest and Challenge Mode — from the six feature
 * services, then hands the whole thing to GamificationVisibility so the §9
 * matrix decides what actually leaves the server for this audience.
 *
 * Nothing is composed here that a feature service could not answer on its own;
 * this class exists so that "what a learner's gamification looks like right
 * now" has exactly one definition.
 */
class GamificationService
{
    public function __construct(
        private readonly LearnerActivitySource $activity,
        private readonly PersonalBestService $personalBests,
        private readonly StreakService $streaks,
        private readonly BadgeService $badges,
        private readonly TeamChallengeService $teamChallenges,
        private readonly CareerQuestService $careerQuest,
        private readonly ChallengeModeService $challengeMode,
        private readonly GamificationVisibility $visibility,
    ) {
    }

    /**
     * The overview payload.
     *
     * @return array<string,mixed>
     */
    public function overview(int $learnerId, string $audience): array
    {
        $learner = $this->activity->learner($learnerId);
        if ($learner === null) {
            return ['available' => false, 'reason' => 'learner_not_found'];
        }

        $concepts = $this->activity->conceptRecords($learnerId);
        $tierCounts = $this->personalBests->tierCounts($concepts);

        // Order matters: bests and badges are evaluated before they are read,
        // so an overview is never a stale snapshot of an earlier visit.
        $newRecords = $this->personalBests->refresh($learnerId);
        $newBadges = $this->badges->evaluate($learnerId);

        $payload = [
            'available' => true,
            'learner' => [
                'learner_id' => $learner['learner_id'],
                'name' => $learner['name'],
                'first_name' => $learner['first_name'],
                'grade_number' => $learner['grade_number'],
                'standard_name' => $learner['standard_name'],
                'division_name' => $learner['division_name'],
            ],
            'mastery' => $this->masterySection($concepts, $tierCounts),
            'personal_bests' => $this->personalBests->board($learnerId),
            'streak' => $this->streaks->summary($learnerId),
            'badges' => $this->badges->collection($learnerId),
            'team_challenges' => $this->teamChallenges->forClass([
                'sub_institute_id' => $learner['sub_institute_id'],
                'standard_id' => $learner['standard_id'],
                'division_id' => $learner['division_id'],
            ], $audience, $learnerId, false),
            'career_quest' => $this->careerQuest->quest($learnerId),
            'challenge_mode' => $this->challengeMode->state($learnerId, $audience),
            'class_aggregate' => $this->classAggregate($learner, $audience),
            'new_this_visit' => [
                'personal_bests' => $newRecords,
                'badges' => $newBadges,
            ],
            'unread_notifications' => GamificationNotification::where('learner_id', $learnerId)
                ->whereNull('read_at')
                ->count(),
            'data_sources' => $this->dataSources($learnerId, $concepts),
        ];

        return $this->visibility->filterLearnerPayload($payload, $audience);
    }

    /** Mastery, in the Stream → Mountain → Sky language the learner sees. */
    private function masterySection(array $concepts, array $tierCounts): array
    {
        $tiers = (array) config('pal_gamification.mastery_tiers', []);

        $ordered = collect($concepts)
            ->sortByDesc('mastery')
            ->values()
            ->map(fn ($c) => [
                'concept_ref' => $c['concept_ref'],
                'concept_label' => $c['concept_label'],
                'subject_name' => $c['subject_name'],
                'mastery' => (float) $c['mastery'],
                'mastery_source' => $c['mastery_source'],
                'tier' => $c['tier'],
                'tier_label' => (string) ($tiers[$c['tier']]['label'] ?? ucfirst((string) $c['tier'])),
                'sessions' => (int) $c['sessions'],
                'best_net_fluency' => $c['best_net_fluency'],
                'latest_net_fluency' => $c['latest_net_fluency'],
                'last_seen_at' => $c['last_seen_at']?->toIso8601String(),
            ])
            ->all();

        $skyCount = (int) ($tierCounts['sky'] ?? 0);
        $mountainCount = (int) ($tierCounts['mountain'] ?? 0);

        return [
            'concepts_tracked' => count($concepts),
            'tier_counts' => array_map('intval', $tierCounts),
            'tiers' => array_values(array_map(fn ($key, $tier) => [
                'key' => $key,
                'label' => $tier['label'] ?? $key,
                'min_mastery' => (float) ($tier['min_mastery'] ?? 0),
                'student_message' => $tier['student_message'] ?? '',
                'count' => (int) ($tierCounts[$key] ?? 0),
            ], array_keys($tiers), array_values($tiers))),
            'concepts' => $ordered,
            'headline' => match (true) {
                $concepts === [] => 'No concepts practised yet — your first practice session starts the map.',
                $skyCount > 0 => "{$skyCount} concept" . ($skyCount === 1 ? '' : 's') . ' at Sky, ' . $mountainCount . ' at Mountain.',
                $mountainCount > 0 => "{$mountainCount} concept" . ($mountainCount === 1 ? '' : 's') . ' at Mountain — Sky is the next ridge.',
                default => 'Everything is at Stream right now. That is where every concept starts.',
            },
            'recent_milestones' => array_slice(array_filter($ordered, fn ($c) => $c['tier'] !== 'stream'), 0, 5),
        ];
    }

    /**
     * Class aggregate — the ONLY cross-learner number a student may see, and
     * only ever as an anonymous aggregate (§9.1). No names, no ranking, no
     * "you are Nth".
     */
    private function classAggregate(array $learner, string $audience): array
    {
        if (! $this->visibility->allows('class_aggregate_mastery', $audience)) {
            return [];
        }

        $roster = $this->activity->classmates($learner);
        if ($roster === []) {
            return ['class_size' => 0, 'learners_with_activity' => 0, 'tier_counts' => [], 'available' => false];
        }

        $learnerIds = array_column($roster, 'learner_id');
        $attemptsByLearner = $this->activity->attemptsFor($learnerIds);

        $tierCounts = array_fill_keys(array_keys((array) config('pal_gamification.mastery_tiers', [])), 0);
        $withActivity = 0;

        foreach ($attemptsByLearner as $attempts) {
            if ($attempts->isEmpty()) {
                continue;
            }
            $withActivity++;
            foreach ($attempts->groupBy('concept_ref') as $group) {
                $mastery = null;
                foreach ($group->values() as $attempt) {
                    $mastery = $mastery === null
                        ? (float) $attempt['accuracy']
                        : $mastery + 0.5 * (((float) $attempt['accuracy']) - $mastery);
                }
                $tier = $this->activity->tierFor((float) $mastery);
                $tierCounts[$tier] = ($tierCounts[$tier] ?? 0) + 1;
            }
        }

        return [
            'available' => true,
            'class_size' => count($roster),
            'learners_with_activity' => $withActivity,
            'tier_counts' => $tierCounts,
            'note' => 'Class figures are aggregates only. No student is named and no ranking exists.',
        ];
    }

    /**
     * Where the numbers on this screen came from.
     *
     * Surfaced deliberately: a gamification layer that cannot name its evidence
     * is indistinguishable from one that invented it, and this module's whole
     * claim is that it never does.
     */
    private function dataSources(int $learnerId, array $concepts): array
    {
        $attempts = $this->activity->attempts($learnerId);
        $bySource = [];
        foreach ($concepts as $concept) {
            $key = (string) $concept['mastery_source'];
            $bySource[$key] = ($bySource[$key] ?? 0) + 1;
        }

        return [
            'pal_attempts' => $attempts->count(),
            'first_attempt_at' => $attempts->first()['occurred_at']?->toIso8601String(),
            'last_attempt_at' => $attempts->last()['occurred_at']?->toIso8601String(),
            'concepts_by_mastery_source' => $bySource,
            'tables' => array_values(array_filter([
                'question_paper + lms_online_exam (exam_type = PAL)',
                $bySource['pal_competencies'] ?? false ? 'pal_competencies' : null,
                DB::table('pal_framework_progress')->where('learner_id', $learnerId)->exists() ? 'pal_framework_progress' : null,
                DB::table('pal_telemetry_events')->where('actor_id', $learnerId)->exists() ? 'pal_telemetry_events' : null,
                DB::table('pal_learning_sessions')->where('learner_id', $learnerId)->exists() ? 'pal_learning_sessions' : null,
            ])),
        ];
    }

    /**
     * The module's shipped specification: mastery tiers, badge categories,
     * challenge types, career stages, celebration levels and the visibility
     * matrix. The UI renders these rather than restating them, so a change to
     * the spec is a backend-only change.
     */
    public function specification(string $audience): array
    {
        return [
            'mastery_tiers' => array_values((array) config('pal_gamification.mastery_tiers', [])),
            'badge_categories' => array_map(fn ($key, $meta) => [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'blurb' => $meta['blurb'] ?? '',
            ], array_keys((array) config('pal_gamification.badge_categories', [])), array_values((array) config('pal_gamification.badge_categories', []))),
            'team_challenge_types' => $this->teamChallenges->types(),
            'career_stages' => array_values((array) config('pal_gamification.career_quest.stages', [])),
            'celebration_levels' => array_map(fn ($key, $meta) => ['key' => $key] + $meta, array_keys((array) config('pal_gamification.celebration.levels', [])), array_values((array) config('pal_gamification.celebration.levels', []))),
            'personal_best_metrics' => array_values((array) config('pal_gamification.personal_best.metrics', [])),
            'streak_rules' => (array) config('pal_gamification.streak', []),
            'challenge_mode_rules' => (array) config('pal_gamification.challenge_mode', []),
            'visibility' => $this->visibility->describe($audience),
            'design_principle' => 'Every mechanic must make a struggling student feel more capable, not less.',
        ];
    }
}
