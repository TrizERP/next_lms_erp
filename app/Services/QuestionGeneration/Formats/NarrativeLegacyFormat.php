<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;

/**
 * The legacy `question_type: narrative` alias -- a mixed bag of Very Short,
 * Short, Long, Assertion Reason and Case Study items the model apportions
 * across the Bloom ladder.
 *
 * It is NOT a catalogue form, so it is never offered by the formats endpoint,
 * cannot be requested through `question_format_code`, and records no format code
 * on the rows it writes (persistedFormatCode() is null): stamping a mixed batch
 * with one catalogue code would be wrong for most of its rows. The sharper,
 * single-purpose replacements are VeryShortFormat, ShortFormat, LongFormat,
 * AssertionReasonFormat and CaseStudyFormat.
 *
 * LEGACY PATH. As with McqFormat, the prompt text stays in
 * QuestionGenerationService and is pinned by the golden tests.
 */
class NarrativeLegacyFormat extends AbstractQuestionFormat
{
    public function code(): string
    {
        return 'narrative';
    }

    public function label(): string
    {
        return 'Narrative (mixed)';
    }

    public function isLegacy(): bool
    {
        return true;
    }

    public function engine(): string
    {
        return 'narrative';
    }

    public function persistedFormatCode(): ?string
    {
        return null;
    }

    public function fallbackQuestionTypeId(): int
    {
        return 2;
    }

    public function defaultMarks(): int
    {
        return 3;
    }

    public function marksRange(): array
    {
        return [1, 6];
    }

    public function promptVersion(): string
    {
        return (string) config('deepseek.prompt_version', 'qgen-sys-2.0');
    }

    public function batchSize(): int
    {
        return max(1, (int) config('deepseek.batch_size_narrative', 1));
    }

    public function temperature(): float
    {
        return (float) config('deepseek.temperature_narrative', 0.6);
    }

    public function taskLabel(): string
    {
        return 'narrative (constructed-response)';
    }

    public function scopesDedupByFormat(): bool
    {
        return false;
    }

    public function constructionRules(int $total): string
    {
        throw new \LogicException('Legacy narrative keeps its prompt in QuestionGenerationService::userPrompt().');
    }

    public function responseSchema(): string
    {
        throw new \LogicException('Legacy narrative keeps its schema in QuestionGenerationService::narrativeColumnSchema().');
    }

    public function validateRow(array $row): ?string
    {
        throw new \LogicException('Legacy narrative rows are validated by QuestionGenerationService::validateRows().');
    }
}
