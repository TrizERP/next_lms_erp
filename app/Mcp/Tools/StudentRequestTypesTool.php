<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentRequestService;

/**
 * The request types this institute has defined, so a question can name one.
 *
 * Exists for the same reason `exams.list` does: a caller asking about "transfer
 * certificate requests" needs the id `student_requests.list` filters on, and guessing it
 * from a title is how a filter silently matches nothing.
 */
class StudentRequestTypesTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentRequestService $service)
    {
    }

    public function name(): string
    {
        return 'student_requests.types';
    }

    public function description(): string
    {
        return 'The student change request types defined for this institute, with whether each requires '
            .'a proof document and what that document is called. Use it to resolve a request type named '
            .'in a question into the id student_requests.list filters on. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100],
            ],
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

        return $this->service->types($context, $arguments);
    }
}
