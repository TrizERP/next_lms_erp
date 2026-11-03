<?php

namespace App\Services\StudyDeck;

/**
 * The deck validator's number-grounding rule (DeckValidator::groundingCheck) as a pure helper, so each stage that WRITES wording can check its own
 * output and use its own repair round, instead of the whole finished deck failing at the end with nobody left to fix it (run re27dc680740a: a unit
 * conversion factor in a "steps" item and a rounded 8640 on a diagram label, neither in the chapter).
 *
 * It mirrors the validator on purpose and must stay in step with it: a number is grounded when it appears, as the same digits, in the chapter
 * text, the attached bank questions, a concept's name or definition, or the chapter's own names. Pure: no database, no model.
 */
final class GroundedNumbers
{
    /** Keys whose values are structure, not wording (same list as DeckValidator::wording). */
    private const SKIP = ['id', 'kind', 'start', 'next', 'after', 'sound', 'x', 'y', 'w', 'h', 'reason', 'style'];

    /**
     * The text a number may be found in, lower-cased.
     *
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => bank questions the deck may use
     */
    public static function allowedText(array $context, array $eligible = []): string
    {
        $allowed = mb_strtolower((string) ($context['ground_truth'] ?? ''));
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                // Tags become spaces, not nothing: stripping "<td>40</td><td>10</td>" to "4010" would hide both numbers.
                $allowed .= ' ' . mb_strtolower((string) preg_replace('/<[^>]+>/', ' ', ($q['stem'] ?? '') . ' ' . implode(' ', array_column((array) ($q['options'] ?? []), 'text')) . ' ' . ($q['explanation'] ?? '') . ' ' . ($q['answer_text'] ?? '')));
            }
        }
        foreach ((array) ($context['concepts'] ?? []) as $c) {
            $allowed .= ' ' . mb_strtolower(($c['name'] ?? '') . ' ' . ($c['definition'] ?? ''));
        }
        $chapter = (array) ($context['chapter'] ?? []);

        return $allowed . ' ' . mb_strtolower(($chapter['chapter_name'] ?? '') . ' ' . ($chapter['subject_name'] ?? '') . ' ' . ($chapter['standard_name'] ?? ''));
    }

    /** Every string of a structure that is slide wording (labels, sentences, items), joined by spaces; ids, flags and coordinates are left out. */
    public static function wording(mixed $node): string
    {
        $out = [];
        $walk = function ($v, $key = null) use (&$walk, &$out): void {
            if (is_array($v)) {
                foreach ($v as $k => $x) {
                    $walk($x, is_string($k) ? $k : $key);
                }
            } elseif (is_string($v) && $v !== '' && !in_array($key, self::SKIP, true)) {
                $out[] = $v;
            }
        };
        $walk($node);

        return implode(' ', $out);
    }

    /**
     * The numbers of `$text` that `$allowed` does not contain. With no allowed text at all nothing can be judged, so nothing is reported.
     *
     * @return array<int,string>
     */
    public static function ungrounded(string $text, string $allowed): array
    {
        if (trim($allowed) === '') {
            return [];
        }
        // Slide and step numbers are navigation, not claims about the subject.
        $text = preg_replace('/\b(?:slide|step)\s+\d+\b/i', '', $text) ?? $text;
        preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $found);
        $bad = [];
        foreach (array_unique($found[0]) as $number) {
            if (!preg_match('/(?<![\d.])' . preg_quote($number, '/') . '(?![\d])/u', $allowed)) {
                $bad[] = $number;
            }
        }

        return $bad;
    }

    /** The sentence a repair round is given for numbers that are not in the chapter. @param array<int,string> $numbers */
    public static function complaint(array $numbers): string
    {
        return 'It uses the number(s) ' . implode(', ', array_slice($numbers, 0, 4)) . ', which are not in the chapter text. Use only numbers the chapter gives (copy them exactly as written there), or none.';
    }
}
