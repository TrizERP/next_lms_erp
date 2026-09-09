<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AiTemplateService;
use App\Services\Mcp\McpRequestContext;

class AiTemplatesListTool extends AbstractMcpTool
{
    public function __construct(private readonly AiTemplateService $service)
    {
    }

    public function name(): string
    {
        return 'ai.templates.list';
    }

    public function description(): string
    {
        return 'List the AI-category templates in the existing template_master library.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Optional text to match against the template title.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return [
            'risk' => 'read',
            'required_permission' => 'template.read',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->listTemplates($context, $arguments);
    }
}
