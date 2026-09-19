<?php

namespace App\Services\PAL\Questions;

use Illuminate\Support\Facades\DB;

/**
 * The single definition of "an MCQ this tenant may serve to a learner".
 *
 * ---------------------------------------------------------------------------
 * WHY THE TYPE FILTER LIVES HERE AND NOT IN ServableQuestions
 * ---------------------------------------------------------------------------
 * ServableQuestions deliberately REPLACED `question_type_id = 1` with a test of
 * whether a question can actually be answered and marked - read its docblock,
 * the reasoning is sound and it is what the Learn flow depends on today.
 *
 * The diagnostic has a narrower brief: the product owner asked for strictly
 * MCQ items, no assertion-and-reason and no CBE, even though those are
 * answerable. So this class composes the two rules rather than choosing
 * between them - ServableQuestions::constrain() for answerability, plus
 * question_type_id = 1 for the label.
 *
 * Keeping the type filter here means ServableQuestions keeps its current
 * behaviour untouched and getRandomPalQuestions() (the Learn flow) cannot
 * regress. Do not push this filter down into ServableQuestions.
 *
 * The cost is known and accepted: roughly 214 answerable assertion-and-reason
 * and CBE items are excluded from PAL diagnostics. The remaining pool is large
 * - 13094 easy / 8535 medium / 6457 hard servable MCQs estate-wide.
 */
final class McqPool
{
    /** question_type_master.id of "multiple". The MCQ label. */
    public const MCQ_TYPE_ID = 1;

    /**
     * Every MCQ this tenant may serve. Caller adds chapter/concept scope.
     *
     * sub_institute_id 0 is the shared/global bank and is always included
     * alongside the tenant, matching how every other PAL surface reads it.
     */
    public static function base($subInstituteId)
    {
        $query = DB::table('lms_question_master as q')
            ->whereNull('q.deleted_at')
            ->where('q.status', 1)
            ->where('q.question_type_id', self::MCQ_TYPE_ID)
            ->whereIn('q.sub_institute_id', [$subInstituteId, 0]);

        return ServableQuestions::constrain($query, 'q.id');
    }

    /**
     * A stable, seed-controlled order.
     *
     * Not inRandomOrder(): on a 65k-row table MySQL materialises and sorts the
     * whole candidate set, and - worse for us - the order changes on every
     * call. A seeded hash is cheap and reproducible, which is what lets a
     * support engineer replay exactly the paper a learner was served from the
     * seed stored in selection_report, and what stops a page refresh
     * reshuffling the questions in front of a learner mid-practice.
     */
    public static function deterministic($query, int $seed, string $questionIdColumn = 'q.id')
    {
        return $query->orderByRaw('MD5(CONCAT(' . $questionIdColumn . ', ?))', [(string) $seed]);
    }

    /**
     * Per-chapter counts of servable MCQs under the combined difficulty signal.
     *
     * ONE grouped query for every chapter asked about. The chapter picker and
     * the concept list both need this for many chapters at once, and doing it
     * per chapter turns a page load into hundreds of round trips against a
     * remote database.
     *
     * The CASE ladder mirrors DifficultyBands exactly: a DoK tag decides the
     * band, and g_difficulty is read only when no DoK tag exists. Rows with
     * neither land in `untagged`, which is counted but never presented as a
     * difficulty.
     *
     * @param  array<int,int>  $chapterIds
     * @return array<int,array{easy:int,medium:int,hard:int,untagged:int,total:int}>
     */
    public static function availability(array $chapterIds, $subInstituteId): array
    {
        $chapterIds = array_values(array_unique(array_filter(array_map('intval', $chapterIds))));

        if ($chapterIds === []) {
            return [];
        }

        $ids = DifficultyBands::mappingValueIds();

        // Resolved to a band per question first, THEN counted. Without the
        // inner grouping a question holding several mapping rows would be
        // counted once per row.
        $inner = self::base($subInstituteId)
            ->whereIn('q.chapter_id', $chapterIds)
            ->leftJoin('lms_question_mapping as dok', function ($join) use ($ids) {
                $join->on('dok.questionmaster_id', '=', 'q.id')
                    ->where('dok.mapping_type_id', '=', DifficultyBands::DOK_PARENT_ID)
                    ->whereIn('dok.mapping_value_id', array_values($ids));
            })
            ->groupBy('q.id', 'q.chapter_id', 'q.g_difficulty')
            ->select([
                'q.id',
                'q.chapter_id',
                DB::raw(self::bandExpression($ids) . ' as band'),
            ]);

        $rows = DB::query()
            ->fromSub($inner, 'resolved')
            ->groupBy('resolved.chapter_id')
            ->get([
                'resolved.chapter_id',
                DB::raw("SUM(resolved.band = 'easy') as easy"),
                DB::raw("SUM(resolved.band = 'medium') as medium"),
                DB::raw("SUM(resolved.band = 'hard') as hard"),
                DB::raw("SUM(resolved.band IS NULL) as untagged"),
                DB::raw('COUNT(*) as total'),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->chapter_id] = [
                'easy' => (int) $row->easy,
                'medium' => (int) $row->medium,
                'hard' => (int) $row->hard,
                'untagged' => (int) $row->untagged,
                'total' => (int) $row->total,
            ];
        }

        // Chapters with nothing still get a row, so callers can render them
        // disabled instead of dropping them out of the list silently.
        foreach ($chapterIds as $id) {
            $out[$id] ??= ['easy' => 0, 'medium' => 0, 'hard' => 0, 'untagged' => 0, 'total' => 0];
        }

        return $out;
    }

