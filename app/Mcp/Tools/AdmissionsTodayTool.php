<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AdmissionsTodayService;
use App\Services\Mcp\McpRequestContext;

class AdmissionsTodayTool extends AbstractMcpTool
{
    public function __construct(private readonly AdmissionsTodayService $service)
    {
    }

    protected function name(): string
    {
        return 'admissions.today';
    }

    protected function description(): string
    {
        return 'Return today admission registrations for the authenticated institute.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'Optional override; defaults to today in server timezone.'],
                'admission_status' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->todaysAdmissions($context, $arguments);
    }
}
