<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the person behind a request is a school administrator.
 *
 * WHY `McpRequestContext::$isAdmin` ALONE IS NOT ENOUGH
 *
 * The MCP gate calls someone an administrator only when the login token's `is_admin` is 1 or
 * more, and the token copies `tbluser.is_admin` verbatim. In this estate that column is empty
 * for every user — all 105 "Admin" profile users in institute 1 included — so the gate
 * classifies every non-student as "staff", and any tool restricted to administrators could
 * never be used by anybody. Fee reminders hit exactly this: the person who owns the school
 * was told "You do not have permission to use this MCP tool."
 *
 * The ERP already has an answer to "who is an administrator" that does not depend on that
 * column. `PalApiAuth` documents it: admin-tier profiles (Admin, School admin, …) all chain up
 * to profile id 1 through `tbluserprofilemaster.parent_id`, consistently across institutes,
 * and it uses that convention for the same reason. This applies the same rule, and only to
 * the tools that opt in — the MCP gate itself is untouched, so no other tool's access changes.
 *
 * It widens nothing beyond a genuine administrator: a Teacher, Clerk or Librarian profile does
 * not chain to profile 1 and is still refused. Read-only; one lookup per request.
 */
class AdministratorCheck
{
    /** @var array<int, bool> */
    private array $memo = [];

    public function isAdministrator(McpRequestContext $context): bool
    {
        if ($context->isAdmin) {
            return true;
        }

        if ($context->isStudent || $context->userProfileId === null || $context->userProfileId <= 0) {
            return false;
        }

        $profileId = (int) $context->userProfileId;

        if (isset($this->memo[$profileId])) {
            return $this->memo[$profileId];
        }

        return $this->memo[$profileId] = $this->chainsToProfileOne($profileId);
    }

    protected function chainsToProfileOne(int $profileId): bool
    {
        if ($profileId === 1) {
            return true;
        }

        if (! Schema::hasTable('tbluserprofilemaster')) {
            return false;
        }

        return (int) DB::table('tbluserprofilemaster')->where('id', $profileId)->value('parent_id') === 1;
    }
}
