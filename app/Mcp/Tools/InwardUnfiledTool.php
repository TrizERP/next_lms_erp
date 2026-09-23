<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\InwardService;
use App\Services\Mcp\McpRequestContext;

/**
 * Inward records with a gap in the register: no physical file location, or no scan attached.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class InwardUnfiledTool extends AbstractMcpTool
{
    public function __construct(private readonly InwardService $service)
    {
    }

    public function name(): string
    {
        return 'inward.unfiled';
    }

    public function description(): string
    {
        return 'Inward records with a gap in the REGISTER — no physical file location recorded, so nobody can '
            .'be told where the paper is, or no scan attached, so there is no copy in the system — oldest '
            .'first. A gap here is not a document awaiting action: this table records no status at all. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'gap' => ['type' => 'string', 'enum' => ['any', 'file_location', 'attachment'], 'default' => 'any'],
                'place_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'inward.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->unfiled($context, $arguments);
    }
}
