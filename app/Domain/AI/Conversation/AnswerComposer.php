<?php

namespace App\Domain\AI\Conversation;

/**
 * Turns the pipeline's output into what the person actually reads.
 *
 * Answers are structured rather than free text — a headline, typed sections, offered
 * actions, and the follow-up questions that move the journey to its next stage. That
 * structure is what lets the console render an approve button under a recommendation
 * instead of printing the word "approve" and hoping.
 *
 * Nothing here generates prose from a model. Every sentence is assembled from rows the
 * pipeline already produced, which is the same rule the explanation layer follows: the
 * answer cannot say more than the evidence does.
 */
class AnswerComposer
{
    /** A section the console knows how to render. */
    public const TEXT = 'text';
    public const RECORDS = 'records';
    public const KEY_VALUES = 'key_values';
    public const EVIDENCE = 'evidence';
    public const STEPS = 'steps';
    public const COMPARISON = 'comparison';

    public function make(
        string $headline,
        array $sections = [],
        array $actions = [],
        array $followUps = []
    ): array {
        return [
            'headline' => $headline,
            'sections' => array_values(array_filter($sections)),
            'actions' => array_values($actions),
            'follow_ups' => array_values($followUps),
        ];
    }

    public function text(string $title, string $body): ?array
    {
        if (trim($body) === '') {
            return null;
        }

        return ['type' => self::TEXT, 'title' => $title, 'body' => $body];
    }

    /**
     * A list of records — students, cases, recommendations. Each item carries its own
     * badge and detail lines so the console does not have to know the domain.
     */
    public function records(string $title, array $items, ?string $emptyMessage = null): ?array
    {
        if ($items === []) {
            return $emptyMessage === null ? null : $this->text($title, $emptyMessage);
        }

        return ['type' => self::RECORDS, 'title' => $title, 'items' => array_values($items)];
    }

    public function keyValues(string $title, array $pairs): ?array
    {
        $pairs = array_filter($pairs, fn ($value) => $value !== null && $value !== '');

        if ($pairs === []) {
            return null;
        }

        return [
            'type' => self::KEY_VALUES,
            'title' => $title,
            'items' => array_map(
                fn ($key, $value) => ['label' => $key, 'value' => is_scalar($value) ? (string) $value : json_encode($value)],
                array_keys($pairs),
                $pairs
            ),
        ];
    }

    /**
     * Evidence rendered as citations: what was observed, from which table, when.
     * The source is always shown, because evidence without provenance is an assertion.
     */
    public function evidence(string $title, array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        return [
            'type' => self::EVIDENCE,
            'title' => $title,
            'items' => array_map(fn (array $row) => [
                'id' => $row['id'] ?? null,
                'kind' => $row['kind'] ?? null,
                'summary' => $row['summary'] ?? '',
                'value' => $this->formatValue($row),
                'source' => trim(sprintf(
                    '%s%s',
                    $row['source']['table'] ?? ($row['source']['service'] ?? 'computed'),
                    isset($row['source']['id']) && $row['source']['id'] !== null ? ' #' . $row['source']['id'] : ''
                )),
                'observed_at' => $row['observed_at'] ?? null,
                'verified' => (bool) ($row['verified'] ?? false),
                'is_generated' => (bool) ($row['is_generated'] ?? false),
            ], array_values($rows)),
        ];
    }

    /**
     * Workflow steps, in order, with the state each is actually in.
     */
    public function steps(string $title, array $steps): ?array
    {
        if ($steps === []) {
            return null;
        }

        return ['type' => self::STEPS, 'title' => $title, 'items' => array_values($steps)];
    }

    /**
     * Before and after, for an outcome.
     */
    public function comparison(string $title, array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        return ['type' => self::COMPARISON, 'title' => $title, 'items' => array_values($rows)];
    }

    public function action(string $key, string $label, string $intent, array $payload = [], string $style = 'default'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            // The console sends this back as the next question, so a button and a typed
            // sentence go down exactly the same path. One code path, one trace shape.
            'intent' => $intent,
            'utterance' => $payload['utterance'] ?? $label,
            'payload' => array_diff_key($payload, ['utterance' => null]),
            'style' => $style,
        ];
    }

    /**
     * Severity as a word a teacher uses, from the severity the case stores.
     */
    public function severityLabel(?string $severity): string
    {
        return match (strtolower((string) $severity)) {
            'critical' => 'Critical risk',
            'high' => 'High risk',
            'moderate' => 'Medium risk',
            'low' => 'Low risk',
            default => 'Risk',
        };
    }

    private function formatValue(array $row): ?string
    {
        if (($row['numeric_value'] ?? null) !== null) {
            return rtrim(rtrim(number_format((float) $row['numeric_value'], 2, '.', ''), '0'), '.')
                . ($row['unit'] ? ' ' . $row['unit'] : '');
        }

        $value = $row['value'] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }
}
