<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\UserIcardService;

/**
 * Staff a card can be printed for, and which cards are missing a photo, employee number or profile.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class UserIcardRosterTool extends AbstractMcpTool
{
    public function __construct(private readonly UserIcardService $service)
    {
    }

    public function name(): string
    {
        return 'user_icard.roster';
    }

    public function description(): string
    {
        return 'The staff of this institute a user identity card can be printed for, with the fields a card '
            .'prints: name, employee number, profile, department and whether a photograph is on file. Payroll, '
            .'bank, PAN and Aadhaar columns on the staff record are NOT readable through this tool. '
            .'`account_expired` is the ERP account expiry on the staff record, not a card expiry — no card '
            .'issue or expiry date is recorded anywhere. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_profile_id' => ['type' => 'integer', 'minimum' => 1],
                'department_id' => ['type' => 'integer', 'minimum' => 1],
                'account_expired_only' => ['type' => 'boolean', 'description' => 'Only staff whose ERP account expiry date has passed.'],
                'missing_photo_only' => ['type' => 'boolean'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
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

        return $this->service->roster($context, $arguments);
    }
}
