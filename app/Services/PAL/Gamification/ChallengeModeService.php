<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\ChallengeModeOptIn;
use App\Models\PAL\Gamification\ChallengeModeScore;
use App\Models\PAL\Gamification\ChallengeModeSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * New PAL → Gamification: Challenge Mode (document §6).
 *
 * This is the ONLY place in PAL V4 where one student is shown against another,
 * and the document fences it in hard. Every fence is implemented here rather
 * than assumed:
 *
 *   - strictly opt-in; absence of consent means no participation and no
 *     visibility, not a default-on with a hide button
 *   - Grade 4 and up only
 *   - a teacher can switch it off for a whole class (exam periods)
 *   - scores NEVER touch BKT mastery: they live in their own table and no
 *     mastery path reads it
 *   - the leaderboard shows the week's top five, first names only, and resets
 *     weekly so no ranking becomes permanent
 *   - opting out removes a learner from the display immediately
 *   - parents never see it
 */
class ChallengeModeService
{
    public function __construct(private readonly LearnerActivitySource $activity)
    {
    }

    /**
     * A learner's Challenge Mode state: whether they may take part at all,
     * whether they have chosen to, their own scores, and — only if both — the
     * week's leaderboard.
     */
    public function state(int $learnerId, string $audience): array
    {
        $learner = $this->activity->learner($learnerId);
        if ($learner === null) {
            return ['available' => false, 'reason' => 'learner_not_found'];
        }

        $cfg = (array) config('pal_gamification.challenge_mode', []);
        $eligibility = $this->eligibility($learner);
        $optIn = ChallengeModeOptIn::where('learner_id', $learnerId)->first();
        $optedIn = (bool) ($optIn?->opted_in);

        $weekStart = $this->weekStart();
        $ownScores = ChallengeModeScore::where('learner_id', $learnerId)
            ->orderByDesc('submitted_at')
            ->limit(50)
            ->get()
            ->map(fn (ChallengeModeScore $s) => $this->presentScore($s))
            ->all();

        $state = [
            'available' => $eligibility['eligible'],
            'eligibility' => $eligibility,
            'opted_in' => $optedIn,
            'opted_in_at' => $optIn?->opted_in_at?->toIso8601String(),
            'week_start' => $weekStart->toDateString(),
            'rules' => [
                'min_grade' => (int) ($cfg['min_grade'] ?? 4),
                'min_items_to_qualify' => (int) ($cfg['min_items_to_qualify'] ?? 5),
                'difficulty_min' => (int) ($cfg['difficulty_min'] ?? 4),
                'difficulty_max' => (int) ($cfg['difficulty_max'] ?? 5),
                'speed_bonus_cap' => (float) ($cfg['speed_bonus_cap'] ?? 2.0),
                'affects_mastery' => (bool) ($cfg['affects_bkt_mastery'] ?? false),
                'leaderboard_top_n' => (int) ($cfg['leaderboard']['top_n'] ?? 5),
                'leaderboard_reset' => (string) ($cfg['leaderboard']['reset'] ?? 'weekly'),
            ],
            'own_scores' => $ownScores,
            'best_score' => $ownScores === [] ? null : max(array_column($ownScores, 'score')),
        ];

        // A learner who has not opted in is never shown the leaderboard — not
        // even an empty one, which would still be an invitation to compare.
        if ($optedIn || in_array($audience, [GamificationVisibility::TEACHER, GamificationVisibility::ADMIN], true)) {
            $state['leaderboard'] = $this->leaderboard($learner, $audience, $weekStart);
        }

        return $state;
    }

