<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\ClassTeacherService;
use App\Services\Mcp\McpRequestContext;

/**
 * Who teaches a given class, and what a given teacher teaches.
 *
 * `teachers.directory` reads the staff record, which knows a department and not a class.
 * The teacher-to-class relationship lives only on the timetable, so "who teaches 8B" was
 * previously unanswerable from any registered tool — the assistant could name every
 * teacher in the school and still not say which of them the question was about.
 */
class AcademicsClassTeachersTool extends AbstractMcpTool
{
    public function __construct(private readonly ClassTeacherService $service)
    {
    }

    public function name(): string
    {
        return 'academics.class_teachers';
    }

    public function description(): string
    {
        return 'Which teachers teach a given class, from the timetable, with the subject each '
            . 'takes and how many periods a week. Answers "who teaches 8B", "which teacher takes '
            . 'Science for Standard 9" and, given a teacher, which classes they take. Resolve a '
            . 'class name to standard_id and division_id with academics.structure first.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Narrows to one section. Omit for every section of the standard.',
                ],
                // Names as well as ids, so a planner can fill this straight from the
                // question instead of needing academics.structure first.
                'standard_name' => [
                    'type' => 'string',
                    'description' => 'Exact standard name, e.g. "Standard-10". Use instead of standard_id.',
                ],
                'division_name' => [
                    'type' => 'string',
                    'description' => 'Exact division name, e.g. "A". Use instead of division_id.',
                ],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
                'teacher_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Ask the question the other way round: the classes this teacher takes.',
                ],
                // No academic_year argument, deliberately. The year is part of the
                // scoped context the hydrator resolves and validates against the
                // institute; taking it as a tool argument would let a caller read
                // another year by writing it in the payload.
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'teacher.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->forClass($context, $arguments);
    }
}
