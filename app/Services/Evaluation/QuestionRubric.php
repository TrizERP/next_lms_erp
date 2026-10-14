<?php

namespace App\Services\Evaluation;

/**
 * Reads the marking key a written question carries.
 *
 * Generated written questions (short answer, long answer, case study ...) store
 * their key as a JSON document in `lms_question_master.answer`: a `model_answer`,
 * one `marking_points` entry per mark, `keywords`, `common_errors` and a
 * `full_credit_threshold`. Hand-authored questions store plain text or HTML.
 *
 * Until this existed the grader was handed the raw JSON text and told it was the
 * "model answer", so the per-mark criteria were buried in braces and never used
 * as a rubric. This turns the document into a structure the grader can be shown
 * properly.
 *
 * Pure on purpose -- no database, no framework -- so it is unit-testable and the
 * same rules apply wherever a key is read.
 *
 * It never invents anything. A document with neither a `model_answer` nor any
 * `marking_points` is left as it was found (the caller keeps its plain-text
 * behaviour), and an empty value stays empty so the caller can say there is no
 * key rather than guess one.
 */
final class QuestionRubric
{
    /**
     * @return array{
     *     structured: bool,
     *     model_answer: string,
     *     marking_points: array<int,array{criterion:string, accept:array<int,string>, reject:array<int,string>}>,
     *     keywords: array<int,array{term:string, weight:float, synonyms:array<int,string>}>,
     *     common_errors: array<int,array{erroneous_answer:string, mark_ceiling:int}>,
     *     full_credit_threshold: int|null
     * }
     */
    public static function parse(string $stored): array
    {
        $plain = self::plainText($stored);

        $empty = [
            'structured' => false,
            'model_answer' => $plain,
            'marking_points' => [],
            'keywords' => [],
            'common_errors' => [],
            'full_credit_threshold' => null,
        ];

        $trimmed = ltrim($stored);

        if ($trimmed === '' || $trimmed[0] !== '{') {
            return $empty;
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            return $empty;
        }

        $points = self::markingPoints($decoded['marking_points'] ?? null);
        $modelAnswer = self::plainText(is_string($decoded['model_answer'] ?? null) ? $decoded['model_answer'] : '');

        // Not one of ours (a match / drag-drop / fill-blank envelope, say): keep
        // the old behaviour of passing its text through untouched, because for
        // those the raw document IS the key.
        if ($modelAnswer === '' && $points === []) {
            return $empty;
        }

        if ($modelAnswer === '') {
            $modelAnswer = implode(' ', array_map(static fn (array $point) => $point['criterion'], $points));
        }

        $threshold = $decoded['full_credit_threshold'] ?? null;

        return [
            'structured' => true,
            'model_answer' => $modelAnswer,
            'marking_points' => $points,
            'keywords' => self::keywords($decoded['keywords'] ?? null),
            'common_errors' => self::commonErrors($decoded['common_errors'] ?? null),
            'full_credit_threshold' => is_numeric($threshold) && (int) $threshold > 0 ? (int) $threshold : null,
        ];
    }

    /** Question text and keys are stored as HTML by the rich-text authoring screens. */
    public static function plainText(string $value): string
    {
        $value = preg_replace('/<br\s*\/?>/i', ' ', $value) ?? $value;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** @return array<int,array{criterion:string, accept:array<int,string>, reject:array<int,string>}> */
    private static function markingPoints(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $points = [];

        foreach ($value as $entry) {
            // A bare string is accepted as a criterion: older rows were written
            // before the accept / reject lists existed.
            $criterion = is_string($entry)
                ? self::plainText($entry)
                : (is_array($entry) && is_string($entry['criterion'] ?? null) ? self::plainText($entry['criterion']) : '');

            if ($criterion === '') {
                continue;
            }

            $points[] = [
                'criterion' => $criterion,
                'accept' => is_array($entry) ? self::strings($entry['accept'] ?? null) : [],
                'reject' => is_array($entry) ? self::strings($entry['reject'] ?? null) : [],
            ];
        }

        return $points;
    }

    /** @return array<int,array{term:string, weight:float, synonyms:array<int,string>}> */
    private static function keywords(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keywords = [];

        foreach ($value as $entry) {
            $term = is_array($entry) && is_string($entry['term'] ?? null) ? self::plainText($entry['term']) : '';

            if ($term === '') {
                continue;
            }

            $keywords[] = [
                'term' => $term,
                'weight' => max(0.0, min(1.0, (float) ($entry['weight'] ?? 1))),
                'synonyms' => self::strings($entry['synonyms'] ?? null),
            ];
        }

        return $keywords;
    }

    /** @return array<int,array{erroneous_answer:string, mark_ceiling:int}> */
    private static function commonErrors(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $errors = [];

        foreach ($value as $entry) {
            $text = is_array($entry) && is_string($entry['erroneous_answer'] ?? null)
                ? self::plainText($entry['erroneous_answer'])
                : '';

            if ($text === '') {
                continue;
            }

            $errors[] = [
                'erroneous_answer' => $text,
                'mark_ceiling' => max(0, (int) ($entry['mark_ceiling'] ?? 0)),
            ];
        }

        return $errors;
    }

    /** @return array<int,string> */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item) && ($text = self::plainText($item)) !== '') {
                $strings[] = $text;
            }
        }

        return $strings;
    }
}
