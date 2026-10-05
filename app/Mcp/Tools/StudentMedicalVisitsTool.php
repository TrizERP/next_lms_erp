<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentMedicalService;

/**
 * Infirmary visits. Clinical detail only for a read that names one student.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class StudentMedicalVisitsTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentMedicalService $service)
    {
    }

    public function name(): string
    {
        return 'student_medical.visits';
    }

    public function description(): string
    {
        return 'Infirmary visits recorded for this institute and academic year, with the date, case number, '
            .'doctor and whether the case is still open. Clinical detail - complaint, symptoms, disease '
            .'and treatment - is returned only when the read names one student, and is null otherwise. '
            .'Nothing about the visits is interpreted. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Naming one student includes the clinical detail.'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'open_only' => ['type' => 'boolean', 'description' => 'Cases with no close date recorded.'],
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

        return $this->service->visits($context, $arguments);
    }
}
