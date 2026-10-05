<?php

namespace App\Services\PAL\Questions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The FORM of a PAL question: what kind of question it is, and what the client
 * needs in order to ask it that way.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * PAL used to send a learner a stem and a list of options, and nothing else.
 * That is all the old exam screen could draw -- radio buttons -- so it was all
 * the controller sent. The consequence was not cosmetic: a fill-in-the-blank
 * question, a match-the-following and an assertion & reason item all reached
 * the learner as four radio buttons, which is the wrong interaction for two of
 * them and loses the stem of the third.
 *
 * The PAL Test now renders every question through the shared H5P players
 * (`components/h5p/players` on the Next side), which pick a player from the
 * question's own recorded form -- Blanks for a blank, a matching activity for
 * match-the-following, a single choice set for an MCQ. That decision needs the
 * FORM, and the form is what this class resolves and hands over.
 *
 * NOTHING IS CONVERTED AND NOTHING IS COPIED. There is no h5p_* row behind a
 * PAL question; the client builds the activity from the question at render
 * time. `lms_question_master` stays the single source of truth, which is the
 * whole point -- a second copy per content type is exactly what this avoids.
 *
 * ---------------------------------------------------------------------------
 * HOW THE FORM IS DECIDED
 * ---------------------------------------------------------------------------
 * The ladder `ApiQuestionBankController` already uses, most authoritative
 * first:
 *
 *   1. `lms_question_extraction.question_type_code`, read off the source
 *      document by the extraction pipeline.
 *   2. `answer -> $.item_form`, which is where an extracted question records
 *      its own form when it has no sidecar row.
 *   3. `lms_question_master.g_qtype_code`, a generated column derived from the
 *      stem, which is all an AI-generated question has.
 *   4. `question_type_id`, the grading engine's eight rows, which can only say
 *      'mcq' or 'narrative'.
 *
 * Kept identical on purpose: PAL and the question bank disagreeing about what
 * form a question is would be worse than either of them being wrong, because
 * the bank is where a teacher looks to find out why a PAL question rendered
 * the way it did.
 *
 * IT IS SPELLED TWICE, ONCE IN SQL AND ONCE IN PHP, and the two must stay
 * identical -- `effectiveCodeFor()` decides which questions are drawn and
 * `describe()` decides what each one is called. The note on the first says
 * what goes wrong when they drift; the tiers above are the contract both
 * implement.
 */
class PalQuestionForms
{
    /**
     * The forms a learner answers WITHOUT choosing a stored option.
     *
     * These are the ones the old `answer_master`-only servability rule hid
     * from PAL entirely: they are marked against the model answer in the
     * question's own envelope, not against a row in `answer_master`, so a rule
     * that demanded two options and one correct flag could never admit them.
     *
     * Every code here is one the client has a player for. A form with no
     * player would render as "this question cannot be played yet", which is
     * worse for a learner than not being drawn at all -- so the list is
     * deliberately the built ones, not every code in the catalogue.
     */
    public const TYPED_ANSWER_CODES = [
        'fill_blank',
        'numerical',
        'match_following',
        'true_false',
    ];

    /**
     * Narrow a query to questions PAL can actually put in front of a learner.
     *
     * TWO ARMS, BECAUSE THERE ARE TWO WAYS TO ANSWER. The first is
     * `ServableQuestions` unchanged: enough options to choose between, and at
     * least one of them marked correct. The second admits a question whose
     * answer is TYPED rather than chosen -- a blank, a value, a pair to match
     * -- on the same standard the first arm sets: it must be answerable, and
     * it must be markable. A stored model answer is what makes it markable,
     * and a form with a built player is what makes it answerable.
     *
     * WHY NOT JUST WIDEN `ServableQuestions::constrain`. That method is also
     * read by `resultPersonalizeMarksController`, which marks against
     * `answer_master` ids and would silently start drawing questions it cannot
     * mark. Widening in place would have been a one-line change with a bug at
     * the other end of it.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $questionIdColumn  fully qualified, e.g. 'lqm.id'
     */
    public function constrainPlayable($query, string $questionIdColumn)
    {
        return $query->where(function ($outer) use ($questionIdColumn) {
            ServableQuestions::constrain($outer, $questionIdColumn);
            $outer->orWhere(function ($typed) use ($questionIdColumn) {
                $this->constrainTypedAnswer($typed, $questionIdColumn);
            });
        });
    }

