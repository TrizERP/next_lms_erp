<?php

namespace App\Services\PAL\Adaptive;

use App\Services\PAL\Questions\DifficultyBands;
use App\Services\PAL\Questions\McqPool;
use App\Services\PAL\Questions\ServableQuestions;
use Illuminate\Support\Facades\DB;

/**
 * Serves adaptive practice for one learner on one concept, and records the
 * answers.
 *
 * Called from palController::adaptiveQuestions / adaptiveAnswer /
 * adaptiveProgress. Difficulty is never chosen here - that is
 * AdaptiveDifficultyRule's job; this class supplies it with signals, then
 * fetches whatever it asked for.
 */
class AdaptiveLearningService
{
    /** Questions per practice set. */
    public const DEFAULT_LIMIT = 5;

    /** Answered at hard, with this accuracy, counts as mastered. */
    private const MASTERY_MIN_HARD = 5;
    private const MASTERY_ACCURACY = 80.0;

    public function __construct(
        private ConceptPerformanceAnalyzer $analyzer = new ConceptPerformanceAnalyzer(),
        private AdaptiveDifficultyRule $rule = new AdaptiveDifficultyRule(),
    ) {
    }

    /**
     * The next practice set.
     *
     * @return array{items: array, difficulty: ?string, exhausted: bool, progress: array, reason?: string}
     */
    public function questions($studentId, int $conceptId, $subInstituteId, $limit = self::DEFAULT_LIMIT): array
    {
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'name', 'chapter_id']);

        if ($concept === null) {
            return [
                'items' => [], 'difficulty' => null, 'exhausted' => true,
                'progress' => $this->progress($studentId, $conceptId),
                'reason' => 'unknown_concept',
            ];
        }

        $limit = max(1, (int) $limit);
        $scope = $this->scope($conceptId, (int) $concept->chapter_id, $subInstituteId);

        $signals = $this->analyzer->signalsForConcept($studentId, $conceptId, $subInstituteId, $scope['availability']);
        $decision = $this->rule->decide($signals);

        if ($decision['difficulty'] === null) {
            return [
                'items' => [], 'difficulty' => null, 'exhausted' => true,
                'progress' => $this->progress($studentId, $conceptId),
                'rule_fired' => $decision['rule_fired'],
                'rationale' => $decision['rationale'],
                'concept_exact' => $scope['exact'],
            ];
        }

        $answered = DB::table('pal_adaptive_response')
            ->where('student_id', (int) $studentId)
            ->where('concept_id', $conceptId)
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $picked = $this->fill($scope, $subInstituteId, $decision['difficulty'], $answered, $studentId, $limit);
        $recycled = false;
        $exhausted = false;

        // Everything in this concept has been seen. A blank screen is the
        // worst possible answer, so re-serve the oldest answers instead and
        // say plainly that this is a second pass.
        if ($picked === [] && $answered !== []) {
            $recycled = true;
            $exhausted = true;
            $picked = $this->fill($scope, $subInstituteId, $decision['difficulty'], [], $studentId, $limit);
        }

        $items = [];

        foreach ($picked as $entry) {
            // Options without the answer key; correctness is resolved
            // server-side in recordAnswer().
            $item = ServableQuestions::hydrate((int) $entry['id']);

            if ($item === null) {
                continue;
            }

            $items[] = $item + [
                // The band this question ACTUALLY came from, not the band the
                // rule asked for. They differ whenever the target band ran out
                // and the set was topped up from a neighbour, and recordAnswer()
                // stores what was really served.
                'difficulty' => $entry['band'],
                'difficulty_source' => $entry['tagged'] ? 'resolved' : 'untagged',
                'concept_exact' => $scope['exact'],
                'borrowed' => $entry['band'] !== $decision['difficulty'],
            ];
        }

        $bandsServed = [];
        foreach ($items as $item) {
            $bandsServed[$item['difficulty']] = ($bandsServed[$item['difficulty']] ?? 0) + 1;
        }

        return [
            'items' => $items,
            // The band the RULE chose - the set's centre of gravity. Individual
            // items may sit either side of it; `bands_served` is the breakdown.
            'difficulty' => $decision['difficulty'],
            'bands_served' => $bandsServed,
            'requested' => $limit,
            'exhausted' => $exhausted || $items === [],
            'progress' => $this->progress($studentId, $conceptId),
            'rule_fired' => $decision['rule_fired'],
            'rationale' => $decision['rationale'],
            'concept_exact' => $scope['exact'],
            'recycled' => $recycled,
            'concept_name' => $concept->name,
        ];
    }

    /**
     * Record one answer and say what comes next.
     *
     * The difficulty and rule are re-derived BEFORE the row is written. At
     * that moment the history is exactly the history that produced the
     * question the learner just answered, so what gets stored is true
     * provenance. Deriving it afterwards would read a history that already
     * includes this answer, and taking it from the request would let a
     * replayed post claim any band it liked.
     */
    public function recordAnswer($studentId, int $conceptId, $subInstituteId, int $questionId, ?int $answerMasterId): array
    {
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'chapter_id']);

        if ($concept === null) {
            return ['status' => 0, 'message' => 'Unknown concept.'];
        }

        // The option must belong to the question it was submitted against.
        $option = $answerMasterId !== null
            ? DB::table('answer_master')->where('id', $answerMasterId)->first(['id', 'question_id', 'correct_answer', 'feedback'])
            : null;

        if ($option === null || (int) $option->question_id !== $questionId) {
            return ['status' => 0, 'message' => 'Invalid option for this question.'];
        }

        $scope = $this->scope($conceptId, (int) $concept->chapter_id, $subInstituteId);
        $signals = $this->analyzer->signalsForConcept($studentId, $conceptId, $subInstituteId, $scope['availability']);
        $served = $this->rule->decide($signals);

        $isCorrect = (int) $option->correct_answer === 1;

        DB::table('pal_adaptive_response')->updateOrInsert(
            [
                'student_id' => (int) $studentId,
                'concept_id' => $conceptId,
                'question_id' => $questionId,
            ],
            [
                'sub_institute_id' => (int) $subInstituteId,
                'answer_master_id' => $answerMasterId,
                'is_correct' => $isCorrect ? 1 : 0,
                // The band THIS QUESTION really carries, resolved server-side,
                // not the band the rule was aiming at. fill() tops a short set
                // up from neighbouring bands, so the two genuinely differ - and
                // crediting the ladder for a band the learner never answered
                // would make mastery a lie. Falls back to the rule's band only
                // for a question carrying no difficulty signal at all.
                'difficulty_served' => $this->bandForQuestion($questionId, $subInstituteId)
                    ?? $served['difficulty']
                    ?? DifficultyBands::EASY,
                'difficulty_source' => $this->bandForQuestion($questionId, $subInstituteId) !== null ? 'resolved' : 'rule',
                'concept_exact' => $scope['exact'] ? 1 : 0,
                'rule_fired' => $served['rule_fired'],
                'created_at' => now(),
            ]
        );

        // Recomputed AFTER the write, so the learner is told where the next
        // set will sit given the answer they just gave.
        $next = $this->rule->decide(
            $this->analyzer->signalsForConcept($studentId, $conceptId, $subInstituteId, $scope['availability'])
        );

        $correctId = DB::table('answer_master')
            ->where('question_id', $questionId)
            ->where('correct_answer', 1)
            ->value('id');

        return [
            'status' => 1,
            'is_correct' => $isCorrect,
            'correct_answer_id' => $correctId !== null ? (int) $correctId : null,
            // The bank's own rationale for the chosen option - this is the
            // misconception text, and it is the whole point of practice.
            'feedback' => $option->feedback ?: null,
            'served_difficulty' => $served['difficulty'],
            'next_difficulty' => $next['difficulty'],
            'rule_fired' => $next['rule_fired'],
            'rationale' => $next['rationale'],
            'progress' => $this->progress($studentId, $conceptId),
        ];
    }

    /**
     * The Concept Diagnostic Result: where this learner stands on one concept
     * after practice, and what should happen next.
     *
     * ---------------------------------------------------------------------
     * WHY THIS IS A PROJECTION AND NOT A STORED ROW
     * ---------------------------------------------------------------------
     * Every input already exists and is already persisted: the answers are in
     * pal_adaptive_response, the diagnostic band is on the attempt, and the
     * next difficulty is whatever AdaptiveDifficultyRule says about that
     * history. Storing a snapshot would create a second version of the truth
     * that goes stale the moment the learner answers again.
     *
     * So this is a pure read. It can be called as often as the UI likes, and
     * it can never disagree with the evidence it is derived from.
     *
     * `mastered` here is the LADDER verdict (see MasteryLadder), not the
     * hard-band-only rule that progress() reports. progress() keeps its own
     * flag untouched so nothing already reading it changes meaning.
     *
     * @return array{
     *     concept_id: int, concept_name: ?string, chapter_id: ?int,
     *     concept_exact: bool, availability: array, progress: array,
     *     diagnostic: array, ladder: array, current_difficulty: ?string,
     *     next_difficulty: ?string, rule_fired: ?string, rationale: ?string,
     *     understanding: string, needs_remediation: bool,
     *     ready_for_progression: bool, reason?: string
     * }
     */
    public function conceptResult($studentId, int $conceptId, $subInstituteId): array
    {
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'name', 'chapter_id']);

        if ($concept === null) {
            return [
                'concept_id' => $conceptId, 'concept_name' => null, 'chapter_id' => null,
                'concept_exact' => false, 'availability' => [],
                'progress' => $this->progress($studentId, $conceptId),
                'diagnostic' => [], 'ladder' => [],
                'current_difficulty' => null, 'next_difficulty' => null,
                'rule_fired' => null, 'rationale' => null,
                'understanding' => 'unknown', 'needs_remediation' => false,
                'ready_for_progression' => false,
                'reason' => 'unknown_concept',
            ];
        }

        $scope = $this->scope($conceptId, (int) $concept->chapter_id, $subInstituteId);
        $progress = $this->progress($studentId, $conceptId);

        // The same signal bundle the rule reasons from when it picks the next
        // set, so the result screen and the next practice set can never
        // disagree about where the learner is.
        $signals = $this->analyzer->signalsForConcept($studentId, $conceptId, $subInstituteId, $scope['availability']);
        $decision = $this->rule->decide($signals);

        $ladder = (new \App\Services\PAL\Questions\MasteryLadder())
            ->evaluate($scope['availability'], $progress['by_difficulty']);

        $understanding = $this->understandingBand(
            $progress['attempted'],
            (float) $progress['accuracy'],
            $ladder['mastered']
        );

        return [
            'concept_id' => $conceptId,
            'concept_name' => $concept->name,
            'chapter_id' => (int) $concept->chapter_id,
            'concept_exact' => $scope['exact'],
            'availability' => $scope['availability'],
            'progress' => $progress,

            // What the chapter diagnostic said about this concept, so the
            // screen can show movement rather than a bare number.
            'diagnostic' => [
                'level' => $signals['diagnostic_level'] ?? null,
                'concept_pct' => $signals['concept_diagnostic_pct'] ?? null,
                'concept_served' => $signals['concept_diagnostic_served'] ?? 0,
                'baseline_difficulty' => $signals['baseline_difficulty'] ?? null,
            ],

            'ladder' => $ladder,
            'current_difficulty' => $progress['current_difficulty'],
            'next_difficulty' => $decision['difficulty'],
            'rule_fired' => $decision['rule_fired'],
            'rationale' => $decision['rationale'],

            'understanding' => $understanding,
            'needs_remediation' => $understanding === 'weak',
            'ready_for_progression' => $ladder['mastered'],
        ];
    }

    /**
     * Plain-language understanding band for one concept.
     *
     * Reuses the same 40/70 cuts the diagnostic scorer and the adaptive rule
     * use, so "weak" means the same thing on every PAL screen. Below the
     * ladder's attempt floor it reports `untested` rather than labelling a
     * learner off one or two answers.
     */
    private function understandingBand(int $attempted, float $accuracy, bool $mastered): string
    {
        if ($mastered) {
            return 'mastered';
        }

        if ($attempted < 2) {
            return 'untested';
        }

        if ($accuracy < 40.0) {
            return 'weak';
        }

        return $accuracy < 70.0 ? 'developing' : 'strong';
    }

    /**
     * Practice totals for one concept, overall and per band.
     *
     * @return array<string,mixed>
     */
    public function progress($studentId, int $conceptId): array
    {
        $rows = DB::table('pal_adaptive_response')
            ->where('student_id', (int) $studentId)
            ->where('concept_id', $conceptId)
            ->orderByDesc('id')
            ->get(['is_correct', 'difficulty_served']);

        $byDifficulty = [];
        foreach (DifficultyBands::BANDS as $band) {
            $byDifficulty[$band] = ['attempted' => 0, 'correct' => 0, 'accuracy' => 0.0];
        }

        $attempted = $correct = 0;
        $streak = 0;
        $streakOpen = true;
        $current = null;

        foreach ($rows as $row) {
            $attempted++;
            $current ??= $row->difficulty_served;

            if ((int) $row->is_correct === 1) {
                $correct++;
                if ($streakOpen) {
                    $streak++;
                }
            } else {
                $streakOpen = false;
            }

            $band = $row->difficulty_served;

            if (isset($byDifficulty[$band])) {
                $byDifficulty[$band]['attempted']++;

                if ((int) $row->is_correct === 1) {
                    $byDifficulty[$band]['correct']++;
                }
            }
        }

        foreach ($byDifficulty as $band => $stats) {
            $byDifficulty[$band]['accuracy'] = $stats['attempted'] > 0
                ? round($stats['correct'] / $stats['attempted'] * 100, 2)
                : 0.0;
        }

        $hard = $byDifficulty[DifficultyBands::HARD];

        return [
            'attempted' => $attempted,
            'correct' => $correct,
            'accuracy' => $attempted > 0 ? round($correct / $attempted * 100, 2) : 0.0,
            'by_difficulty' => $byDifficulty,
            'current_difficulty' => $current,
            'streak' => $streak,
            'mastered' => $hard['attempted'] >= self::MASTERY_MIN_HARD && $hard['accuracy'] >= self::MASTERY_ACCURACY,
        ];
    }

    /**
     * Which questions belong to this concept, and how many per band.
     *
     * Prefers the concept's own questions and falls back to its chapter.
     * concept_id is populated on roughly 2k of the 28k servable MCQs on this
     * estate, so the chapter path is the normal case; `exact` records which
     * one was used so the screen can be honest about the precision.
     *
     * @return array{exact: bool, column: string, value: int, availability: array<string,int>}
     */
    private function scope(int $conceptId, int $chapterId, $subInstituteId): array
    {
        $exactCount = McqPool::base($subInstituteId)->where('q.concept_id', $conceptId)->count();

        if ($exactCount > 0) {
            $availability = $this->availabilityFor('q.concept_id', $conceptId, $subInstituteId);

            return ['exact' => true, 'column' => 'q.concept_id', 'value' => $conceptId, 'availability' => $availability];
        }

        $availability = McqPool::availability([$chapterId], $subInstituteId)[$chapterId]
            ?? ['easy' => 0, 'medium' => 0, 'hard' => 0, 'untagged' => 0, 'total' => 0];

        return ['exact' => false, 'column' => 'q.chapter_id', 'value' => $chapterId, 'availability' => $availability];
    }

    /** Band counts for one scope column/value, in a single grouped query. */
    private function availabilityFor(string $column, int $value, $subInstituteId): array
    {
        $ids = DifficultyBands::mappingValueIds();
        $easy = (int) $ids[DifficultyBands::EASY];
        $medium = (int) $ids[DifficultyBands::MEDIUM];
        $hard = (int) $ids[DifficultyBands::HARD];

        $inner = McqPool::base($subInstituteId)
            ->where($column, $value)
            ->leftJoin('lms_question_mapping as dok', function ($join) use ($ids) {
                $join->on('dok.questionmaster_id', '=', 'q.id')
                    ->where('dok.mapping_type_id', '=', DifficultyBands::DOK_PARENT_ID)
                    ->whereIn('dok.mapping_value_id', array_values($ids));
            })
            ->groupBy('q.id', 'q.g_difficulty')
            ->select([
                'q.id',
                DB::raw("CASE
                    WHEN MAX(CASE WHEN dok.mapping_value_id = {$easy} THEN 1 END) = 1 THEN 'easy'
                    WHEN MAX(CASE WHEN dok.mapping_value_id = {$medium} THEN 1 END) = 1 THEN 'medium'
                    WHEN MAX(CASE WHEN dok.mapping_value_id = {$hard} THEN 1 END) = 1 THEN 'hard'
                    WHEN LOWER(q.g_difficulty) IN ('easy','medium','hard') THEN LOWER(q.g_difficulty)
                    ELSE NULL END as band"),
            ]);

        $row = DB::query()->fromSub($inner, 'r')->first([
            DB::raw("SUM(r.band = 'easy') as easy"),
            DB::raw("SUM(r.band = 'medium') as medium"),
            DB::raw("SUM(r.band = 'hard') as hard"),
            DB::raw('SUM(r.band IS NULL) as untagged'),
            DB::raw('COUNT(*) as total'),
        ]);

        return [
            'easy' => (int) ($row->easy ?? 0),
            'medium' => (int) ($row->medium ?? 0),
            'hard' => (int) ($row->hard ?? 0),
            'untagged' => (int) ($row->untagged ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    /**
     * The band one question genuinely belongs to, by the estate's own
     * precedence: a DoK tag decides it, and g_difficulty is read only when no
     * DoK tag exists. Null when the question carries neither.
     *
     * Memoised per request - recordAnswer() asks twice (once for the value,
     * once to decide the source label) and a practice set asks five times.
     */
    private function bandForQuestion(int $questionId, $subInstituteId): ?string
    {
        static $memo = [];

        if (array_key_exists($questionId, $memo)) {
            return $memo[$questionId];
        }

        $ids = DifficultyBands::mappingValueIds();

        $dok = DB::table('lms_question_mapping')
            ->where('questionmaster_id', $questionId)
            ->where('mapping_type_id', DifficultyBands::DOK_PARENT_ID)
            ->whereIn('mapping_value_id', array_values($ids))
            ->pluck('mapping_value_id')
            ->all();

        if ($dok !== []) {
            // Ordered easy -> hard so a question carrying several tags resolves
            // the same way every time rather than by row order.
            foreach (DifficultyBands::BANDS as $band) {
                if (in_array((int) $ids[$band], array_map('intval', $dok), true)) {
                    return $memo[$questionId] = $band;
                }
            }
        }

        $generated = DB::table('lms_question_master')->where('id', $questionId)->value('g_difficulty');
        $generated = is_string($generated) ? strtolower(trim($generated)) : null;

        return $memo[$questionId] = DifficultyBands::isBand($generated) ? $generated : null;
    }

    /**
     * Assemble a set of $limit questions for one concept, centred on $target.
     *
     * ---------------------------------------------------------------------------
     * WHY THIS CANNOT JUST DRAW AT ONE BAND
     * ---------------------------------------------------------------------------
     * The brief is five questions per concept. Drawing five at a single band
     * delivers that only if the concept OWNS five at that band, and on this
     * estate almost none do.
     *
     * Measured on chapter 8677 (Integers), 2026-09-18: every one of its 37
     * concepts holds exactly six servable MCQs, split easy 1 / medium 4 /
     * hard 1. **Zero of the 37 have five at any single band.** So a
     * single-band draw returned one question for an easy target and four for a
     * medium one - never five.
     *
     * So the set is filled OUTWARD from the band the rule chose: the target
     * first, then its nearest neighbours (DifficultyBands::adjacent(), the same
     * ladder the diagnostic borrows along), then whatever else the concept has.
     * Six available, five requested - so five is reachable from the concept's
     * own pool without ever leaving the concept.
     *
     * The target band still decides WHERE the set sits; it just no longer caps
     * how big it can be. And each item keeps the band it genuinely came from,
     * so `difficulty_served` in pal_adaptive_response stays true and the
     * mastery ladder is never credited for a band the learner did not answer.
     *
     * @param  array<string,mixed>  $scope
     * @param  array<int,int>  $exclude
     * @return array<int,array{id:int, band:string, tagged:bool}>
     */
    private function fill(array $scope, $subInstituteId, string $target, array $exclude, $studentId, int $limit): array
    {
        // Target first, then nearest-first outward. array_unique keeps the
        // order while dropping the target if adjacent() already named it.
        $order = array_values(array_unique(array_merge(
            [$target],
            DifficultyBands::adjacent($target),
            DifficultyBands::BANDS
        )));

        $picked = [];
        $taken = $exclude;

        foreach ($order as $band) {
            if (count($picked) >= $limit) {
                break;
            }

            $rows = $this->draw($scope, $subInstituteId, $band, $taken, $studentId, $limit - count($picked));

            foreach ($rows as $row) {
                $picked[] = [
                    'id' => (int) $row->id,
                    'band' => $band,
                    'tagged' => $row->band !== null,
                ];
                $taken[] = (int) $row->id;
            }
        }

        return $picked;
    }

    /**
     * Draw questions at one band, excluding ones already answered.
     *
     * Ordered by the STUDENT id, not a per-request seed: a refresh must not
     * reshuffle the five questions in front of the learner. The exclusion list
     * is what moves the set forward as they answer.
     *
     * @param  array<string,mixed>  $scope
     * @param  array<int,int>  $exclude
     */
    private function draw(array $scope, $subInstituteId, string $band, array $exclude, $studentId, int $limit)
    {
        $ids = DifficultyBands::mappingValueIds();

        $query = McqPool::base($subInstituteId)->where($scope['column'], $scope['value']);

        $query->where(function ($w) use ($band) {
            DifficultyBands::constrainByDok($w, 'q.id', $band);
            $w->orWhere(function ($g) use ($band) {
                DifficultyBands::constrainByGenerated($g, 'q', $band);
                DifficultyBands::excludeAnyDok($g, 'q.id');
            });
        });

        if ($exclude !== []) {
            $query->whereNotIn('q.id', $exclude);
        }

        $dokId = (int) $ids[$band];

        return McqPool::deterministic($query, (int) $studentId)
            ->limit($limit)
            ->get([
                'q.id',
                DB::raw("(SELECT 'dok' FROM lms_question_mapping pm WHERE pm.questionmaster_id = q.id
                    AND pm.mapping_type_id = " . DifficultyBands::DOK_PARENT_ID . "
                    AND pm.mapping_value_id = {$dokId} LIMIT 1) as band"),
            ]);
    }
}
