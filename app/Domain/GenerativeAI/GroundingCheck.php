<?php

namespace App\Domain\GenerativeAI;

use App\Domain\Templates\PromptTemplate;

/**
 * Does this generation actually have anything to work from?
 *
 * The rest of the platform already refuses to assert what it cannot support: a claim
 * with no citable evidence is dropped rather than softened. Generation had no equivalent
 * rule, and the gap produced exactly the failure it was built to prevent — asked to
 * summarise a course catalogue with no courses in the prompt, a model does not stop, it
 * writes "this catalogue currently shows no courses", which reads as a finding about the
 * school rather than what it is: a statement about an empty prompt.
 *
 * So a template may declare which of its variables carry the data it is summarising. If
 * every one of them is empty, the generation is refused before the model is called. That
 * is cheaper, and far more honest.
 *
 * Declared on the template, in the existing `variables` array:
 *
 *   {"key": "records", "label": "Courses shown", "grounding": true}
 *
 * A template with no `grounding` variables is unaffected — plenty of templates legitimately
 * write from instructions alone.
 */
final class GroundingCheck
{
    /**
     * Values that are syntactically present but carry no information.
     *
     * These are the placeholders the workspace itself substitutes when a page reports
     * nothing ("none listed", "none reported"), plus the usual empty spellings. Without
     * this list the check would pass on the literal string "none listed" and defeat
     * the entire purpose.
     */
    private const EMPTY_MARKERS = [
        '', '0', '-', '—', 'n/a', 'na', 'null', 'nil', 'none', 'no',
        'none listed', 'none reported', 'none found', 'none available',
        'not available', 'not reported', 'no records', 'no data', 'empty',
    ];

    /**
     * @return array{
     *   required: bool, grounded: bool,
     *   variables: array<int, string>, empty: array<int, string>, present: array<int, string>
     * }
     */
    public static function inspect(PromptTemplate $template, array $variables): array
    {
        $declared = self::groundingKeys($template);

        if ($declared === []) {
            return [
                'required' => false,
                'grounded' => true,
                'variables' => [],
                'empty' => [],
                'present' => [],
            ];
        }

        $empty = [];
        $present = [];

        foreach ($declared as $key) {
            self::hasContent($variables[$key] ?? null)
                ? $present[] = $key
                : $empty[] = $key;
        }

        return [
            'required' => true,
            // One grounded variable is enough. A catalogue summary can work from the
            // rows even if the metrics tiles are empty.
            'grounded' => $present !== [],
            'variables' => $declared,
            'empty' => $empty,
            'present' => $present,
        ];
    }

    /**
     * The message a caller sees when generation is refused.
     *
     * It names what was missing, because "could not generate" sends someone hunting
     * through logs for a fault that is really a missing input.
     */
    public static function refusalMessage(PromptTemplate $template, array $report): string
    {
        return sprintf(
            'There is nothing to summarise: %s did not receive any content (%s). '
            . 'Rather than describe an empty page as if it were an empty catalogue, nothing was generated.',
            $template->name,
            implode(', ', $report['empty'])
        );
    }

    /**
     * @return array<int, string>
     */
    private static function groundingKeys(PromptTemplate $template): array
    {
        $keys = [];

        foreach ($template->variables as $variable) {
            if (is_array($variable) && ! empty($variable['grounding']) && ! empty($variable['key'])) {
                $keys[] = (string) $variable['key'];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Content means something a reader could learn from — not merely a non-null value.
     */
    private static function hasContent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            return array_filter($value, fn ($item) => self::hasContent($item)) !== [];
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalised = strtolower(trim($value));
        $normalised = trim($normalised, " \t\n\r\0\x0B.:;");

        return $normalised !== '' && ! in_array($normalised, self::EMPTY_MARKERS, true);
    }
}
