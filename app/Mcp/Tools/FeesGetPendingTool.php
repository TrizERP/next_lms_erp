<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FeesPendingService;
use App\Services\Mcp\McpRequestContext;

class FeesGetPendingTool extends AbstractMcpTool
{
    public function __construct(private readonly FeesPendingService $service)
    {
    }

    public function name(): string
    {
        return 'fees.getPending';
    }

    public function description(): string
    {
        return 'Load real pending fee details for a selected student within the authenticated ERP scope.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer'],
            ],
            'required' => ['student_id'],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return [
            'risk' => 'read',
            'required_permission' => 'fees.collect',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);
        return $this->service->getPending($context, $arguments);
    }
}
