<?php

namespace App\Services\lms\H5P;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Questions for an H5P screen, read straight from `lms_question_master`.
 *
 * WHY THIS EXISTS. Every H5P type had the same problem the MCQ screen had: it
 * could only show what somebody had authored into its own `h5p_*` table, so a
 * chapter with 726 questions in the bank showed an empty library. This reads
 * the bank instead. Nothing is copied into an `h5p_*` table; the caller gets
 * the questions and renders them.
 *
 * HOW A QUESTION'S FORM IS DECIDED. `question_type_catalog` is the vocabulary
 * -- `mcq`, `fill_blank`, `match_following`, `assertion_reason` and the rest --
 * and a question is matched to it on three tiers, most authoritative first:
 *
 *   1. `lms_question_extraction.question_type_code`, read off the source
 *      document by the extraction pipeline.
 *   2. `lms_question_master.g_qtype_code`, a generated column derived from the
 *      stem, which is all an AI-generated question has.
 *   3. `question_type_id`, the grading engine's eight rows, which can only say
 *      "multiple" or "narrative".
 *
 * That ladder is `ApiQuestionBankController`'s, kept identical on purpose: two
 * endpoints disagreeing about what form a question is would be worse than
 * either being wrong.
 *
 * DIFFICULTY IS `g_difficulty`, a stored generated column holding
 * `answer -> $.difficulty`. It replaced the `lms_question_mapping` join, which
 * required difficulty to be recorded a second time per question and returned
 * one to six questions per level on a chapter holding hundreds.
 */
class QuestionBankSource
{
    /**
     * Which catalogue forms each H5P type can play.
     *
     * A form appears under every type that can honestly render it: a fill-blank
     * question is a typed answer (Blanks), a dragged word (Drag the Words) or a
     * marked word (Mark the Words) depending only on how the screen presents
     * it. A form appears under no type when nothing built can carry it.
     */
    public const TYPE_CODES = [
        'h5p_mcq'                 => ['mcq', 'assertion_reason'],
        'h5p_single_choice_set'   => ['mcq', 'assertion_reason'],
        'h5p_true_false'          => ['true_false'],
        'h5p_blanks'              => ['fill_blank', 'numerical'],
        // 'drag_text' / 'mark_the_words' / 'drag_drop' are their own
        // question_type_catalog codes (near-identical labels to these H5P
        // types) that carried no mapping here -- confirmed zero existing
        // questions under any of the three, so adding the obvious 1:1 is
        // additive with no effect on what any existing row plays as.
        'h5p_drag_text'           => ['fill_blank', 'drag_text'],
        'h5p_mark_the_words'      => ['fill_blank', 'mark_the_words'],
        'h5p_memory_game'         => ['match_following'],
        'h5p_drag_drop'           => ['match_following', 'drag_drop'],
        'h5p_arithmetic_quiz'     => ['numerical'],
        'h5p_course_presentation' => [
            'case_study', 'case_study_parent', 'case_study_child',
            'source_based_integrated', 'competency_focused', 'proof', 'construction',
        ],
    ];

    /**
     * What a question must carry before this type can render it.
     *
     *   options -> at least two rows in `answer_master`; there has to be
     *              something to choose between.
     *   answer  -> a model answer in the question's `answer` envelope; a blank
     *              with nothing to fill in is not a question.
     *   text    -> a stem, and nothing more. Presentation types show the
     *              question; they do not mark it.
     */
    public const TYPE_REQUIRES = [
        'h5p_mcq'                 => 'options',
        'h5p_single_choice_set'   => 'options',
        'h5p_true_false'          => 'answer',
        'h5p_blanks'              => 'answer',
        'h5p_drag_text'           => 'answer',
        'h5p_mark_the_words'      => 'answer',
        'h5p_memory_game'         => 'answer',
        'h5p_drag_drop'           => 'answer',
        'h5p_arithmetic_quiz'     => 'answer',
        'h5p_course_presentation' => 'text',
    ];

    /** Is this an H5P type that can be served from the bank at all? */
    public static function supports(string $h5pType): bool
    {
        return isset(self::TYPE_CODES[$h5pType]);
    }

