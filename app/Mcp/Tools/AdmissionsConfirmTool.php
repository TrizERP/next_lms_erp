<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Mcp\ConfirmableMcpToolInterface;
use App\Services\Mcp\AdmissionMcpService;
use App\Services\Mcp\McpRequestContext;

class AdmissionsConfirmTool extends AbstractMcpTool implements ConfirmableMcpToolInterface
{
    public function __construct(private readonly AdmissionMcpService $service)
    {
    }

    protected function name(): string
    {
        return 'admissions.confirm';
    }

    protected function description(): string
    {
        return 'Confirm an admission using the existing ERP admission workflow after explicit confirmation.';
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

    protected function isReadOnly(): bool
    {
        return false;
    }

    protected function annotations(): array
    {
        return [
            'risk' => 'approval',
            'required_permission' => 'admission.confirm',
            'requires_confirmation' => true,
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        return $this->preview($arguments, $context);
    }

    public function preview(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);
        return $this->service->previewConfirm($context, $arguments);
    }

    public function executeConfirmed(array $arguments, McpRequestContext $context, array $confirmation): array
    {
        $this->authorize($context);
        $payload = $confirmation['arguments'] ?? $arguments;
        return $this->service->confirm($context, $payload);
    }
}
