<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FeesCollectionReportService;
use App\Services\Mcp\McpRequestContext;

class FeesCollectionReportTool extends AbstractMcpTool
{
    public function __construct(private readonly FeesCollectionReportService $service)
    {
    }

    protected function name(): string
    {
        return 'fees.collection_report';
    }

    protected function description(): string
    {
        return 'Return a read-only fees collection report for the authenticated institute.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from_date' => ['type' => 'string', 'format' => 'date'],
                'to_date' => ['type' => 'string', 'format' => 'date'],
                'student_id' => ['type' => 'integer'],
                'payment_mode' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->report($context, $arguments);
    }
}
