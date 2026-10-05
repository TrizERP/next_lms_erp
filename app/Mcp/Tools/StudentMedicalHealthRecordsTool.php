<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentMedicalService;

/**
 * Health notes, with an attached document reported as present and never returned.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class StudentMedicalHealthRecordsTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentMedicalService $service)
    {
    }

    public function name(): string
    {
        return 'student_medical.health_records';
    }

    public function description(): string
    {
        return 'General health notes recorded for students of this institute in this academic year, with the '
            .'doctor and whether a document is attached. An attached medical document is reported as '
            .'present and never returned. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->healthRecords($context, $arguments);
    }
}
