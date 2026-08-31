<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PeopleDirectoryService;

/**
 * Students by where they sit, rather than by name.
 *
 * `students.search` finds one person; this returns a cohort. A filter naming a class the
 * institute does not have returns nothing and says so, rather than falling back to every
 * student — which is how "students in 9C" silently becomes "the whole school".
 */
class StudentsDirectoryTool extends AbstractMcpTool
{
    public function __construct(private readonly PeopleDirectoryService $service)
    {
    }

    protected function name(): string
    {
        return 'students.directory';
    }

    protected function description(): string
    {
        return 'List students enrolled in a given grade, standard (class) or division (section) '
            . 'for the current academic year. Use this for cohort questions such as "students in 8B" '
            . 'or "how many in Grade 5". To find one named student, use students.search instead.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'grade_id' => ['type' => 'integer', 'minimum' => 1],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'standard_name' => ['type' => 'string', 'description' => 'Exact standard name, e.g. "8".'],
                'division_name' => ['type' => 'string', 'description' => 'Exact division name, e.g. "B".'],
                'active_only' => ['type' => 'boolean', 'default' => true],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->students($context, $arguments);
    }
}
