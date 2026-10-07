<?php

namespace App\Services\QuestionGeneration\H5p;

/**
 * One H5P content type the generator writes for.
 *
 * The selected content type decides everything specific to the interaction: the
 * prompt the model is given, the JSON it must return, how that JSON is validated and
 * how a validated question becomes the row shape the existing save pipeline takes.
 * The pipeline itself (quota, dedup, validateRows, prepareRow, persist) is shared and
 * does not know which type it is carrying.
 *
 * Adding a type (fill blank, matching, ...) is one class implementing this interface,
 * one entry in H5pContentTypeRegistry::TYPES and a catalogue row.
 *
 * A "slot" is what the server assigns to one question before the model writes it:
 * ['level' => Bloom level, 'dok' => int, 'difficulty' => Easy|Medium|Hard, 'points' => int].
 * Bloom, DOK, difficulty and marks are server-owned, so the model is never asked to
 * invent them and a wrong one cannot be saved.
 */
interface H5pContentType
{
    /** The question_type_catalog code this type generates, e.g. "mcq". */
    public function formatCode(): string;

    /** The `type` value each question must carry in the model's JSON. */
    public function questionType(): string;

    /** The H5P library this content plays as, e.g. "H5P.MultiChoice". */
    public function h5pType(): string;

    public function h5pLabel(): string;

    /** Short noun used in the task line, e.g. "multiple-choice". */
    public function taskLabel(): string;

    /** The H5P-specific generation rules for $count questions. */
    public function prompt(int $count): string;

    /** The JSON Schema text shown to the model. */
    public function responseSchema(): string;

    /** Why one model question is not acceptable, or null when it is. */
    public function validateQuestion(array $question): ?string;

    /** Why the questions together are not acceptable (balance, duplicates), or null. */
    public function validateSet(array $questions): ?string;

    /**
     * A validated question as a row for the existing pipeline: the eight column keys
     * plus the `answer` envelope, in the shape the format's own prepareRow() and
     * validateRows() already expect.
     *
     * @param array{level: string, dok: int, difficulty: string, points: int} $slot
     */
    public function toRow(array $question, array $slot, string $envelopeVersion): array;
}
