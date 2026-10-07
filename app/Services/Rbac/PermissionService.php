<?php

namespace App\Services\Rbac;

use Illuminate\Support\Facades\DB;

/**
 * permission_check(user, module, action) — the contract tracker row 5 / Decision #37 asks for.
 *
 * > "Use the same permission_check(user, module, action) mechanism as every other gated
 * >  action in this tracker — do not build a parallel content-permissions system."
 *
 * So this builds NO new storage. It reads `tblindividual_rights` then `tblgroupwise_rights`
 * — the same two tables, in the same precedence order, that
 * app/Http/Middleware/checkPermission.php:40-56 already uses. The rights admin screens
 * that already exist keep working unchanged; there is nothing new to administer.
 *
 * WHAT IS DIFFERENT FROM checkPermission
 *
 *  1. It FAILS CLOSED. checkPermission.php:66-69 has its "no rights row at all" rejection
 *     commented out, so a user with no grant currently falls through to allowed. Here,
 *     no row means deny.
 *
 *  2. No menu-id allowlists. checkPermission.php:73,77 hardcodes
 *     `in_array($menu_id,[200])` and `in_array($menu_id,[31,82,386])` to skip
 *     delete/edit checks. Those are environment-specific ids and a Decision #23
 *     violation (configuration granting a permission). Modules resolve by
 *     tblmenumaster.link instead.
 *
 *  3. It takes an explicit user/profile/tenant, so no request flag can switch it off.
 *     (This originally cited a `type=API` bypass in checkPermission; that bypass was
 *     removed upstream on 2026-09-08. The design point stands.)
 *
 * SCOPE. This is content-scoped on purpose. The platform-wide fix to the bypass is
 * Track D's (Decisions & Risk Log #2, Central Engines row 1-2). When their RBAC engine
 * lands it should replace the BODY of check() — every caller in this repo goes through
 * check() or the `perm:` middleware, so that swap needs no caller changes.
 */
class PermissionService
{
    /** Per-request memo. Rights are not cached across requests — see config note. */
    private array $memo = [];

    /**
     * May this user perform this action on this module?
     *
     * @param  int          $userId            tbluser.id
     * @param  int|null     $profileId         tbluser.user_profile_id
     * @param  int|string   $subInstituteId    the tenant the request is acting in
     * @param  string       $module            a key from config('rbac_modules.modules')
     * @param  string       $action            view | create | update | delete
     */
    public function check(int $userId, ?int $profileId, int|string $subInstituteId, string $module, string $action): bool
    {
        $rights = $this->rightsFor($userId, $profileId, $subInstituteId, $module);

        if ($rights === null) {
            // Unresolvable module, or no grant row anywhere. Deny.
            return (bool) config('rbac_modules.allow_when_unresolved', false);
        }

        $column = config("rbac_modules.actions.{$action}");

        if ($column === null) {
            // An unregistered action is a programming error, not a permission question.
            // Deny rather than guess.
            return false;
        }

        return (int) ($rights[$column] ?? 0) === 1;
    }

    /**
     * Every action flag for one module, for the frontend.
     *
     * `/api/menu-rights` cannot answer this: MenuRightsController::getMenuRightsLevelWise
     * selects only `GROUP_CONCAT(distinct m.id) AS MID`, i.e. WHICH MENUS EXIST, never the
     * CRUD columns. That is why the content UI has never been able to gate a button — it
     * has no per-action data to gate on.
     *
     * @return array<string,bool>
     */
    public function actionsFor(int $userId, ?int $profileId, int|string $subInstituteId, string $module): array
    {
        $rights = $this->rightsFor($userId, $profileId, $subInstituteId, $module);
        $out = [];

        foreach ((array) config('rbac_modules.actions', []) as $action => $column) {
            $out[$action] = $rights === null
                ? (bool) config('rbac_modules.allow_when_unresolved', false)
                : (int) ($rights[$column] ?? 0) === 1;
        }

        return $out;
    }

    /** @return list<string> */
    public function modules(): array
    {
        return array_keys((array) config('rbac_modules.modules', []));
    }

    /**
     * Resolve a module name to the menu ids that carry its rights.
     *
     * By LINK, never by a hardcoded id — ids differ per environment, which is exactly
     * what makes checkPermission's allowlists fragile.
     *
     * @return list<int>
     */
    public function menuIdsFor(string $module): array
    {
        // NOT config("rbac_modules.modules.{$module}.links") - module names contain a
        // dot ("lms.content") and config() treats dots as path separators, so that form
        // resolves to modules -> lms -> content and always returns []. The whole array is
        // fetched and indexed directly instead.
        //
        // The failure was silent and fail-closed: every permission check denied, so the
        // symptom would have been "nobody can create content" rather than an error.
        $modules = (array) config('rbac_modules.modules', []);
        $links = (array) ($modules[$module]['links'] ?? []);

        if ($links === []) {
            return [];
        }

        // Resolved in the order the registry declares, because the config documents
        // "tried in order; the first that resolves wins" and a bare whereIn() does not
        // honour that - it returns whatever order the database chooses.
        //
        // This matters: `lms.content` resolves to BOTH menu 236 and menu 270, and
        // rightsFor() takes ->first() of the matching rights rows. Without a deterministic
        // order, which menu's grant decides is undefined. Measured 2026-09-08: of 89
        // overlapping (profile, tenant) pairs, ONE genuinely disagrees - profile 28 /
        // tenant 47 has can_edit=0 on menu 236 and can_edit=1 on menu 270. So this was
        // never purely theoretical; the registry order now decides it deterministically.
        $rows = DB::table('tblmenumaster')
            ->whereIn('link', $links)
            ->where('status', 1)
            ->pluck('id', 'link')
            ->all();

        $ordered = [];
        foreach ($links as $link) {
            if (isset($rows[$link])) {
                $ordered[] = (int) $rows[$link];
            }
        }

        return $ordered;
    }

    /**
     * The winning rights row: individual overrides group, exactly as checkPermission does.
     *
     * @return array<string,mixed>|null  null = unresolvable module, or no grant at all
     *
     * Protected, not private, so a test can substitute the storage lookup without a
     * database. phpunit.xml points at the LIVE shared vivek_erp, so a test that queried
     * tblgroupwise_rights for real would be reading 56 tenants' production rights.
     */
    protected function rightsFor(int $userId, ?int $profileId, int|string $subInstituteId, string $module): ?array
    {
        $key = implode('|', [$userId, (string) $profileId, (string) $subInstituteId, $module]);

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $menuIds = $this->menuIdsFor($module);

        if ($menuIds === []) {
            return $this->memo[$key] = null;
        }

        // Individual rights win over group rights — the same precedence as
        // checkPermission.php:52-56.
        // Walked in registry order, one menu at a time, so the winning row is
        // deterministic. Individual rights beat group rights for the SAME menu before we
        // move on to the next - the same precedence checkPermission.php:52-56 uses.
        foreach ($menuIds as $menuId) {
            $individual = DB::table('tblindividual_rights')
                ->where('menu_id', $menuId)
                ->where('user_id', $userId)
                ->where('sub_institute_id', $subInstituteId)
                ->first();

            if ($individual !== null) {
                return $this->memo[$key] = (array) $individual;
            }

            if ($profileId === null) {
                continue;
            }

            $group = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $profileId)
                ->where('sub_institute_id', $subInstituteId)
                ->first();

            if ($group !== null) {
                return $this->memo[$key] = (array) $group;
            }
        }

        return $this->memo[$key] = null;
    }
}
