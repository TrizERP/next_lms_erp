<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AdmissionMcpService;
use App\Services\Mcp\McpRequestContext;

class AdmissionsGetEnquiryDetailsTool extends AbstractMcpTool
{
    public function __construct(private readonly AdmissionMcpService $service)
    {
    }

    protected function name(): string
    {
        return 'admissions.getEnquiryDetails';
    }

    protected function description(): string
    {
        return 'Load the complete admission enquiry and registration details for a selected candidate.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enquiry_id' => ['type' => 'integer'],
            ],
            'required' => ['enquiry_id'],
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
        return $this->service->getEnquiryDetails($context, $arguments);
    }
}