    /**
     * The typed-answer arm: a stored model answer, and a form with a player.
     *
     * The form test is a subquery rather than a join because the sidecar is a
     * one-to-one table that a join would still multiply when a question has
     * been extracted twice, and this runs inside an already-joined random draw.
     */
    private function constrainTypedAnswer($query, string $questionIdColumn)
    {
        $query->whereRaw(
            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT("
            . $this->answerColumnFor($questionIdColumn)
            . ", '$.model_answer')), '') <> ''"
        );

        return $query->whereIn(
            DB::raw($this->effectiveCodeFor($questionIdColumn)),
            self::TYPED_ANSWER_CODES
        );
    }

    /**
     * The `answer` column of the question this constraint is about.
     *
     * Derived from the id column the caller named, because PAL's draw aliases
     * `lms_question_master` as `lqm` while other callers do not alias it at
     * all, and a hardcoded prefix would work in exactly one of them.
     */
    private function answerColumnFor(string $questionIdColumn): string
    {
        $table = str_contains($questionIdColumn, '.')
            ? substr($questionIdColumn, 0, strrpos($questionIdColumn, '.'))
            : 'lms_question_master';

        return $table . '.answer';
    }

    /**
     * The form expression for a question, as raw SQL.
     *
     * MUST RESOLVE IDENTICALLY TO `describe()`, TIER FOR TIER. One decides
     * which questions are DRAWN and the other decides what the client is TOLD
     * each one is, and a question drawn as one form and described as another
     * is the worst of the available outcomes: it renders in a player its
     * answer key does not fit, or refuses to render at all, on a paper a
     * learner is sitting.
     *
     * That is why the envelope's `item_form` is a tier here and not only in
     * `describe()`. Leaving it out is a quiet trap: an extracted question with
     * no sidecar row records its form in its own `answer` envelope, so the
     * SELECT would report `fill_blank` for a row the WHERE had rejected as
     * `narrative` -- and the question would simply never appear, with nothing
     * anywhere saying why.
     *
     * Written as correlated subqueries rather than as joined columns so it can
     * be dropped into a WHERE on a query that has not joined the sidecar --
     * which PAL's draw has not, and should not, because joining it would
     * change the row count the random draw takes from.
     */
    private function effectiveCodeFor(string $questionIdColumn): string
    {
        $table = str_contains($questionIdColumn, '.')
            ? substr($questionIdColumn, 0, strrpos($questionIdColumn, '.'))
            : 'lms_question_master';

        $sidecar = $this->hasSidecar()
            ? "(SELECT x.question_type_code FROM lms_question_extraction x "
                . "WHERE x.question_id = {$questionIdColumn} LIMIT 1)"
            : 'NULL';
        $envelopeForm = "NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.item_form')), 'null')";
        $generated = $this->hasGeneratedCode() ? "{$table}.g_qtype_code" : 'NULL';

        return "COALESCE({$sidecar}, {$envelopeForm}, {$generated}, "
            . "CASE WHEN {$table}.question_type_id = 1 THEN 'mcq' ELSE 'narrative' END)";
    }

    /**
     * Everything the client needs to render a set of questions as activities,
     * keyed by question id.
     *
     * ONE QUERY FOR THE WHOLE PAPER, not one per question: PAL draws ten at a
     * time and the sidecar lookup would otherwise be ten round trips inside a
     * request that already makes several.
     *
     * @param  array<int, int|string>  $questionIds
     * @return array<int, array<string, mixed>>
     */
    public function describe(array $questionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $questionIds))));
        if ($ids === []) {
            return [];
        }

        $sidecarCodes = $this->sidecarCodesFor($ids);

        $rows = DB::table('lms_question_master as q')
            ->leftJoin('question_type_master as qt', 'qt.id', '=', 'q.question_type_id')
            ->whereIn('q.id', $ids)
            ->get($this->describeSelect());

        $described = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $envelope = $this->decodeEnvelope($row->answer ?? null);
            $rawType = strtolower(trim((string) ($row->lms_question_type ?? '')));
            $isMcq = in_array($rawType, ['mcq', 'multiple', 'multiple choice', 'multiple_choice'], true);

            $code = $sidecarCodes[$id]
                ?? ($envelope['item_form'] ?? null)
                ?? ($row->derived_type_code ?? null)
                ?? ($isMcq ? 'mcq' : 'narrative');

            $described[$id] = [
                'question_type_code' => $code,
                // Both vocabularies, exactly as the bank endpoint returns
                // them: the grading engine's collapsed label, and the form.
                'question_type' => $isMcq ? 'MCQ' : 'Narrative',
                'model_answer' => $this->readableModelAnswer($envelope, $row->answer ?? null),
                'marks' => (int) ($row->points ?? 1),
                'difficulty' => $row->difficulty ?? null,
                // Assertion & reason keeps its two halves apart in the
                // envelope; the client composes the stem from them, and
                // flattening them here would lose which half is which.
                'assertion' => $envelope['assertion'] ?? null,
                'reason' => $envelope['reason'] ?? null,
                'sub_part_labels' => $envelope['sub_part_labels'] ?? [],
                'standard_id' => $row->standard_id !== null ? (int) $row->standard_id : null,
                'subject_id' => $row->subject_id !== null ? (int) $row->subject_id : null,
                'chapter_id' => $row->chapter_id !== null ? (int) $row->chapter_id : null,
            ];
        }

        return $described;
    }

    /** The sidecar's codes for these questions, or an empty map without one. */
    private function sidecarCodesFor(array $ids): array
    {
        if (!$this->hasSidecar()) {
            return [];
        }

        return DB::table('lms_question_extraction')
            ->whereIn('question_id', $ids)
            ->whereNotNull('question_type_code')
            ->pluck('question_type_code', 'question_id')
            ->map(fn ($code) => (string) $code)
            ->all();
    }

    /** Columns `describe()` reads, guarded for the generated ones. */
    private function describeSelect(): array
    {
        $select = [
            'q.id', 'q.points', 'q.answer', 'q.question_type_id',
            'q.standard_id', 'q.subject_id', 'q.chapter_id',
            DB::raw('qt.question_type as lms_question_type'),
        ];

        // `g_qtype_code` and `g_difficulty` were added later and are absent on
        // an older tenant's schema. Selecting a missing generated column is a
        // hard SQL error, not a null, so both are probed rather than assumed.
        $select[] = $this->hasGeneratedCode()
            ? DB::raw('q.g_qtype_code as derived_type_code')
            : DB::raw('NULL as derived_type_code');
        $select[] = $this->hasColumn('g_difficulty')
            ? DB::raw('q.g_difficulty as difficulty')
            : DB::raw('NULL as difficulty');

        return $select;
    }

    /** The answer envelope, or an empty array when the column holds prose. */
    private function decodeEnvelope($value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '' || !str_starts_with($trimmed, '{')) {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prose answer only.
     *
     * An MCQ envelope has no `model_answer`, and returning the raw JSON there
     * is what would force the client to guard every read with a looks-like-JSON
     * check -- so it returns null instead. Same rule as
     * `ApiQuestionBankController::readableModelAnswer`, deliberately.
     */
    private function readableModelAnswer(array $envelope, $raw): ?string
    {
        $model = $envelope['model_answer'] ?? null;
        if (is_string($model) && trim($model) !== '') {
            return $model;
        }

        if ($envelope !== []) {
            return null;
        }

        return is_string($raw) && trim($raw) !== '' ? $raw : null;
    }

    // -----------------------------------------------------------------------
    // Schema probes, memoised: these run on every PAL draw and must not become
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
