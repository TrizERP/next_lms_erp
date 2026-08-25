<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AiTemplateService;
use App\Services\Mcp\McpRequestContext;

/**
 * Fills an AI-category template with a real admission enquiry.
 *
 * Read-only: it renders and returns HTML, it does not send, store or attach
 * anything. Whatever happens to the output afterwards — a follow-up message, a
 * confirmation step — stays behind the admission workflow's own approval gate.
 */
class AiTemplatesRenderTool extends AbstractMcpTool
{
    public function __construct(private readonly AiTemplateService $service)
    {
    }

    protected function name(): string
    {
        return 'ai.templates.render';
    }

    protected function description(): string
    {
        return 'Render an AI-category template from template_master using a real admission enquiry record.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enquiry_id' => ['type' => 'integer', 'minimum' => 1],
                'template_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The template id, when known.'],
                'title' => ['type' => 'string', 'description' => 'The template title, matched exactly and then partially.'],
            ],
            'required' => ['enquiry_id'],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return [
            'risk' => 'read',
            'required_permission' => 'template.read',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->renderForEnquiry($context, $arguments);
    }
}
