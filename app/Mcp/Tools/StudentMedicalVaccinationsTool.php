<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentMedicalService;

/**
 * Vaccinations recorded. No row means none recorded, not unvaccinated.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class StudentMedicalVaccinationsTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentMedicalService $service)
    {
    }

    public function name(): string
    {
        return 'student_medical.vaccinations';
    }

    public function description(): string
    {
        return 'Vaccinations recorded for students of this institute in this academic year. A student with '
            .'no row has no vaccination RECORDED, which is not the same as unvaccinated. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'vaccination_type' => ['type' => 'string'],
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

        return $this->service->vaccinations($context, $arguments);
    }
}