    public static function codesFor(string $h5pType): array
    {
        return self::TYPE_CODES[$h5pType] ?? [];
    }

    /**
     * The three-tier form expression, as raw SQL.
     *
     * Kept in one place because it is used by the WHERE, the SELECT and the
     * level counts, and the three must agree or a question is counted at a
     * level it cannot be drawn from.
     */
    public function effectiveCode(): string
    {
        $sidecar = $this->hasSidecar() ? 'x.question_type_code' : 'NULL';
        $generated = $this->hasGeneratedCode() ? 'q.g_qtype_code' : 'NULL';

        return "COALESCE($sidecar, $generated, "
            . "CASE WHEN q.question_type_id = 1 THEN 'mcq' ELSE 'narrative' END)";
    }

    /**
     * Every question in scope this H5P type could serve, before any level
     * filter.
     *
     * The content requirement is an EXISTS-style subquery rather than a join:
     * joining `answer_master` multiplies a question by its option count, and
     * the old MCQ query then leaned on `GROUP BY question_title` to undo that
     * -- which also collapsed two genuinely different questions that happened
     * to share a title.
     */
    public function scoped(Request $request, $subInstituteId, string $h5pType)
    {
        $query = DB::table('lms_question_master as q')
            ->where('q.sub_institute_id', $subInstituteId)
            ->when($this->hasColumn('deleted_at'), fn ($w) => $w->whereNull('q.deleted_at'))
            ->when($request->filled('standard_id'), fn ($w) => $w->where('q.standard_id', $request->standard_id))
            ->when($request->filled('subject_id'), fn ($w) => $w->where('q.subject_id', $request->subject_id))
            ->when($request->filled('chapter_id'), fn ($w) => $w->where('q.chapter_id', $request->chapter_id))
            ->when($request->filled('topic_id'), fn ($w) => $w->where('q.topic_id', $request->topic_id))
            ->when($request->filled('concept_id'), fn ($w) => $w->where('q.concept_id', $request->concept_id));

        if ($this->hasSidecar()) {
            // LEFT, so AI-generated questions -- which have no extraction row --
            // are still reachable through the generated column below them.
            $query->leftJoin('lms_question_extraction as x', 'x.question_id', '=', 'q.id');
        }

        $codes = self::codesFor($h5pType);
        if ($codes !== []) {
            $query->whereIn(DB::raw($this->effectiveCode()), $codes);
        }

        return $this->requireContent($query, $h5pType, $subInstituteId);
    }

