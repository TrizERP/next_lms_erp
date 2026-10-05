<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\UserIcardService;

/**
 * One member of staff's identity-card fields.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class UserIcardCardDetailsTool extends AbstractMcpTool
{
    public function __construct(private readonly UserIcardService $service)
    {
    }

    public function name(): string
    {
        return 'user_icard.card_details';
    }

    public function description(): string
    {
        return 'The card fields for one member of staff of this institute: name, employee number, profile, '
            .'department, contact and whether a photograph is on file. Payroll, bank and government identity '
            .'numbers are not readable through this tool. A staff id belonging to another institute returns '
            .'not-found. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'staff_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['staff_id'],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'user_icard.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->cardDetails($context, $arguments);
    }
}
