<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\GamificationNotification;
use App\Models\PAL\Gamification\TeamChallenge;
use App\Models\PAL\Gamification\TeamChallengeContribution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * New PAL → Gamification: team challenges (document §4).
 *
 * A team challenge exists because a leaderboard cannot do what it does: the
 * whole class wins or loses together, so a struggling learner's improvement
 * still counts toward the goal, nobody is exposed as the weakest, and the high
 * performers have a reason to help rather than to pull ahead.
 *
 * Progress is NEVER stored on the challenge row. It is recomputed from live
 * class data on every read, which is what lets the module promise that the bar
 * a class sees is the truth at that moment. Contribution rows are written as a
 * side effect of that recomputation so a teacher can act on individuals and a
 * student can be told "you have contributed" — and nothing more.
 */
class TeamChallengeService
{
    public function __construct(private readonly LearnerActivitySource $activity)
    {
    }

    // =====================================================================
    // Teacher operations
    // =====================================================================

    /**
     * Create a challenge. Always teacher-initiated (§4.3): there is no code
     * path in this module that generates one automatically.
     *
     * @param array<string,mixed> $input
     * @return array{challenge:TeamChallenge}|array{error:string}
     */
    public function create(array $input, int $teacherId, int $subInstituteId): array
    {
        $types = (array) config('pal_gamification.team_challenges.types', []);
        $type = (string) ($input['type'] ?? '');
        if (! isset($types[$type])) {
            return ['error' => 'Unknown challenge type. Choose one of: ' . implode(', ', array_keys($types)) . '.'];
        }

        $definition = $types[$type];

        $standardId = (int) ($input['standard_id'] ?? 0);
        if ($standardId <= 0) {
            return ['error' => 'A class (standard) is required — a challenge belongs to one class.'];
        }

        if (! empty($definition['requires_concept']) && empty($input['concept_ref'])) {
            return ['error' => 'This challenge type targets one concept, so concept_ref is required.'];
        }

        // §4.3 — at most two active challenges per class per week.
        $limit = (int) config('pal_gamification.team_challenges.max_active_per_class_per_week', 2);
        $activeCount = TeamChallenge::active()
            ->where('standard_id', $standardId)
            ->when(! empty($input['division_id']), fn ($q) => $q->where('division_id', $input['division_id']))
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
        if ($activeCount >= $limit) {
            return ['error' => "This class already has {$activeCount} active challenges this week. The limit is {$limit} — challenge fatigue is a real cost."];
        }

        $scope = [
            'sub_institute_id' => $subInstituteId,
            'syear' => $input['syear'] ?? null,
            'standard_id' => $standardId,
            'division_id' => $input['division_id'] ?? null,
        ];

        $challenge = TeamChallenge::create([
            'sub_institute_id' => $subInstituteId,
            'syear' => $input['syear'] ?? null,
            'grade_id' => $input['grade_id'] ?? null,
            'standard_id' => $standardId,
            'division_id' => $input['division_id'] ?? null,
            'teacher_id' => $teacherId,
            'type' => $type,
            'title' => (string) ($input['title'] ?: $definition['label']),
            'description' => $input['description'] ?? null,
            'subject_id' => $input['subject_id'] ?? null,
            'concept_ref' => $input['concept_ref'] ?? null,
            'concept_label' => $input['concept_label'] ?? null,
            'target_metric' => (string) $definition['target_metric'],
            'target_value' => (float) ($input['target_value'] ?? $definition['default_target_value']),
            'target_tier' => $input['target_tier'] ?? ($type === 'mastery_sprint' ? 'mountain' : null),
            // A "beat our own record" challenge needs the class's earlier value
            // captured at creation — otherwise the goalposts move underneath it.
            'baseline_value' => $type === 'collective_fluency'
                ? $this->classAverageFluency($scope, (string) ($input['concept_ref'] ?? ''), Carbon::now()->subDays(30), Carbon::now())
                : null,
            'deadline' => $input['deadline'] ?? null,
            'reward_title' => $input['reward_title'] ?? null,
            'reward_description' => $input['reward_description'] ?? null,
            'reward_content_id' => $input['reward_content_id'] ?? null,
            'reward_approved' => (bool) ($input['reward_approved'] ?? false),
            'status' => 'active',
        ]);

        return ['challenge' => $challenge];
    }

