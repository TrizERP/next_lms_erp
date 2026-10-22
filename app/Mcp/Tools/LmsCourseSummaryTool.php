<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\LmsCourseSummaryService;
use App\Services\Mcp\McpRequestContext;

/**
 * The chapters of a course and how much of each has a lesson plan.
 *
 * `lms.courses` says which courses exist and `curriculum_planning.status` reports coverage
 * by unit; neither answers "summarise the chapters and lesson plan for this course". This
 * reads both ends of that question in one call, and says plainly when a chapter has no plan.
 */
class LmsCourseSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly LmsCourseSummaryService $service)
    {
    }

    public function name(): string
    {
        return 'lms.course_summary';
    }

    public function description(): string
    {
        return 'The chapters of a published LMS course and the lesson plan written for each: topics '
            . 'and periods planned and the first learning objective. Name a class and subject for the '
            . 'chapters of one course; name only a class for one line per course. Answers "summarise '
            . 'the chapters and lesson plan for Grade 8 Science" and "which chapters have no lesson plan".';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Restrict to one class.'],
                'standard_name' => ['type' => 'string', 'description' => 'Exact standard name, e.g. "8". Use instead of standard_id.'],
                'subject_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Restrict to one subject.'],
                'question' => ['type' => 'string', 'description' => 'The user\'s words; a subject named in them narrows the courses.'],
                'syear' => ['type' => 'integer', 'minimum' => 2000, 'description' => 'Academic year the lesson plans were written for.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'lms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
