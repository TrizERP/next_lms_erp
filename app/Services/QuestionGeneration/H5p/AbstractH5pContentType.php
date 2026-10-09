<?php

namespace App\Services\QuestionGeneration\H5p;

/**
 * Checks and row-building every H5P content type repeats.
 */
abstract class AbstractH5pContentType implements H5pContentType
{
    /**
     * What the model must supply besides the interaction itself: why the answer is
     * right, and what in the concept slice the question rests on. Same provenance the
     * existing prompts demand (system prompt rule 2), so nothing ungrounded is saved.
     */
    protected function provenanceReason(array $q): ?string
    {
        if (mb_strlen($this->text($q['explanation'] ?? null)) < 30) {
            return 'explanation is required (at least 30 characters)';
        }

        foreach (['knowledge_refs', 'learning_outcome'] as $key) {
            $values = $q[$key] ?? null;
            if (!is_array($values) || $values === [] || !array_is_list($values)) {
                return "{$key} must be a non-empty list of strings copied from the concept slice";
            }
            foreach ($values as $value) {
                if ($this->text($value) === '') {
                    return "{$key} must contain only non-empty strings";
                }
            }
        }

        if (array_key_exists('hint', $q) && $q['hint'] !== null && !is_string($q['hint'])) {
            return 'hint must be a string or null';
        }

        return null;
    }

    /**
     * The columns and answer fields both types share, built from a validated question.
     *
     * @param array{level: string, dok: int, difficulty: string, points: int} $slot
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [row without answer, answer envelope]
     */
    protected function baseRow(array $q, array $slot, string $title, string $questionType, string $subType, string $envelopeVersion, int $seconds): array
    {
        $refs = array_values(array_map(fn ($v) => trim((string) $v), $q['knowledge_refs']));
        $outcomes = array_values(array_map(fn ($v) => trim((string) $v), $q['learning_outcome']));
        $level = $slot['level'];

        // Remember-level items are not hinted: a fact cannot be hinted without being
        // supplied (system prompt rule 11).
        $hint = $level === 'Remember' ? null : $this->text($q['hint'] ?? null);

        $row = [
            'question_title' => $title,
            'description' => mb_substr("Tests {$level}-level understanding of: {$refs[0]}", 0, 240),
            'subconcept' => mb_substr($refs[0], 0, 240),
            'points' => (int) $slot['points'],
            'multiple_answer' => 0,
            'hint_text' => $hint === '' ? null : $hint,
            'learning_outcome' => $outcomes,
        ];

        $answer = [
            'v' => $envelopeVersion,
            'question_type' => $questionType,
            'sub_type' => $subType,
            'bloom_level' => $level,
            'dok_level' => (int) $slot['dok'],
            'difficulty' => $slot['difficulty'],
            'stimulus' => null,
            'estimated_time_seconds' => $seconds,
            'explanation' => $this->text($q['explanation']),
            'remediation' => mb_substr('Reteach: ' . $refs[0], 0, 240),
            'knowledge_refs' => $refs,
            'ability_ref' => null,
            'misconception_refs' => [],
        ];

        return [$row, $answer];
    }

    /** A comparison key: case, spacing and punctuation do not make two texts different. */
    protected function fingerprint(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    protected function words(string $value): int
    {
        $value = trim(strip_tags($value));

        return $value === '' ? 0 : count(preg_split('/\s+/u', $value) ?: []);
    }

    /** @param list<string> $stems */
    protected function duplicateStem(array $stems): ?string
    {
        $seen = [];
        foreach ($stems as $i => $stem) {
            $key = $this->fingerprint($stem);
            if (isset($seen[$key])) {
                return 'questions ' . ($seen[$key] + 1) . ' and ' . ($i + 1) . ' are the same question';
            }
            $seen[$key] = $i;
        }

        return null;
    }
}