    /** @return array{challenge:TeamChallenge}|array{error:string} */
    public function update(TeamChallenge $challenge, array $input): array
    {
        if ($challenge->status !== 'active') {
            return ['error' => 'This challenge has already finished and can no longer be edited.'];
        }

        $challenge->fill(array_filter([
            'title' => $input['title'] ?? null,
            'description' => $input['description'] ?? null,
            'target_value' => $input['target_value'] ?? null,
            'target_tier' => $input['target_tier'] ?? null,
            'deadline' => $input['deadline'] ?? null,
            'reward_title' => $input['reward_title'] ?? null,
            'reward_description' => $input['reward_description'] ?? null,
            'reward_content_id' => $input['reward_content_id'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('reward_approved', $input)) {
            $challenge->reward_approved = (bool) $input['reward_approved'];
        }

        $challenge->save();

        return ['challenge' => $challenge->fresh()];
    }

    /**
     * End a challenge early. §4.3 names the reason this must exist: a teacher
     * who sees a challenge causing distress has to be able to stop it.
     */
    public function end(TeamChallenge $challenge, int $endedBy, string $reason = ''): TeamChallenge
    {
        $challenge->update([
            'status' => 'ended',
            'ended_at' => now(),
            'ended_by' => $endedBy,
            'ended_reason' => $reason ?: null,
        ]);

        return $challenge->fresh();
    }

    // =====================================================================
    // Progress
    // =====================================================================

    /**
     * Recompute one challenge's progress from live class data.
     *
     * Returns the class aggregate plus, separately, the per-learner detail. The
     * caller decides which half an audience may see — a student is only ever
     * handed the aggregate and their own row.
     */
    public function progress(TeamChallenge $challenge): array
    {
        $roster = $this->activity->classRoster([
            'sub_institute_id' => $challenge->sub_institute_id,
            'syear' => $challenge->syear,
            'standard_id' => $challenge->standard_id,
            'division_id' => $challenge->division_id,
        ]);

        $total = count($roster);
        $perLearner = [];

        foreach ($roster as $member) {
            $perLearner[] = $this->evaluateLearner($challenge, (int) $member['learner_id']) + [
                'learner_id' => (int) $member['learner_id'],
                'name' => $member['name'],
                'first_name' => $member['first_name'],
            ];
        }

        $qualified = collect($perLearner)->where('contributed', true);

        [$value, $unit, $achieved] = $this->aggregate($challenge, $perLearner, $total);

        $percent = $challenge->target_value > 0
            ? (int) round(min(100, ($value / (float) $challenge->target_value) * 100))
            : ($achieved ? 100 : 0);

        $this->persistContributions($challenge, $perLearner);

        if ($achieved && $challenge->status === 'active') {
            $this->markCompleted($challenge, $qualified->pluck('learner_id')->all());
        }

        return [
            'qualified' => $qualified->count(),
            'total' => $total,
            'value' => $value,
            'unit' => $unit,
            'target_value' => (float) $challenge->target_value,
            'percent' => $percent,
            'achieved' => $achieved,
            'remaining' => max(0, (int) ceil(((float) $challenge->target_value) - $value)),
            'per_learner' => $perLearner,
        ];
    }

    /**
     * How one learner stands against this challenge's goal. A learner is either
     * contributing or not — there is no per-learner score, because ranking
     * classmates against each other is exactly what this mechanic replaces.
     */
    private function evaluateLearner(TeamChallenge $challenge, int $learnerId): array
    {
        return match ($challenge->type) {
            'mastery_sprint' => $this->evaluateMastery($challenge, $learnerId),
            'collective_fluency' => $this->evaluateFluency($challenge, $learnerId),
            'peer_teaching' => $this->evaluatePeerTeaching($challenge, $learnerId),
            'exploration' => $this->evaluateExploration($challenge, $learnerId),
            default => ['contributed' => false, 'value' => 0.0, 'detail' => null],
        };
    }

    private function evaluateMastery(TeamChallenge $challenge, int $learnerId): array
    {
        $concepts = $this->activity->conceptRecords($learnerId);
        $record = $concepts[(string) $challenge->concept_ref] ?? null;
        if ($record === null) {
            return ['contributed' => false, 'value' => 0.0, 'detail' => 'not_started'];
        }

        $targetTier = (string) ($challenge->target_tier ?: 'mountain');
        $threshold = (float) config("pal_gamification.mastery_tiers.{$targetTier}.min_mastery", 0.70);

        return [
            'contributed' => (float) $record['mastery'] >= $threshold,
            'value' => (float) $record['mastery'],
            'detail' => (string) $record['tier'],
        ];
    }

    private function evaluateFluency(TeamChallenge $challenge, int $learnerId): array
    {
        $concepts = $this->activity->conceptRecords($learnerId);
        $record = $concepts[(string) $challenge->concept_ref] ?? null;
        $fluency = $record['best_net_fluency'] ?? null;

        return [
            'contributed' => $fluency !== null,
            'value' => (float) ($fluency ?? 0),
            'detail' => $fluency === null ? 'no_measured_fluency' : 'measured',
        ];
    }

    private function evaluatePeerTeaching(TeamChallenge $challenge, int $learnerId): array
    {
        $sessions = $this->activity->peerTeachingSessions($learnerId);

        return [
            'contributed' => $sessions > 0,
            'value' => (float) $sessions,
            'detail' => $sessions . ' session' . ($sessions === 1 ? '' : 's'),
        ];
    }

    private function evaluateExploration(TeamChallenge $challenge, int $learnerId): array
    {
        $opens = $this->activity->selfDirectedOpens($learnerId);

        return [
            'contributed' => $opens > 0,
            'value' => (float) $opens,
            'detail' => $opens . ' self-directed open' . ($opens === 1 ? '' : 's'),
        ];
    }

    /** @return array{0:float,1:string,2:bool} value, unit, achieved */
    private function aggregate(TeamChallenge $challenge, array $perLearner, int $total): array
    {
        $contributors = collect($perLearner)->where('contributed', true);

        return match ($challenge->type) {
            'mastery_sprint', 'exploration' => [
                $total > 0 ? round(($contributors->count() / $total) * 100, 2) : 0.0,
                'percent_of_class',
                $total > 0 && ($contributors->count() / $total) * 100 >= (float) $challenge->target_value,
            ],
            'peer_teaching' => [
                (float) collect($perLearner)->sum('value'),
                'sessions',
                collect($perLearner)->sum('value') >= (float) $challenge->target_value,
            ],
            'collective_fluency' => $this->aggregateFluency($challenge, $contributors),
            default => [0.0, 'value', false],
        };
    }

    /**
     * "Can we beat our own record as a class?" — the class average today
     * against the class average captured when the challenge began. Never
     * against another class or another school (§4.2 type 2).
     *
     * @return array{0:float,1:string,2:bool}
     */
    private function aggregateFluency(TeamChallenge $challenge, Collection $contributors): array
    {
        if ($contributors->isEmpty()) {
            return [0.0, 'average_fluency', false];
        }

        $average = round((float) $contributors->avg('value'), 4);
        $baseline = $challenge->baseline_value !== null ? (float) $challenge->baseline_value : null;
        $improvement = $baseline !== null ? round(($average - $baseline) * 100, 2) : 0.0;

        return [
            $average,
            'average_fluency',
            $baseline !== null && $improvement >= (float) $challenge->target_value,
        ];
    }

    /** The class's average net fluency on a concept over a window — the baseline. */
    private function classAverageFluency(array $scope, string $conceptRef, Carbon $from, Carbon $to): ?float
    {
        if ($conceptRef === '') {
            return null;
        }

        $roster = $this->activity->classRoster($scope);
        if ($roster === []) {
            return null;
        }

        $values = [];
        foreach ($this->activity->attemptsFor(array_column($roster, 'learner_id')) as $attempts) {
            $window = $attempts->filter(fn ($a) => $a['concept_ref'] === $conceptRef
                && $a['occurred_at'] !== null
                && $a['occurred_at']->between($from, $to));
            if ($window->isNotEmpty()) {
                $values[] = (float) $window->max('net_fluency');
            }
        }

        return $values === [] ? null : round(array_sum($values) / count($values), 4);
    }

    private function persistContributions(TeamChallenge $challenge, array $perLearner): void
    {
        foreach ($perLearner as $row) {
            $existing = TeamChallengeContribution::firstOrNew([
                'challenge_id' => $challenge->id,
                'learner_id' => $row['learner_id'],
            ]);

            if ($row['contributed'] && $existing->first_contributed_at === null) {
                $existing->first_contributed_at = now();
            }

            $existing->contributed = (bool) $row['contributed'];
            $existing->contribution_value = (float) $row['value'];
            $existing->evaluated_at = now();
            $existing->save();
        }
    }

    private function markCompleted(TeamChallenge $challenge, array $contributorIds): void
    {
        $challenge->update(['status' => 'completed', 'completed_at' => now()]);

        foreach ($contributorIds as $learnerId) {
            GamificationNotification::create([
                'learner_id' => (int) $learnerId,
                'type' => 'team_challenge',
                'level' => 'milestone',
                'title' => $challenge->title,
                'body' => 'Your class reached the goal together — and you were part of it.',
                'context' => [
                    'challenge_id' => $challenge->id,
                    'reward_title' => $challenge->reward_title,
                ],
            ]);
        }
    }

    // =====================================================================
    // Presentation
    // =====================================================================

    /**
     * Serialise a challenge for an audience.
     *
     * The student shape is deliberately narrow (§4.3): class aggregate, own
     * contribution, time remaining, and the reward. Which classmates have or
     * have not contributed is never in the payload — not hidden by the UI,
     * absent from the response.
     */
    public function present(TeamChallenge $challenge, string $audience, ?int $learnerId = null): array
    {
        $types = (array) config('pal_gamification.team_challenges.types', []);
        $definition = (array) ($types[$challenge->type] ?? []);
        $progress = $this->progress($challenge);

        $base = [
            'id' => $challenge->id,
            'type' => $challenge->type,
            'type_label' => (string) ($definition['label'] ?? $challenge->type),
            'type_summary' => (string) ($definition['summary'] ?? ''),
            'inclusive' => (bool) ($definition['inclusive'] ?? false),
            'title' => $challenge->title,
            'description' => $challenge->description,
            'concept_ref' => $challenge->concept_ref,
            'concept_label' => $challenge->concept_label,
            'target_metric' => $challenge->target_metric,
            'target_value' => (float) $challenge->target_value,
            'target_tier' => $challenge->target_tier,
            'deadline' => $challenge->deadline?->toDateString(),
            'days_remaining' => $challenge->deadline
                ? max(0, (int) Carbon::today()->diffInDays($challenge->deadline, false))
                : null,
            'status' => $challenge->status,
            'reward' => [
                'title' => $challenge->reward_title,
                'description' => $challenge->reward_description,
                'content_id' => $challenge->reward_content_id,
                'approved' => (bool) $challenge->reward_approved,
            ],
            'class_progress' => [
                'qualified' => $progress['qualified'],
                'total' => $progress['total'],
                'value' => $progress['value'],
                'unit' => $progress['unit'],
                'percent' => $progress['percent'],
                'achieved' => $progress['achieved'],
                'remaining' => $progress['remaining'],
                // The class-facing line from §4.2: a count, never names.
                'headline' => $this->classHeadline($challenge, $progress),
            ],
        ];

        if ($learnerId !== null) {
            $own = collect($progress['per_learner'])->firstWhere('learner_id', $learnerId);
            $base['own_contribution'] = [
                'contributed' => (bool) ($own['contributed'] ?? false),
                'detail' => $own['detail'] ?? null,
                'message' => ($own['contributed'] ?? false)
                    ? 'You have contributed to this goal.'
                    : 'You have not contributed yet — and you still can.',
            ];
        }

        // Per-student detail is teacher/admin only, for intervention (§4.3).
        if (in_array($audience, [GamificationVisibility::TEACHER, GamificationVisibility::ADMIN], true)) {
            $base['per_learner'] = $progress['per_learner'];
            $base['teacher_id'] = $challenge->teacher_id;
            $base['standard_id'] = $challenge->standard_id;
            $base['division_id'] = $challenge->division_id;
            $base['baseline_value'] = $challenge->baseline_value !== null ? (float) $challenge->baseline_value : null;
            $base['ended_reason'] = $challenge->ended_reason;
        }

        return $base;
    }

    private function classHeadline(TeamChallenge $challenge, array $progress): string
    {
        if ($progress['total'] === 0) {
            return 'No students are enrolled in this class yet.';
        }

        return match ($challenge->type) {
            'mastery_sprint' => "{$progress['qualified']} of {$progress['total']} students at "
                . ucfirst((string) ($challenge->target_tier ?: 'Mountain'))
                . ($progress['achieved'] ? ' — goal reached.' : ' — the class can still get there together.'),
            'exploration' => "{$progress['qualified']} of {$progress['total']} students have explored something of their own choosing.",
            'peer_teaching' => ((int) $progress['value']) . ' of ' . ((int) $challenge->target_value) . ' peer teaching sessions completed across the class.',
            'collective_fluency' => $challenge->baseline_value === null
                ? 'No class baseline was available when this challenge started, so there is nothing to beat yet.'
                : 'The class average is ' . number_format($progress['value'], 2)
                    . ' against its own earlier ' . number_format((float) $challenge->baseline_value, 2) . '.',
            default => "{$progress['qualified']} of {$progress['total']} students have contributed.",
        };
    }

    /** Active + recent challenges for a class, as the given audience sees them. */
    public function forClass(array $scope, string $audience, ?int $learnerId = null, bool $includeFinished = true): array
    {
        $challenges = TeamChallenge::query()
            ->when(! empty($scope['sub_institute_id']), fn ($q) => $q->where('sub_institute_id', $scope['sub_institute_id']))
            ->when(! empty($scope['standard_id']), fn ($q) => $q->where('standard_id', $scope['standard_id']))
            ->when(! empty($scope['division_id']), fn ($q) => $q->where(function ($inner) use ($scope) {
                $inner->where('division_id', $scope['division_id'])->orWhereNull('division_id');
            }))
            ->when(! $includeFinished, fn ($q) => $q->where('status', 'active'))
            ->orderByRaw("FIELD(status,'active','completed','ended')")
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $challenges->map(fn (TeamChallenge $c) => $this->present($c, $audience, $learnerId))->all();
    }

    /** The challenge types a teacher can pick from — served, never hard-coded in the UI. */
    public function types(): array
    {
        return array_values(array_map(fn ($key, $definition) => [
            'key' => $key,
            'label' => $definition['label'] ?? $key,
            'summary' => $definition['summary'] ?? '',
            'target_metric' => $definition['target_metric'] ?? '',
            'target_unit' => $definition['target_unit'] ?? '',
            'default_target_value' => $definition['default_target_value'] ?? 0,
            'requires_concept' => (bool) ($definition['requires_concept'] ?? false),
            'inclusive' => (bool) ($definition['inclusive'] ?? false),
        ], array_keys((array) config('pal_gamification.team_challenges.types', [])), array_values((array) config('pal_gamification.team_challenges.types', []))));
    }
}
