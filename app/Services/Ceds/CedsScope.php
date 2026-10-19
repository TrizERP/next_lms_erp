<?php

namespace App\Services\Ceds;

/**
 * Which chapters the CEDS picker and lesson planner are switched on for (config `ceds_fit_matrix.scope`).
 *
 * Today that is one chapter: Std 9 Science, Chapter 1. A chapter counts only when its id, standard and subject
 * ALL match an entry, so a chapter that merely reuses a subject or a standard, or an id typed in a request,
 * never qualifies. Pure: it reads the config array it is given and nothing else.
 */
class CedsScope
{
    /** @var list<array{chapter_id:int,standard_id:int,subject_id:int}> */
    private array $chapters;
    private string $label;

    /** @param array<string,mixed>|null $scope defaults to config('ceds_fit_matrix.scope') */
    public function __construct(?array $scope = null)
    {
        $scope ??= (array) config('ceds_fit_matrix.scope', []);
        $this->chapters = array_values((array) ($scope['chapters'] ?? []));
        $this->label = (string) ($scope['label'] ?? 'the enabled chapter');
    }

    /** @param object|array<string,mixed> $chapter a chapter_master row (id, standard_id, subject_id) */
    public function allows(object|array $chapter): bool
    {
        $row = (array) $chapter;
        foreach ($this->chapters as $allowed) {
            if ((int) ($row['id'] ?? 0) === (int) $allowed['chapter_id']
                && (int) ($row['standard_id'] ?? 0) === (int) $allowed['standard_id']
                && (int) ($row['subject_id'] ?? 0) === (int) $allowed['subject_id']) {
                return true;
            }
        }

        return false;
    }

    public function message(): string
    {
        return "The teaching-method planner is available only for {$this->label}.";
    }
}
