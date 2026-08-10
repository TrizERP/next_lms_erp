<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AdmissionMcpService;
use App\Services\Mcp\McpRequestContext;

class AdmissionsListEnquiriesTool extends AbstractMcpTool
{
    public function __construct(private readonly AdmissionMcpService $service)
    {
    }

    protected function name(): string
    {
        return 'admissions.listEnquiries';
    }

    protected function description(): string
    {
        return 'List real admission enquiries for the authenticated institute and academic year.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search_text' => ['type' => 'string'],
                'only_pending' => ['type' => 'boolean', 'default' => true],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return [
            'risk' => 'read',
            'required_permission' => 'admission.confirm',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);
        return $this->service->listEnquiries($context, $arguments);
    }
}
