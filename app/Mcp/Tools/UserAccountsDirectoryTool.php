<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\UserAccountService;

/**
 * Who has an ERP account in this institute, and what state it is in.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class UserAccountsDirectoryTool extends AbstractMcpTool
{
    public function __construct(private readonly UserAccountService $service)
    {
    }

    public function name(): string
    {
        return 'user_accounts.directory';
    }

    public function description(): string
    {
        return 'The ERP accounts in this institute, with the profile, department, status, whether the account is '
            .'an administrator or a portal user, and the join, expiry and last-login dates. Payroll, bank, PAN '
            .'and Aadhaar columns on the staff record are NOT readable through this tool. `last_login` is the '
            .'only activity recorded — there is no session or action history — so nothing may describe how '
            .'much anybody uses the system. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_profile_id' => ['type' => 'integer', 'minimum' => 1],
                'department_id' => ['type' => 'integer', 'minimum' => 1],
                'include_inactive' => ['type' => 'boolean', 'description' => 'Inactive accounts are excluded by default.'],
                'never_logged_in_only' => ['type' => 'boolean', 'description' => 'Only accounts with no login RECORDED.'],
                'administrators_only' => ['type' => 'boolean'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'user.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->directory($context, $arguments);
    }
}
