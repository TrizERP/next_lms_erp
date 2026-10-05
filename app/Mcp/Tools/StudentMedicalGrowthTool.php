<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentMedicalService;

/**
 * Height and weight as recorded. No index is calculated from them.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class StudentMedicalGrowthTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentMedicalService $service)
    {
    }

    public function name(): string
    {
        return 'student_medical.growth';
    }

    public function description(): string
    {
        return 'Height and weight measurements recorded for students of this institute in this academic '
            .'year, exactly as recorded. The columns carry no unit, so no index is calculated and no child '
            .'is described as under or over any weight. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student_medical.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->growth($context, $arguments);
    }
}
