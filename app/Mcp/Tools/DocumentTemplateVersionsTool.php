<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\DocumentTemplateService;
use App\Services\Mcp\McpRequestContext;

/**
 * The saved revisions of one document template.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class DocumentTemplateVersionsTool extends AbstractMcpTool
{
    public function __construct(private readonly DocumentTemplateService $service)
    {
    }

    public function name(): string
    {
        return 'doc_templates.versions';
    }

    public function description(): string
    {
        return 'The saved revisions of one document template of this institute, with who saved each and the '
            .'merge fields it contained. The document body is never returned. A version is a save, not an '
            .'approval: this table records no reviewer and no sign-off. A template id belonging to another '
            .'institute returns not-found. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'template_id' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ],
            'required' => ['template_id'],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'document_templates.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->versions($context, $arguments);
    }
}
