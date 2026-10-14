<?php

namespace App\Mcp\Concerns;

use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * For tools that only a school administrator may use.
 *
 * Declares the roles the MCP gate lets through (`admin` and `staff`, because the gate cannot
 * tell an administrator from staff in an estate where `tbluser.is_admin` is empty — see
 * `AdministratorCheck`) and then enforces the real rule itself. A tool using this is exactly
 * as restricted as before; it is the check that changed, not who is let in.
 */
trait RequiresAdministrator
{
    /** @return array<int, string> */
    protected function allowedRoles(): array
    {
        return ['admin', 'staff'];
    }

    protected function authorize(McpRequestContext $context): void
    {
        parent::authorize($context);

        if (! app(AdministratorCheck::class)->isAdministrator($context)) {
            throw new AuthorizationException($this->administratorOnlyMessage());
        }
    }

    protected function administratorOnlyMessage(): string
    {
        return 'This is limited to school administrators, and your login is not one.';
    }
}