    /**
     * Band counts for MANY concepts in one grouped query.
     *
     * The chapter-scoped availability() above cannot answer this: a concept
     * owns a slice of its chapter's questions, and the mastery ladder is
     * graded per concept, not per chapter. Doing it per concept in PHP turned
     * a 37-concept chapter into 37 round trips against a remote database.
     *
     * Same CASE ladder as availability(), for the same reason - a DoK tag
     * decides the band and g_difficulty is read only when none exists.
     *
     * @param  array<int,int>  $conceptIds
     * @return array<int,array{easy:int,medium:int,hard:int,untagged:int,total:int}>
     */
    public static function availabilityForConcepts(array $conceptIds, $subInstituteId): array
    {
        $conceptIds = array_values(array_unique(array_filter(array_map('intval', $conceptIds))));

        if ($conceptIds === []) {
            return [];
        }

        $ids = DifficultyBands::mappingValueIds();

        $inner = self::base($subInstituteId)
            ->whereIn('q.concept_id', $conceptIds)
            ->leftJoin('lms_question_mapping as dok', function ($join) use ($ids) {
                $join->on('dok.questionmaster_id', '=', 'q.id')
                    ->where('dok.mapping_type_id', '=', DifficultyBands::DOK_PARENT_ID)
                    ->whereIn('dok.mapping_value_id', array_values($ids));
            })
            ->groupBy('q.id', 'q.concept_id', 'q.g_difficulty')
            ->select([
                'q.id',
                'q.concept_id',
                DB::raw(self::bandExpression($ids) . ' as band'),
            ]);

        $rows = DB::query()
            ->fromSub($inner, 'resolved')
            ->groupBy('resolved.concept_id')
            ->get([
                'resolved.concept_id',
                DB::raw("SUM(resolved.band = 'easy') as easy"),
                DB::raw("SUM(resolved.band = 'medium') as medium"),
                DB::raw("SUM(resolved.band = 'hard') as hard"),
                DB::raw('SUM(resolved.band IS NULL) as untagged'),
                DB::raw('COUNT(*) as total'),
            ]);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->concept_id] = [
                'easy' => (int) $row->easy,
                'medium' => (int) $row->medium,
                'hard' => (int) $row->hard,
                'untagged' => (int) $row->untagged,
                'total' => (int) $row->total,
            ];
        }

        // A concept with nothing still gets a row, so the ladder can say "no
        // questions yet" instead of the caller seeing a missing key.
        foreach ($conceptIds as $id) {
            $out[$id] ??= ['easy' => 0, 'medium' => 0, 'hard' => 0, 'untagged' => 0, 'total' => 0];
        }

        return $out;
    }

    /**
     * SQL that resolves one question to a band: DoK first, then g_difficulty.
     *
     * MAX() over the joined mapping rows because we are inside a GROUP BY on
     * the question - it answers "does this question carry that tag at all".
     *
     * @param  array<string,int>  $ids
     */
    private static function bandExpression(array $ids): string
    {
        $easy = (int) $ids[DifficultyBands::EASY];
        $medium = (int) $ids[DifficultyBands::MEDIUM];
        $hard = (int) $ids[DifficultyBands::HARD];

        return "CASE
            WHEN MAX(CASE WHEN dok.mapping_value_id = {$easy} THEN 1 END) = 1 THEN 'easy'
            WHEN MAX(CASE WHEN dok.mapping_value_id = {$medium} THEN 1 END) = 1 THEN 'medium'
            WHEN MAX(CASE WHEN dok.mapping_value_id = {$hard} THEN 1 END) = 1 THEN 'hard'
            WHEN LOWER(q.g_difficulty) IN ('easy','medium','hard') THEN LOWER(q.g_difficulty)
            ELSE NULL
        END";
    }
}
