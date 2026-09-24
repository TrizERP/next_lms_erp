<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentRequestService;

/**
 * One student change request in full.
 *
 * A request belonging to another institute answers exactly as a request that does not
 * exist: confirming that an id exists elsewhere is itself a disclosure across the tenant
 * boundary.
 */
class StudentRequestDetailsTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentRequestService $service)
    {
    }

    public function name(): string
    {
        return 'student_requests.details';
    }

    public function description(): string
    {
        return 'One student change request in full, with the reason and description given, whether proof '
            .'was required by its type and whether any was supplied, and who decided it and when. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'request_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['request_id'],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student_request.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->details($context, $arguments);
    }
}