    /** Apply this type's content requirement. See TYPE_REQUIRES. */
    private function requireContent($query, string $h5pType, $subInstituteId)
    {
        $requires = self::TYPE_REQUIRES[$h5pType] ?? 'text';

        if ($requires === 'options') {
            return $query->whereIn('q.id', function ($sub) use ($subInstituteId) {
                $sub->from('answer_master')
                    ->select('question_id')
                    ->where('sub_institute_id', $subInstituteId)
                    ->groupBy('question_id')
                    ->havingRaw('COUNT(*) >= 2');
            });
        }

        if ($requires === 'answer') {
            // Either a written model answer in the envelope, or options to draw
            // the answer from -- a true/false question stores it as two options
            // while a fill-blank stores it as prose.
            return $query->where(function ($w) use ($subInstituteId) {
                $w->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(q.answer, '$.model_answer')), '') <> ''")
                    ->orWhereIn('q.id', function ($sub) use ($subInstituteId) {
                        $sub->from('answer_master')
                            ->select('question_id')
                            ->where('sub_institute_id', $subInstituteId)
                            ->groupBy('question_id')
                            ->havingRaw('COUNT(*) >= 1');
                    });
            });
        }

        return $query->whereRaw("COALESCE(q.question_title, '') <> ''");
    }

    /**
     * The difficulties this scope actually holds questions at, for this type.
     *
     * `id` is the difficulty word, because there is no row behind it any more:
     * the value is the key, and it is what comes back as `selectedLevel`.
     */
    public function levels(Request $request, $subInstituteId, string $h5pType)
    {
        $rows = $this->scoped($request, $subInstituteId, $h5pType)
            ->whereNotNull('q.g_difficulty')
            ->where('q.g_difficulty', '<>', '')
            ->selectRaw('LOWER(TRIM(q.g_difficulty)) as level, COUNT(DISTINCT q.id) as total')
            ->groupBy(DB::raw('LOWER(TRIM(q.g_difficulty))'))
            ->pluck('total', 'level');

        // Taught order, not alphabetical: a picker reading Easy, Hard, Medium
        // is one nobody trusts.
        $rank = ['easy' => 1, 'medium' => 2, 'hard' => 3];

        $levels = [];
        foreach ($rows as $level => $total) {
            $levels[] = [
                'id'    => ucfirst($level),
                'name'  => ucfirst($level),
                'total' => (int) $total,
                'rank'  => $rank[$level] ?? 99,
            ];
        }

        usort($levels, fn ($a, $b) => $a['rank'] === $b['rank']
            ? strcmp($a['name'], $b['name'])
            : $a['rank'] <=> $b['rank']);

        return collect($levels)->map(function ($level) {
            unset($level['rank']);
            // An object, because the Blade views read `$item->name`.
            return (object) $level;
        });
    }

    /**
     * Draw questions for this type, optionally at one difficulty.
     *
     * `$take` of 0 means every match, which is what a library listing wants;
     * a quiz passes 10.
     */
    public function questions(
        Request $request,
        $subInstituteId,
        string $h5pType,
        ?string $level = null,
        int $take = 10,
        bool $random = true
    ) {
        $query = $this->scoped($request, $subInstituteId, $h5pType)
            ->selectRaw(
                'q.id, q.question_title, q.points, q.g_difficulty, q.g_bloom, '
                . 'q.chapter_id, q.subject_id, q.standard_id, q.topic_id, q.concept_id, '
                . $this->effectiveCode() . ' as question_type_code, '
                . "JSON_UNQUOTE(JSON_EXTRACT(q.answer, '$.model_answer')) as model_answer"
            )
            ->groupBy(
                'q.id', 'q.question_title', 'q.points', 'q.g_difficulty', 'q.g_bloom',
                'q.chapter_id', 'q.subject_id', 'q.standard_id', 'q.topic_id', 'q.concept_id',
                'q.answer', 'q.question_type_id'
            );

        if ($this->hasSidecar()) {
            $query->groupBy('x.question_type_code');
        }
        if ($this->hasGeneratedCode()) {
            $query->groupBy('q.g_qtype_code');
        }

        if ($level !== null && trim($level) !== '') {
            // Folded on both sides: the column carries "Easy", "easy" and
            // "EASY" across publishers, and one level must get all three.
            $query->whereRaw('LOWER(TRIM(q.g_difficulty)) = ?', [mb_strtolower(trim($level))]);
        }

        $query = $random ? $query->inRandomOrder() : $query->orderBy('q.id');

        return $take > 0 ? $query->take($take)->get() : $query->get();
    }

    /**
     * Options for a set of questions, keyed by question id.
     *
     * One query for the whole paper. Ten questions used to cost ten round
     * trips, one per question, in every controller that did this by hand.
     */
    public function answersFor(array $questionIds, $subInstituteId): array
    {
        if ($questionIds === []) {
            return [];
        }

        return DB::table('answer_master')
            ->whereIn('question_id', $questionIds)
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('id')
            ->get()
            ->groupBy('question_id')
            ->map(fn ($rows) => $rows->values()->map(fn ($r) => (array) $r)->toArray())
            ->toArray();
    }

    // -----------------------------------------------------------------------
    // Schema probes, memoised: these run on every request and must not become
    // a round trip each.
    // -----------------------------------------------------------------------

    private function hasSidecar(): bool
    {
        static $has = null;
        return $has ??= Schema::hasTable('lms_question_extraction');
    }

    private function hasGeneratedCode(): bool
    {
        static $has = null;
        return $has ??= $this->hasColumn('g_qtype_code');
    }

    private function hasColumn(string $column): bool
    {
        static $cache = [];
        return $cache[$column] ??= Schema::hasColumn('lms_question_master', $column);
    }
}
