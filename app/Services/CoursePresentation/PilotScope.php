<?php

namespace App\Services\CoursePresentation;

/**
 * Where the Course Presentation pilot is switched on (config course_presentation_pilot.scope).
 *
 * A chapter is allowed only when its id, standard, subject AND school all match one entry. Nothing here touches
 * the database: callers hand in the chapter row they read.
 */
class PilotScope
{
    /** @var array<int,array<string,int>> */
    private array $chapters;
    private string $label;

    public function __construct(?array $scope = null)
    {
        $scope ??= (array) config('course_presentation_pilot.scope', []);
        $this->chapters = array_values((array) ($scope['chapters'] ?? []));
        $this->label = (string) ($scope['label'] ?? 'the pilot chapter');
    }

    public function allows(object|array $chapter): bool
    {
        $row = (array) $chapter;
        foreach ($this->chapters as $allowed) {
            if ((int) ($row['id'] ?? 0) === (int) $allowed['chapter_id']
                && (int) ($row['standard_id'] ?? 0) === (int) $allowed['standard_id']
                && (int) ($row['subject_id'] ?? 0) === (int) $allowed['subject_id']
                && (int) ($row['sub_institute_id'] ?? 0) === (int) $allowed['sub_institute_id']) {
                return true;
            }
        }

        return false;
    }

    public function message(): string
    {
        return "The Course Presentation workflow is available only for {$this->label}.";
    }
}