    /**
     * Can this learner take part? Grade floor plus the class-level switch. Both
     * reasons are returned so the UI can explain rather than just disable.
     */
    public function eligibility(array $learner): array
    {
        $minGrade = (int) config('pal_gamification.challenge_mode.min_grade', 4);
        $grade = $learner['grade_number'];

        if ($grade === null) {
            return [
                'eligible' => false,
                'reason' => 'grade_unknown',
                'message' => 'Challenge Mode is available from Grade ' . $minGrade . '. This learner\'s grade could not be read from their class, so it stays off.',
                'min_grade' => $minGrade,
            ];
        }

        if ($grade < $minGrade) {
            return [
                'eligible' => false,
                'reason' => 'below_min_grade',
                'message' => 'Challenge Mode opens at Grade ' . $minGrade . '.',
                'min_grade' => $minGrade,
                'grade' => $grade,
            ];
        }

        $setting = $this->classSetting($learner);
        if ($setting !== null && ! $setting->enabled) {
            return [
                'eligible' => false,
                'reason' => 'disabled_for_class',
                'message' => $setting->disabled_reason
                    ?: 'A teacher has switched Challenge Mode off for this class.',
                'min_grade' => $minGrade,
                'grade' => $grade,
            ];
        }

        return ['eligible' => true, 'reason' => null, 'message' => null, 'min_grade' => $minGrade, 'grade' => $grade];
    }

    /** Most specific class setting wins: division → standard → institute. */
    private function classSetting(array $learner): ?ChallengeModeSetting
    {
        return ChallengeModeSetting::where('sub_institute_id', $learner['sub_institute_id'])
            ->where(function ($q) use ($learner) {
                $q->whereNull('standard_id')->orWhere('standard_id', $learner['standard_id']);
            })
            ->where(function ($q) use ($learner) {
                $q->whereNull('division_id')->orWhere('division_id', $learner['division_id']);
            })
            ->orderByRaw('division_id IS NULL, standard_id IS NULL')
            ->first();
    }

    /** The learner's one-time consent toggle. Reversible at any time. */
    public function setOptIn(int $learnerId, bool $optedIn): array
    {
        $learner = $this->activity->learner($learnerId);
        if ($learner === null) {
            return ['error' => 'Learner not found.'];
        }

        $eligibility = $this->eligibility($learner);
        if ($optedIn && ! $eligibility['eligible']) {
            return ['error' => $eligibility['message'] ?: 'Challenge Mode is not available for this learner.'];
        }

        $record = ChallengeModeOptIn::updateOrCreate(
            ['learner_id' => $learnerId],
            $optedIn
                ? ['opted_in' => true, 'opted_in_at' => now(), 'opted_out_at' => null]
                : ['opted_in' => false, 'opted_out_at' => now()]
        );

        return [
            'opted_in' => (bool) $record->opted_in,
            'opted_in_at' => $record->opted_in_at?->toIso8601String(),
            // §6.2 — opting out removes the learner from the display at once.
            'removed_from_leaderboard' => ! $optedIn,
        ];
    }

    /** Teacher switch for a whole class (§6.1). */
    public function setClassAvailability(array $scope, bool $enabled, int $updatedBy, string $reason = ''): ChallengeModeSetting
    {
        return ChallengeModeSetting::updateOrCreate(
            [
                'sub_institute_id' => $scope['sub_institute_id'],
                'syear' => $scope['syear'] ?? null,
                'standard_id' => $scope['standard_id'] ?? null,
                'division_id' => $scope['division_id'] ?? null,
            ],
            [
                'enabled' => $enabled,
                'updated_by' => $updatedBy,
                'disabled_reason' => $enabled ? null : ($reason ?: null),
            ]
        );
    }

