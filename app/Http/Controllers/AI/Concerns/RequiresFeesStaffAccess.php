<?php

namespace App\Http\Controllers\AI\Concerns;

use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Blocks student tokens from the Fees Recommendation panel's five
 * intelligence capabilities — financial risk/recommendation data about
 * named students, which `/api/ai/*` otherwise leaves reachable by any
 * valid JWT (McpAuth derives only three roles — 'student', 'admin', or the
 * 'staff' catch-all for everyone else — see McpAuth::role derivation). This
 * mirrors the access level already enforced one layer down: the governed
 * agents behind these endpoints (`k12_fees`, `k12_fees_predictive`) are
 * themselves registered with `allowed_roles: ["admin","staff"]`.
 *
 * KNOWN GAP, NOT FIXED HERE: this platform's MCP auth model has no distinct
 * "parent" role. A parent token that does not set `is_student=true` is
 * bucketed as 'staff' by McpAuth and is NOT blocked by this check — that is
 * a pre-existing, platform-wide gap in McpAuth's role derivation (shared by
 * every other `/api/ai/*` controller), not something a per-controller gate
 * in Fees can close. A real fix needs a fourth role at the McpAuth layer.
 */
trait RequiresFeesStaffAccess
{
    /**
     * @throws AuthorizationException if the caller authenticated as a student
     */
    protected function assertStaffAccess(McpRequestContext $scope): void
    {
        if ($scope->role === 'student' || $scope->isStudent) {
            throw new AuthorizationException(
                'Fees intelligence data is not available to student accounts.'
            );
        }
    }

    /**
     * For the operational agent-control actions (start/stop/interval/run-now/
     * pipeline) — turning on an automated financial-risk sweep for the whole
     * school is an administrative decision, not a read any staff member
     * should be able to trigger. Reuses AdministratorCheck rather than the
     * raw `role==='admin'` string, since `tbluser.is_admin` is empty for
     * every real admin on this estate and AdministratorCheck already
     * accounts for that (see its own docblock).
     *
     * @throws AuthorizationException if the caller is not a true administrator
     */
    protected function assertAdminAccess(McpRequestContext $scope): void
    {
        $this->assertStaffAccess($scope);

        if (! app(AdministratorCheck::class)->isAdministrator($scope)) {
            throw new AuthorizationException(
                'Only an administrator can control Fees agent monitoring.'
            );
        }
    }
}
