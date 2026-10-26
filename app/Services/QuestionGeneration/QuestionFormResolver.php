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

    /**
     * Decode `answer` envelope if passed as a string or return array as is.
     */
    public static function decodeEnvelope(mixed $envelope): ?array
    {
        if (is_array($envelope)) {
            return $envelope;
        }
        if (!is_string($envelope)) {
            return null;
        }
        $trimmed = trim($envelope);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return null;
        }
        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Resolves user-friendly, consistent display label for a question format.
     *
     * Prioritizes the specific interactive format stored in the answer envelope
     * (sub_type, interaction_settings.mode), then catalog forms/labels, then
     * legacy grading engine fallbacks.
     */
    public static function resolveDisplayLabel(
        ?string $formatCode,
        mixed $envelope,
        ?string $legacyType = null,
        ?string $catalogLabel = null,
        ?string $sidecarCode = null
    ): string {
        $env = self::decodeEnvelope($envelope);
        $subType = is_array($env) ? ($env['sub_type'] ?? null) : null;
        $mode = is_array($env) && isset($env['interaction_settings']) && is_array($env['interaction_settings'])
            ? ($env['interaction_settings']['mode'] ?? null)
            : null;
        $itemForm = is_array($env) ? ($env['item_form'] ?? null) : null;

        $format = self::clean($formatCode);
        $sidecar = self::clean($sidecarCode);

        // 1. Single Choice Set
        if ($subType === 'Single Choice Set' || $mode === 'single_choice_set' || $itemForm === 'single_choice_set') {
            return 'Single Choice Set';
        }

        // 2. Flash Cards
        if (in_array($subType, ['Flash Cards', 'Flash Card'], true) || $mode === 'flip_card' || $format === 'flash_card') {
            return 'Flash Cards';
        }

        // 3. Image Hotspots
        if (in_array($subType, ['Image Hotspots', 'Image Hotspot'], true) || $mode === 'image_hotspots' || in_array($format, ['image_hotspots', 'image_hotspot'], true)) {
            return 'Image Hotspots';
        }

        // 4. Match Following / Memory Game
        if (in_array($subType, ['Memory Game', 'Match the Following'], true) || $mode === 'memory_game' || in_array($format, ['match_following', 'memory_game'], true) || in_array($sidecar, ['match_following', 'memory_game'], true)) {
            return 'Match Following / Memory Game';
        }

        // 5. Drag the Words
        if (in_array($subType, ['Drag the Words', 'Drag Text'], true) || $mode === 'drag_words' || in_array($format, ['drag_text', 'drag_words'], true) || in_array($sidecar, ['drag_text', 'drag_words'], true)) {
            return 'Drag the Words';
        }

        // 6. Drag and Drop
        if (in_array($subType, ['Drag and Drop', 'Drag & Drop'], true) || in_array($format, ['drag_drop'], true) || in_array($sidecar, ['drag_drop'], true)) {
            return 'Drag and Drop';
        }

        // 7. Mark the Words
        if (in_array($subType, ['Mark the Words', 'Mark The Words'], true) || $mode === 'mark_words' || in_array($format, ['mark_the_words'], true) || in_array($sidecar, ['mark_the_words'], true)) {
            return 'Mark the Words';
        }

        // 8. Fill in the Blanks
        if (in_array($subType, ['Fill in the Blanks', 'Fill in the Blank'], true) || $mode === 'text_blank' || in_array($format, ['fill_blank', 'fill_in_the_blanks'], true) || in_array($sidecar, ['fill_blank'], true)) {
            return 'Fill in the Blanks';
        }

        // 9. True / False
        if ($subType === 'True / False' || $mode === 'true_false' || in_array($format, ['true_false'], true) || in_array($sidecar, ['true_false'], true)) {
            return 'True / False';
        }

        // 10. Multiple Choice Question (MCQ)
        if (in_array($subType, ['Multiple Choice Questions', 'MCQ'], true) || $mode === 'single_select' || ($format === 'mcq' && $subType === null) || $sidecar === 'mcq') {
            return 'Multiple Choice Question (MCQ)';
        }

        // Known blueprint formats
        if ($format === 'assertion_reason' || $sidecar === 'assertion_reason') return 'Assertion & Reason';
        if ($format === 'short' || $sidecar === 'short') return 'Short Answer';
        if ($format === 'long' || $sidecar === 'long') return 'Long Answer';
        if ($format === 'very_short' || $sidecar === 'very_short') return 'Very Short Answer';
        if ($format === 'case_study' || $sidecar === 'case_study') return 'Case Study';
        if ($format === 'numerical' || $sidecar === 'numerical') return 'Numerical Response';
        if ($format === 'passage' || $sidecar === 'passage') return 'Passage';

        // Catalog label if available and not coarse grading type
        $cleanCat = self::clean($catalogLabel);
        if ($cleanCat !== null && !in_array(strtolower($cleanCat), ['multiple', 'narrative'], true)) {
            return $cleanCat;
        }

        // Fallback to legacy grading type
        $rawLegacy = strtolower(trim((string) $legacyType));
        if (in_array($rawLegacy, ['1', 'mcq', 'multiple', 'multiple choice', 'multiple_choice'], true)) {
            return 'Multiple Choice Question (MCQ)';
        }

        return 'Narrative';
    }

    /**
     * Resolves the fine-grained format code.
     */
    public static function resolveFormatCode(
        ?string $formatCode,
        mixed $envelope,
        ?string $sidecarCode = null,
        ?string $derivedCode = null,
        ?string $legacy = null
    ): string {
        $env = self::decodeEnvelope($envelope);
        $subType = is_array($env) ? ($env['sub_type'] ?? null) : null;
        $mode = is_array($env) && isset($env['interaction_settings']) && is_array($env['interaction_settings'])
            ? ($env['interaction_settings']['mode'] ?? null)
            : null;
        $itemForm = is_array($env) ? ($env['item_form'] ?? null) : null;

        if ($subType === 'Single Choice Set' || $mode === 'single_choice_set' || $itemForm === 'single_choice_set') {
            return 'single_choice_set';
        }
        if (in_array($subType, ['Flash Cards', 'Flash Card'], true) || $mode === 'flip_card') {
            return 'flash_card';
        }
        if (in_array($subType, ['Image Hotspots', 'Image Hotspot'], true) || $mode === 'image_hotspots') {
            return 'image_hotspots';
        }
        if (in_array($subType, ['Memory Game', 'Match the Following'], true) || $mode === 'memory_game') {
            return 'match_following';
        }
        if (in_array($subType, ['Drag the Words', 'Drag Text'], true) || $mode === 'drag_words') {
            return 'drag_text';
        }
        if (in_array($subType, ['Drag and Drop', 'Drag & Drop'], true)) {
            return 'drag_drop';
        }
        if (in_array($subType, ['Mark the Words', 'Mark The Words'], true) || $mode === 'mark_words') {
            return 'mark_the_words';
        }
        if (in_array($subType, ['Fill in the Blanks', 'Fill in the Blank'], true) || $mode === 'text_blank') {
            return 'fill_blank';
        }
        if ($subType === 'True / False' || $mode === 'true_false') {
            return 'true_false';
        }
        if (in_array($subType, ['Multiple Choice Questions', 'MCQ'], true) || $mode === 'single_select') {
            return 'mcq';
        }

        return self::resolve($sidecarCode, $formatCode, $envelope, $derivedCode, $legacy) ?? ($legacy ?? 'mcq');
    }

    /**
     * Applies filter on question format / type to query builder.
     */
    public static function applyTypeFilter($query, mixed $typeFilter, string $table = 'lms_question_master'): void
    {
        if (is_numeric($typeFilter)) {
            $query->where("{$table}.question_type_id", (int) $typeFilter);
            return;
        }

        $raw = strtolower(trim((string) $typeFilter));
        if ($raw === '' || $raw === 'all') {
            return;
        }

        if ($raw === 'multiple') {
            $query->where("{$table}.question_type_id", 1);
            return;
        }
        if ($raw === 'narrative') {
            $query->where("{$table}.question_type_id", '!=', 1);
            return;
        }

        if (in_array($raw, ['single choice set', 'single_choice_set'], true)) {
            $query->where(function ($q) use ($table) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) = 'Single Choice Set'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'single_choice_set'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.item_form')) = 'single_choice_set'");
            });
            return;
        }

        if (in_array($raw, ['multiple choice question (mcq)', 'multiple choice question', 'multiple choice', 'mcq'], true)) {
            $query->where(function ($q) use ($table) {
                $q->where(function ($sub) use ($table) {
                    $sub->where(function ($s) use ($table) {
                        $s->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) IN ('Multiple Choice Questions', 'MCQ')")
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'single_select'");
                    })->orWhere(function ($s) use ($table) {
                        $s->where("{$table}.question_format_code", 'mcq')
                          ->where(function ($s2) use ($table) {
                              $s2->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) != 'Single Choice Set'")
                                 ->orWhereRaw("JSON_EXTRACT({$table}.answer, '$.sub_type') IS NULL");
                          })
                          ->where(function ($s2) use ($table) {
                              $s2->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) != 'single_choice_set'")
                                 ->orWhereRaw("JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode') IS NULL");
                          });
                    });
                })->orWhere(function ($sub) use ($table) {
                    $sub->where("{$table}.question_type_id", 1)
                        ->whereNull("{$table}.question_format_code")
                        ->whereRaw("JSON_EXTRACT({$table}.answer, '$.sub_type') IS NULL");
                });
            });
            return;
        }

        if (in_array($raw, ['true / false', 'true/false', 'true_false', 'true false'], true)) {
            $query->where(function ($q) use ($table) {
                $q->where("{$table}.question_format_code", 'true_false')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) = 'True / False'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'true_false'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.item_form')) = 'true_false'");
            });
            return;
        }

        if (in_array($raw, ['fill in the blanks', 'fill in the blank', 'fill_blank', 'fill_in_the_blanks'], true)) {
            $query->where(function ($q) use ($table) {
                $q->where("{$table}.question_format_code", 'fill_blank')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) IN ('Fill in the Blanks', 'Fill in the Blank')")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'text_blank'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.item_form')) = 'fill_blank'");
            });
            return;
        }

        if (in_array($raw, ['flash cards', 'flash card', 'flash_cards', 'flash_card', 'flip_card'], true)) {
            $query->where(function ($q) use ($table) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) = 'Flash Cards'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'flip_card'")
                  ->orWhere("{$table}.question_format_code", 'flash_card');
            });
            return;
        }

        if (in_array($raw, ['drag and drop', 'drag & drop', 'drag_drop'], true)) {
            $query->where(function ($q) use ($table) {
                $q->where("{$table}.question_format_code", 'drag_drop')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) IN ('Drag and Drop', 'Drag & Drop')");
            });
            return;
        }

        if (in_array($raw, ['drag the words', 'drag text', 'drag_text', 'drag_words'], true)) {
            $query->where(function ($q) use ($table) {
                $q->whereIn("{$table}.question_format_code", ['drag_text', 'drag_words'])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) IN ('Drag the Words', 'Drag Text')")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'drag_words'");
            });
            return;
        }

        if (in_array($raw, ['mark the words', 'mark_the_words', 'mark_words'], true)) {
            $query->where(function ($q) use ($table) {
                $q->where("{$table}.question_format_code", 'mark_the_words')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) = 'Mark the Words'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'mark_words'");
            });
            return;
        }

        if (in_array($raw, ['match following / memory game', 'match following', 'match the following', 'memory game', 'match_following', 'memory_game'], true)) {
            $query->where(function ($q) use ($table) {
                $q->whereIn("{$table}.question_format_code", ['match_following', 'memory_game'])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) IN ('Memory Game', 'Match the Following')")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'memory_game'");
            });
            return;
        }

        if (in_array($raw, ['image hotspots', 'image hotspot', 'image_hotspots', 'image_hotspot'], true)) {
            $query->where(function ($q) use ($table) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.sub_type')) = 'Image Hotspots'")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.answer, '$.interaction_settings.mode')) = 'image_hotspots'")
                  ->orWhere("{$table}.question_format_code", 'image_hotspots');
            });
            return;
        }

        // Default: try exact match on format_code or g_qtype_code
        $query->where(function ($q) use ($table, $typeFilter) {
            $q->where("{$table}.question_format_code", $typeFilter)
              ->orWhere("{$table}.g_qtype_code", $typeFilter);
        });
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

