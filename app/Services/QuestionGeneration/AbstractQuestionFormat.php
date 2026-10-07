<?php

namespace App\Services\QuestionGeneration;

/**
 * Defaults shared by every format, and the config override hook.
 *
 * Knobs a deployment may want to tune without a release (batch size,
 * temperature, prompt version) read `config/question_formats.php` first and fall
 * back to the class constant, so a format works with no config entry at all.
 */
abstract class AbstractQuestionFormat implements QuestionFormat
{
    public const ALL_LEVELS = ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate', 'Create'];

    protected const BATCH_SIZE = 5;
    protected const TEMPERATURE = 0.4;
    protected const PROMPT_VERSION = '1.0';

    public function isLegacy(): bool
    {
        return false;
    }

    public function engine(): string
    {
        return 'narrative';
    }

    public function responseType(): string
    {
        return $this->code();
    }

    public function persistedFormatCode(): ?string
    {
        return $this->code();
    }

    public function marksRange(): array
    {
        return [$this->defaultMarks(), $this->defaultMarks()];
    }

    public function allowedBloomLevels(): array
    {
        return self::ALL_LEVELS;
    }

    public function promptVersion(): string
    {
        return (string) $this->setting('prompt_version', 'qgen-fmt-' . $this->code() . '-' . static::PROMPT_VERSION);
    }

    public function batchSize(): int
    {
        return max(1, (int) $this->setting('batch_size', static::BATCH_SIZE));
    }

    public function temperature(): float
    {
        return (float) $this->setting('temperature', static::TEMPERATURE);
    }

    public function subTypeFor(string $level): string
    {
        return '';
    }

    public function prepareRow(array $row): array
    {
        return $row;
    }

    public function scopesDedupByFormat(): bool
    {
        return true;
    }

    /** A per-format override from config/question_formats.php, else the default. */
    protected function setting(string $key, mixed $default): mixed
    {
        if (!function_exists('config')) {
            return $default;
        }

        return config('question_formats.formats.' . $this->code() . '.' . $key, $default);
    }

    // -----------------------------------------------------------------
    // Helpers the concrete formats share
    // -----------------------------------------------------------------

    /**
     * Construction rules every format repeats for the columns they all fill.
     * Mirrors the wording the MCQ block uses so the two read alike to the model.
     */
    protected function commonRules(string $descriptionExample): string
    {
        return <<<RULES
description  (VARCHAR(250) -- teacher-facing one-liner, <= 240 chars)
- {$descriptionExample}
- Written for a teacher scanning a list of 50 items. Not a restatement of the stem.

subconcept  (VARCHAR(250), <= 240 chars)
- The single `knowledge` string most central to this item. Exact from the slice.

answer.explanation -- 2-3 sentences, student-facing, why the answer is correct.
answer.remediation -- 1 sentence, teacher-facing: what to reteach if the class gets it wrong.
                     Draw on the related misconception's `correction` field when present.
hint_text -- see SYSTEM rule 11. null at Remember level.
learning_outcome -- JSON array of exact `outcome` strings from `learning_outcomes[]`.
answer.knowledge_refs / ability_ref / misconception_refs -- see SYSTEM rule 2. Exact strings only.
answer.estimated_time_seconds -- a realistic time for a student at this level.
RULES;
    }

    /** Words in a string with markup removed. */
    protected function words(string $value): int
    {
        return $this->wordCount($value);
    }

    /** True when the string is one of the allowed difficulty words. */
    protected function isDifficulty(mixed $value): bool
    {
        return in_array($value, ['Easy', 'Medium', 'Hard'], true);
    }

    /** A non-empty trimmed string, or ''. */
    protected function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    protected function wordCount(string $value): int
    {
        $value = trim(strip_tags($value));

        return $value === '' ? 0 : count(preg_split('/\s+/u', $value) ?: []);
    }

    /** The `answer` envelope of a row, or [] when it is not one. */
    protected function answer(array $row): array
    {
        return is_array($row['answer'] ?? null) ? $row['answer'] : [];
    }
}