    /**
     * Score a submitted Challenge Mode run.
     *
     * The formula is §6.3's, evaluated on the server from the submitted
     * responses. Difficulty and target time come from the real item metadata
     * (`pal_question_metadata`) where it exists — the client cannot inflate a
     * score by claiming a task was hard or that it should have taken longer.
     *
     * @param array<int,array<string,mixed>> $responses
     */
    public function submit(int $learnerId, array $responses, array $context = []): array
    {
        $learner = $this->activity->learner($learnerId);
        if ($learner === null) {
            return ['error' => 'Learner not found.'];
        }

        $eligibility = $this->eligibility($learner);
        if (! $eligibility['eligible']) {
            return ['error' => $eligibility['message'] ?: 'Challenge Mode is not available for this learner.'];
        }

        if (! ChallengeModeOptIn::where('learner_id', $learnerId)->where('opted_in', true)->exists()) {
            return ['error' => 'Challenge Mode is opt-in. This learner has not opted in.'];
        }

        $cfg = (array) config('pal_gamification.challenge_mode', []);
        $minItems = (int) ($cfg['min_items_to_qualify'] ?? 5);

        $responses = array_values(array_filter($responses, 'is_array'));
        if (count($responses) < $minItems) {
            return ['error' => "A Challenge Mode run needs at least {$minItems} items to qualify.", 'qualified' => false];
        }

        $metadata = $this->itemMetadata(array_column($responses, 'question_id'));

        $correct = 0;
        $totalTime = 0.0;
        $totalTarget = 0.0;
        $targetSamples = 0;
        $difficultySum = 0.0;
        $difficultySamples = 0;

        foreach ($responses as $response) {
            $questionId = (int) ($response['question_id'] ?? 0);
            $meta = $metadata[$questionId] ?? [];

            if (! empty($response['correct'])) {
                $correct++;
            }

            $totalTime += max(0.0, (float) ($response['time_seconds'] ?? 0));

            if (isset($meta['avg_time_seconds']) && $meta['avg_time_seconds'] > 0) {
                $totalTarget += (float) $meta['avg_time_seconds'];
                $targetSamples++;
            }

            if (isset($meta['difficulty']) && $meta['difficulty'] > 0) {
                $difficultySum += (float) $meta['difficulty'];
                $difficultySamples++;
            }
        }

        $count = count($responses);
        $accuracy = $correct / $count;
        $avgTime = $totalTime / $count;

        // No measured target pace means no speed bonus — never an assumed one.
        $speedRatio = ($targetSamples > 0 && $avgTime > 0)
            ? min(($totalTarget / $targetSamples) / $avgTime, (float) ($cfg['speed_bonus_cap'] ?? 2.0))
            : 1.0;

        $scaleMax = (float) ($cfg['difficulty_scale_max'] ?? 5);
        $difficultyCoeff = $difficultySamples > 0
            ? ($difficultySum / $difficultySamples) / $scaleMax
            : (float) ($cfg['difficulty_min'] ?? 4) / $scaleMax;

        $score = (int) round($accuracy * $speedRatio * $difficultyCoeff * (int) ($cfg['score_multiplier'] ?? 1000));

        $record = ChallengeModeScore::create([
            'learner_id' => $learnerId,
            'sub_institute_id' => $learner['sub_institute_id'],
            'syear' => $learner['syear'],
            'standard_id' => $learner['standard_id'],
            'division_id' => $learner['division_id'],
            'week_start' => $this->weekStart(),
            'concept_ref' => $context['concept_ref'] ?? null,
            'concept_label' => $context['concept_label'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'score' => $score,
            'accuracy_pct' => (int) round($accuracy * 100),
            'speed_bonus' => (int) round(($speedRatio - 1) * 100),
            // §6.3 reports the difficulty coefficient to one decimal place.
            'difficulty_rating' => round($difficultyCoeff * 10) / 10,
            'item_count' => $count,
            'duration_seconds' => (int) round($totalTime),
            'payload' => [
                'items' => $count,
                'correct' => $correct,
                'target_time_samples' => $targetSamples,
                'difficulty_samples' => $difficultySamples,
                'speed_ratio' => round($speedRatio, 4),
            ],
            'submitted_at' => now(),
        ]);

        return [
            'qualified' => true,
            'score' => $record->score,
            'accuracy_pct' => $record->accuracy_pct,
            'speed_bonus' => $record->speed_bonus,
            'difficulty_rating' => (float) $record->difficulty_rating,
            'items' => $count,
            // Stated on every submission so nobody has to trust the docs.
            'affects_mastery' => false,
            'note' => 'Challenge Mode scores are kept separate from your learning mastery.',
        ];
    }

    /** Item difficulty and measured pace from the real psychometrics table. */
    private function itemMetadata(array $questionIds): array
    {
        $questionIds = array_values(array_unique(array_filter(array_map('intval', $questionIds))));
        if ($questionIds === []) {
            return [];
        }

        return DB::table('pal_question_metadata')
            ->whereIn('question_id', $questionIds)
            ->select('question_id', 'difficulty_1_to_5', 'avg_time_seconds')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->question_id => [
                'difficulty' => $r->difficulty_1_to_5 !== null ? (float) $r->difficulty_1_to_5 : null,
                'avg_time_seconds' => $r->avg_time_seconds !== null ? (float) $r->avg_time_seconds : null,
            ]])
            ->all();
    }

    /**
     * The week's leaderboard (§6.2).
     *
     * Opted-in participants only, top five, first names for students, full
     * names for a teacher. Only scores from the current week are considered, so
     * no ranking cements.
     */
    public function leaderboard(array $learner, string $audience, ?Carbon $weekStart = null): array
    {
        $cfg = (array) config('pal_gamification.challenge_mode.leaderboard', []);
        $weekStart ??= $this->weekStart();
        $topN = (int) ($cfg['top_n'] ?? 5);

        // Parents never see Challenge Mode — the document is explicit that this
        // is to prevent external pressure.
        if ($audience === GamificationVisibility::PARENT) {
            return ['visible' => false, 'reason' => 'not_visible_to_parents', 'entries' => []];
        }

        $optedIn = ChallengeModeOptIn::where('opted_in', true)->pluck('learner_id');
        if ($optedIn->isEmpty()) {
            return ['visible' => true, 'week_start' => $weekStart->toDateString(), 'entries' => [], 'participants' => 0];
        }

        $rows = ChallengeModeScore::whereIn('learner_id', $optedIn)
            ->where('week_start', $weekStart)
            ->where('standard_id', $learner['standard_id'])
            ->when(! empty($learner['division_id']), fn ($q) => $q->where('division_id', $learner['division_id']))
            ->select('learner_id', DB::raw('MAX(score) AS best_score'))
            ->groupBy('learner_id')
            ->orderByDesc('best_score')
            ->limit($topN)
            ->get();

        $names = $this->namesFor($rows->pluck('learner_id')->all());
        $showFullNames = in_array($audience, [GamificationVisibility::TEACHER, GamificationVisibility::ADMIN], true);

        $entries = [];
        $position = 0;
        foreach ($rows as $row) {
            $position++;
            $name = $names[(int) $row->learner_id] ?? ['first_name' => 'Student', 'name' => 'Student'];
            $entries[] = [
                'position' => $position,
                'display_name' => $showFullNames ? $name['name'] : $name['first_name'],
                'score' => (int) $row->best_score,
                'is_you' => (int) $row->learner_id === (int) $learner['learner_id'],
                // A learner id only travels to a teacher, never to a classmate.
                'learner_id' => $showFullNames ? (int) $row->learner_id : null,
            ];
        }

        return [
            'visible' => true,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'top_n' => $topN,
            'first_names_only' => ! $showFullNames,
            'participants' => ChallengeModeScore::whereIn('learner_id', $optedIn)
                ->where('week_start', $weekStart)
                ->where('standard_id', $learner['standard_id'])
                ->distinct()
                ->count('learner_id'),
            'entries' => $entries,
        ];
    }

    private function namesFor(array $learnerIds): array
    {
        if ($learnerIds === []) {
            return [];
        }

        return DB::table('tblstudent')
            ->whereIn('id', $learnerIds)
            ->selectRaw("id, first_name, CONCAT_WS(' ', first_name, middle_name, last_name) AS name")
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->id => [
                'first_name' => trim((string) $r->first_name) ?: 'Student',
                'name' => trim((string) $r->name) ?: 'Student',
            ]])
            ->all();
    }

    private function presentScore(ChallengeModeScore $score): array
    {
        return [
            'id' => $score->id,
            'week_start' => $score->week_start?->toDateString(),
            'concept_label' => $score->concept_label,
            'score' => (int) $score->score,
            'accuracy_pct' => (int) $score->accuracy_pct,
            'speed_bonus' => (int) $score->speed_bonus,
            'difficulty_rating' => (float) $score->difficulty_rating,
            'items' => (int) $score->item_count,
            'submitted_at' => $score->submitted_at?->toIso8601String(),
        ];
    }

    private function weekStart(): Carbon
    {
        return Carbon::today()->startOfWeek(Carbon::MONDAY);
    }
}
