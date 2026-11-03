<?php

namespace App\Domain\KnowledgeGraph;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;

/**
 * Which `tblmenumaster` rows the current MCP-scoped caller is actually
 * allowed to see — the same rule the real sidebar already enforces
 * (`App\Http\Middleware\MenuMiddleware::buildMenus()` /
 * `App\Http\Controllers\api\MenuRightsController::getMenuRightsLevelWise()`),
 * replicated here rather than re-derived, because this is a tenant-isolation
 * boundary and the existing logic is the one already trusted for it.
 *
 * Two checks, both required:
 *   - Tenant provisioning: `tblmenumaster.sub_institute_id` (or `.client_id`
 *     for a multi-tenant admin) is a COMMA-SEPARATED LIST of institute ids,
 *     checked with FIND_IN_SET — confirmed live, every row holds a list, not
 *     a single id or NULL.
 *   - Per-user/role rights: a menu id must appear in
 *     `tblgroupwise_rights` (by profile) or `tblindividual_rights` (by user)
 *     for this user/profile/tenant — opt-IN, not opt-out.
 *
 * Both are applied together in one query per call, mirroring
 * MenuMiddleware's own three branches (ordinary staff, student, multi-tenant
 * admin) exactly. The one piece deliberately NOT replicated is
 * MenuMiddleware's hardcoded `+= '37,41,42'` for the 'ADMIN' profile name —
 * three specific legacy menu ids unrelated to anything this feature
 * navigates; skipping them does not widen access, only narrows it very
 * slightly for that one profile, which is the safe direction to err in here.
 */
class MenuRightsResolver
{
    /**
     * The set of tblmenumaster ids this scope may see, or null if nothing
     * could be resolved (no user id / no institute) — callers should treat
     * null the same as an empty set: show nothing rather than guess.
     *
     * @return int[]|null
     */
    public function allowedMenuIds(McpRequestContext $scope): ?array
    {
        $userId = $scope->userId;
        $subInstituteId = $scope->selectedInstituteId;

        if ($userId === null || $subInstituteId === null) {
            return null;
        }

        if ((int) $subInstituteId === 0 && $scope->isAdmin) {
            $row = $this->rightsQuery('tbluser', $userId)
                ->join('tblmenumaster as m', function ($join) use ($scope) {
                    $join->whereRaw(
                        '(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(?, m.client_id)',
                        [$scope->clientId]
                    );
                })
                ->selectRaw('GROUP_CONCAT(DISTINCT m.id) AS ids')
                ->first();
        } elseif ($scope->isStudent) {
            $row = $this->rightsQuery('tblstudent', $userId)
                ->join('tblmenumaster as m', function ($join) use ($subInstituteId) {
                    $join->whereRaw(
                        '(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(?, m.sub_institute_id)',
                        [$subInstituteId]
                    );
                })
                ->selectRaw('GROUP_CONCAT(DISTINCT m.id) AS ids')
                ->first();
        } else {
            $row = $this->rightsQuery('tbluser', $userId)
                ->join('tblmenumaster as m', function ($join) use ($subInstituteId) {
                    $join->whereRaw(
                        '(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(?, m.sub_institute_id)',
                        [$subInstituteId]
                    );
                })
                ->selectRaw('GROUP_CONCAT(DISTINCT m.id) AS ids')
                ->first();
        }

        $ids = $row->ids ?? null;

        if ($ids === null || $ids === '') {
            return [];
        }

        return array_map('intval', explode(',', $ids));
    }

    /** True when $menuId is in $allowedMenuIds (or that set is null — caller already decided to allow). */
    public function allows(?array $allowedMenuIds, int $menuId): bool
    {
        return $allowedMenuIds === null || in_array($menuId, $allowedMenuIds, true);
    }

    private function rightsQuery(string $personTable, int $userId)
    {
        return DB::table($personTable . ' as u')
            ->leftJoin('tblindividual_rights as i', function ($join) {
                $join->whereRaw('u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id AND u.user_profile_id = i.profile_id');
            })
            ->leftJoin('tblgroupwise_rights as g', function ($join) {
                $join->whereRaw('u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id');
            })
            ->where('u.id', $userId);
    }
}
