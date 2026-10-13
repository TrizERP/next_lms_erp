<?php

namespace App\Services\QuestionGeneration;

/**
 * The ONE definition of "what form is this question?".
 *
 * WHY THIS EXISTS. Five readers each spelled this ladder for themselves and no
 * two spelled it the same way:
 *
 *   - QuestionBankSource::effectiveCode        sidecar, generated, legacy
 *   - ApiQuestionBankController (counts)       sidecar, FORMAT, generated, legacy
 *   - ApiQuestionBankController (filter)       sidecar, generated, legacy
 *   - ApiQuestionBankController::shape         sidecar, ITEM_FORM, generated, legacy
 *   - PalQuestionForms (draw + describe)       sidecar, ITEM_FORM, generated, legacy
 *
 * A form recorded in `question_format_code` was therefore visible to some of them
 * and invisible to the rest, so a question could be counted under one form,
 * filtered under another and drawn by PAL as a third. Every reader now takes its
 * expression from here.
 *
 * THE LADDER, most authoritative first:
 *
 *   1. lms_question_extraction.question_type_code   read off a source document
 *   2. lms_question_master.question_format_code     the catalogue code a person
 *                                                   or the format-driven
 *                                                   generator recorded
 *   3. answer -> $.item_form                        the envelope's own copy of
 *                                                   the same code (extraction
 *                                                   writes it; the generator
 *                                                   dual-writes it)
 *   4. lms_question_master.g_qtype_code             the tagger's derived tag
 *   5. question_type_id                             'mcq' when it is 1, else
 *                                                   'narrative'
 *
 * Tier 2 and 3 are written together by the generator and always hold the same
 * code. Tier 3 is what lets PAL and ApiQuestionBankController::shape see a
 * generated form without any change of their own; tier 2 is what the label join
 * and the facet counts read.
 *
 * The SQL builder and the PHP resolver below are the same ladder spelled twice.
 * `QuestionFormResolverTest` pins the tier order of both.
 */
final class QuestionFormResolver
{
    /** question_type_master.id the whole codebase treats as multiple choice. */
    public const MCQ_TYPE_ID = 1;

    /**
     * The ladder as SQL, for a WHERE / SELECT / GROUP BY.
     *
     * @param  string  $table         alias (or name) of lms_question_master in the calling query
     * @param  string  $sidecarCode   SQL for tier 1: a joined column such as `x.question_type_code`,
     *                                a correlated subquery, or `NULL` where no sidecar exists
     * @param  bool    $hasFormatColumn  false on a schema that predates `question_format_code`
     * @param  bool    $hasGeneratedCode false on a schema that predates `g_qtype_code`
     * @param  bool    $withLegacy       false to stop at tier 4 and yield NULL when no real
     *                                   catalogue code exists. A catalogue label join needs
     *                                   that: tier 5 is a grading type, not a catalogue form,
     *                                   and joining on it would relabel every legacy row.
     */
    public static function sqlExpression(
        string $table = 'lms_question_master',
        string $sidecarCode = 'NULL',
        bool $hasFormatColumn = true,
        bool $hasGeneratedCode = true,
        bool $withLegacy = true
    ): string {
        $format = $hasFormatColumn ? "NULLIF({$table}.question_format_code, '')" : 'NULL';
        $itemForm = "NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.item_form')), 'null'), '')";
        $generated = $hasGeneratedCode ? "{$table}.g_qtype_code" : 'NULL';
        $tail = $withLegacy ? ', ' . self::legacySql($table) : '';

        return "COALESCE({$sidecarCode}, {$format}, {$itemForm}, {$generated}{$tail})";
    }

    /** Tier 5 on its own: the coarse type the grading engine records. */
    public static function legacySql(string $table = 'lms_question_master'): string
    {
        return "CASE WHEN {$table}.question_type_id = " . self::MCQ_TYPE_ID . " THEN 'mcq' ELSE 'narrative' END";
    }

    /**
     * The same ladder for a row already in PHP.
     *
     * @param  string|null  $sidecar   lms_question_extraction.question_type_code
     * @param  string|null  $format    lms_question_master.question_format_code
     * @param  mixed        $envelope  the decoded `answer` envelope (array), the raw column, or null
     * @param  string|null  $generated lms_question_master.g_qtype_code
     * @param  string|null  $legacy    tier 5, which the caller derives from its own join; null
     *                                 when the caller wants "no real code" reported as null
     */
    public static function resolve(
        ?string $sidecar,
        ?string $format,
        mixed $envelope,
        ?string $generated,
        ?string $legacy
    ): ?string {
        foreach ([$sidecar, $format, self::itemForm($envelope), $generated] as $candidate) {
            $candidate = self::clean($candidate);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return $legacy;
    }

    /** `answer.item_form`, from a decoded envelope or the raw JSON column. */
    public static function itemForm(mixed $envelope): ?string
    {
        if (is_string($envelope)) {
            $trimmed = trim($envelope);
            if ($trimmed === '' || $trimmed[0] !== '{') {
                return null;
            }
            $envelope = json_decode($trimmed, true);
        }

        if (!is_array($envelope)) {
            return null;
        }

        $form = $envelope['item_form'] ?? null;

        return is_string($form) ? self::clean($form) : null;
    }

    private static function clean(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' || strtolower($value) === 'null' ? null : $value;
    }
}
