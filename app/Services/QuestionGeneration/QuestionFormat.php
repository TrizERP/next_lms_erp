<?php

namespace App\Services\QuestionGeneration;

/**
 * One question format the generator can write: everything that differs between
 * "five true/false statements" and "five case studies", and nothing that does not.
 *
 * WHAT STAYS IN QuestionGenerationService. The concept slice, the Bloom quota
 * maths, batching, the DeepSeek call, JSON extraction, the dedup corpus and the
 * transaction are the same for every format and remain there. A format only
 * answers the questions below.
 *
 * THE TWO LEGACY FORMATS. `mcq` and the legacy `narrative` alias keep the prompt
 * text the service has always carried, byte for byte, so isLegacy() is true for
 * them and the service builds their prompt exactly as before; constructionRules()
 * and responseSchema() are never called on them. Every other format is
 * self-describing and goes through the generic path.
 */
interface QuestionFormat
{
    /** Registry key: the question_type_catalog.code, or 'narrative' for the legacy alias. */
    public function code(): string;

    /** Fallback display name; the catalogue's own label wins when it has one. */
    public function label(): string;

    /** True for the two formats whose prompt stays in the service unchanged. */
    public function isLegacy(): bool;

    /** The engine family, 'mcq' or 'narrative'. Selects legacy prompt and quota behaviour. */
    public function engine(): string;

    /** The value the model must echo in the response wrapper's `question_type`. */
    public function responseType(): string;

    /**
     * The catalogue code written to question_format_code AND answer.item_form.
     * Null for the legacy narrative alias, which is not a catalogue form.
     */
    public function persistedFormatCode(): ?string;

    /** lms_question_type_id to use when the catalogue has no row for this code. */
    public function fallbackQuestionTypeId(): int;

    /** Marks when the catalogue's default_marks is NULL or outside marksRange(). */
    public function defaultMarks(): int;

    /** @return array{0: int, 1: int} inclusive [min, max] marks one item may carry */
    public function marksRange(): array;

    /** @return list<string> Bloom levels this format can honestly be written at */
    public function allowedBloomLevels(): array;

    public function promptVersion(): string;

    /** Questions per DeepSeek call. */
    public function batchSize(): int;

    public function temperature(): float;

    /** Noun phrase for the TASK line: "Write 5 {taskLabel} rows". */
    public function taskLabel(): string;

    /** The answer.sub_type a quota row at this Bloom level carries ('' when the format has none). */
    public function subTypeFor(string $level): string;

    /** The format's CONSTRUCTION RULES body (without the heading or the schema). */
    public function constructionRules(int $total): string;

    /** The JSON Schema the model is asked to satisfy. */
    public function responseSchema(): string;

    /**
     * Format-specific row checks, run AFTER the shared ones (required keys, Bloom
     * level, points, learning_outcome). Return a reason to skip the row, or null.
     */
    public function validateRow(array $row): ?string;

    /**
     * Turn a validated row into what is stored: synthesise the options a typed
     * answer's readers expect, build readable model answers, set sub_part_labels.
     * Runs once per valid row, before persistence.
     */
    public function prepareRow(array $row): array;

    /** True when dedup must be scoped to this format rather than to the coarse type id. */
    public function scopesDedupByFormat(): bool;
}
