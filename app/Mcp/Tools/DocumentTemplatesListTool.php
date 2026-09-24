<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\DocumentTemplateService;
use App\Services\Mcp\McpRequestContext;

/**
 * Document templates with their status, version and the merge fields their content contains.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class DocumentTemplatesListTool extends AbstractMcpTool
{
    public function __construct(private readonly DocumentTemplateService $service)
    {
    }

    public function name(): string
    {
        return 'doc_templates.list';
    }

    public function description(): string
    {
        return 'Document templates for this institute, with the name, category, status, version and the '
            .'`{{merge_field}}` placeholders parsed from the stored content. The document body itself is '
            .'measured but never returned. These tables are empty across this whole estate today, and an empty '
            .'result means exactly that — never a prompt to describe templates a school might want. Changes '
            .'nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'description' => 'As stored, e.g. draft or published.'],
                'category' => ['type' => 'string'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
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

        return $this->service->list($context, $arguments);
    }
}
