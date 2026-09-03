<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AcademicStructureService;
use App\Services\Mcp\McpRequestContext;

/**
 * The shape of the school — grades, classes and sections.
 *
 * Almost every cohort question needs this first: "8B" has to become a standard id and a
 * section id before anything can be counted about it.
 */
class AcademicsStructureTool extends AbstractMcpTool
{
    public function __construct(private readonly AcademicStructureService $service)
    {
    }

    protected function name(): string
    {
        return 'academics.structure';
    }

    protected function description(): string
    {
        return 'List the grades, standards (classes) and divisions (sections) defined for this '
            . 'institute. Use this to turn a class named in a question — "8B", "Grade 5" — into the '
            . 'ids other tools need. Does not return students.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'grade_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Narrow standards to one grade.'],
                'standard_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Narrow to one standard.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'academics.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->structure($context, $arguments);
    }
}
