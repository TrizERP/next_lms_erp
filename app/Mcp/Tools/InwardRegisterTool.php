<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\InwardService;
use App\Services\Mcp\McpRequestContext;

/**
 * The inward register: documents received, with the place they came from and the file they were filed into.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class InwardRegisterTool extends AbstractMcpTool
{
    public function __construct(private readonly InwardService $service)
    {
    }

    public function name(): string
    {
        return 'inward.register';
    }

    public function description(): string
    {
        return 'The inward register for this institute and academic year: one row per document received, with '
            .'its inward number, title, the place it came from, the physical file it was filed into and '
            .'whether a scan is attached. This table records NO status, owner, due date or action taken, so '
            .'nothing it returns can be called pending, overdue or closed. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'place_id' => ['type' => 'integer', 'minimum' => 1],
                'file_location_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->register($context, $arguments);
    }
}
