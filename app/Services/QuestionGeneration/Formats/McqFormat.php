<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;

/**
 * Multiple choice -- the format the generator has always written.
 *
 * LEGACY PATH. Its prompt, schema and row validation stay in
 * QuestionGenerationService, byte for byte (QuestionGenerationGoldenTest pins
 * them), so this class is a descriptor: it tells the service which engine to run
 * and what to record, and it holds none of the prompt text. The service reads
 * its batch size, temperature and prompt version from config/deepseek.php exactly
 * as it did before the format seam existed.
 *
 * MCQ, Assertion-Reason and Case-Based MCQ sub-types remain the model's own
 * choice inside this format, as they always were. Assertion & Reason as a
 * format of its own is AssertionReasonFormat.
 */
class McqFormat extends AbstractQuestionFormat
{
    public function code(): string
    {
        return 'mcq';
    }

    public function label(): string
    {
        return 'Multiple choice';
    }

    public function isLegacy(): bool
    {
        return true;
    }

    public function engine(): string
    {
        return 'mcq';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 1;
    }

    public function defaultMarks(): int
    {
        return 1;
    }

    public function promptVersion(): string
    {
        return (string) config('deepseek.prompt_version', 'qgen-sys-2.0');
    }

    public function batchSize(): int
    {
        return max(1, (int) config('deepseek.batch_size_mcq', 2));
    }

    public function temperature(): float
    {
        return (float) config('deepseek.temperature_mcq', 0.4);
    }

    public function taskLabel(): string
    {
        return 'multiple-choice';
    }

    public function scopesDedupByFormat(): bool
    {
        // Existing MCQ dedup (concept + question_type_id) is unchanged.
        return false;
    }

    public function constructionRules(int $total): string
    {
        throw new \LogicException('MCQ keeps its prompt in QuestionGenerationService::userPrompt().');
    }

    public function responseSchema(): string
    {
        throw new \LogicException('MCQ keeps its schema in QuestionGenerationService::mcqColumnSchema().');
    }

    public function validateRow(array $row): ?string
    {
        throw new \LogicException('MCQ rows are validated by QuestionGenerationService::validateRows().');
    }
}
