<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentIcardService;

/**
 * One student's identity-card fields, and which of them are missing.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class StudentIcardCardDetailsTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentIcardService $service)
    {
    }

    public function name(): string
    {
        return 'student_icard.card_details';
    }

    public function description(): string
    {
        return 'The identity-card fields for one student, and which of them are missing. No card number, '
            .'issue date or reprint history is reported, because this estate records none. Changes '
            .'nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['student_id'],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student_icard.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->cardDetails($context, $arguments);
    }
}
