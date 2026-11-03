<?php

namespace App\Services\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reads and writes a role's menu rights (`tblgroupwise_rights`), and records every change.
 *
 * ── HOW RIGHTS ARE STORED, AND WHAT THAT MEANS FOR A WRITER ─────────────────
 *
 * A row in `tblgroupwise_rights` means "this role can see this menu"; the sidebar takes the
 * union of those rows and does not read `can_view`. So a row whose flags are all off is not
 * "no access", it is access with no actions, and the only honest way to revoke is to delete
 * the row. `apply()` does exactly that.
 *
 * ── RULES THIS ENFORCES THAT THE OLD SAVE DID NOT ───────────────────────────
 *
 *  · Only the menus named in a change are touched. The old save deleted every row for the
 *    role and re-inserted the lot, so a save made from a partial view lost whatever was not on
 *    screen.
 *  · `dashboard_right` and `is_mobile` are flags on the SAME row as the web rights, so one
 *    menu is one row. Rows are matched on menu whatever their `is_mobile` value, because the
 *    sidebar reads them all; ignoring the ones that carry the flag would duplicate a row.
 *  · A menu can only be granted if it is assignable to the role (`tblprofilewise_menu`).
 *  · add / edit / delete / dashboard / mobile all imply view: the row is what makes the menu
 *    visible, so a menu cannot carry any of them and stay hidden.
 *  · Granting a menu also grants view on its parent menus when they are assignable, because a
 *    child whose parent is not granted is not rendered in the sidebar at all.
 *  · Every change is written to `role_access_changes` with the actor, the before and the
 *    after, so a batch can be undone.
 */
final class RoleRightsService
{
    public const FLAGS = ['view', 'add', 'edit', 'delete'];

    /** Flags that sit on the row beside the web rights. */
    public const EXTRAS = ['dashboard', 'mobile'];

    /** @var array<int, array{parent:int,level:int,name:string}>|null */
    private ?array $menus = null;

    /** @return array<int, array{parent:int,level:int,name:string}> id => menu */
    public function menus(): array
    {
        if ($this->menus !== null) {
            return $this->menus;
        }

        $this->menus = [];
        foreach (DB::table('tblmenumaster')->where('status', 1)->get(['id', 'parent_menu_id', 'level', 'name']) as $row) {
            $this->menus[(int) $row->id] = [
                'parent' => (int) $row->parent_menu_id,
                'level' => (int) $row->level,
                'name' => trim((string) $row->name),
            ];
        }

        return $this->menus;
    }

    /**
     * Menus this role may be granted: every active menu that is switched on for the school, plus
     * whatever `tblprofilewise_menu` assigns the role and whatever the role already has.
     *
     * It used to be `tblprofilewise_menu` alone. That table is only written by the legacy profile
     * screen, so menus added later (Talent Management and its ten sub-menus, among others) hold
     * rights for the Admin role but were never assigned, and the screen hid whole modules from
     * the people who could already open them. The Organization Management "Role and Permissions"
     * screen works from the full menu tree for the same reason.
     *
     * @return array<int,true>
     */
    public function allowedMenuIds(int $tenant, int $profile): array
    {
        $ids = DB::table('tblmenumaster')->where('status', 1)
            ->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$tenant])->pluck('id')->all();
        $ids = array_merge(
            $ids,
            DB::table('tblprofilewise_menu')->where('sub_institute_id', $tenant)->where('user_profile_id', $profile)->pluck('menu_id')->all(),
            DB::table('tblgroupwise_rights')->where('sub_institute_id', $tenant)->where('profile_id', $profile)->pluck('menu_id')->all(),
        );

        // Only menus that exist and are switched on can be listed or granted.
        $active = $this->menus();

        return array_filter(array_fill_keys(array_map('intval', $ids), true), fn ($v, $id) => isset($active[$id]), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The role's current rights.
     *
     * @return array<int, array{view:bool,add:bool,edit:bool,delete:bool,dashboard:bool,mobile:bool}> menu_id => rights
     */
    public function rightsFor(int $tenant, int $profile): array
    {
        $out = [];
        foreach ($this->rows($tenant, $profile) as $row) {
            $menu = (int) $row->menu_id;
            $current = [
                'view' => (bool) $row->can_view,
                'add' => (bool) $row->can_add,
                'edit' => (bool) $row->can_edit,
                'delete' => (bool) $row->can_delete,
                'dashboard' => $this->hasDashboard() && (bool) ($row->dashboard_right ?? 0),
                'mobile' => (bool) ($row->is_mobile ?? 0),
            ];
            if (! isset($out[$menu])) {
                $out[$menu] = $current;

                continue;
            }
            // The table has no unique key, so a menu can be duplicated: count the union of its rows.
            foreach ($current as $flag => $value) {
                $out[$menu][$flag] = $out[$menu][$flag] || $value;
            }
        }

        return $out;
    }

    /**
     * Change a role's rights.
     *
     * @param  array<int, array<string,mixed>>  $changes  each {menu_id, view, add, edit, delete}
     * @param  array{id:?int,name:?string}  $actor
     * @return array{batchId:string,changed:int,autoGranted:array<int,int>,rejected:array<int,array<string,mixed>>,entries:array<int,array<string,mixed>>}
     */
    public function apply(
        int $tenant,
        int $profile,
        array $changes,
        array $actor,
        string $source = 'manual',
        ?string $note = null,
        ?int $relatedProfile = null,
    ): array {
        $allowed = $this->allowedMenuIds($tenant, $profile);
        $current = $this->rightsFor($tenant, $profile);
        $menus = $this->menus();

        $wanted = [];
        $rejected = [];
        foreach ($changes as $change) {
            $menu = (int) ($change['menu_id'] ?? 0);
            if ($menu <= 0 || ! isset($menus[$menu])) {
                $rejected[] = ['menuId' => $menu, 'reason' => 'That menu does not exist or is switched off.'];

                continue;
            }
            if (! isset($allowed[$menu])) {
                $rejected[] = ['menuId' => $menu, 'name' => $menus[$menu]['name'], 'reason' => 'This menu is not assigned to the role, so it cannot be granted.'];

                continue;
            }

            $wanted[$menu] = $this->normalise($change);
        }

        // A parent menu must be granted for its children to appear in the sidebar.
        $auto = [];
        foreach ($wanted as $menu => $rights) {
            if (! $this->grants($rights)) {
                continue;
            }
            foreach ($this->ancestors($menu) as $parent) {
                if (! isset($allowed[$parent]) || isset($wanted[$parent]) || isset($auto[$parent])) {
                    continue;
                }
                if (! ($current[$parent]['view'] ?? false)) {
                    $auto[$parent] = ['view' => true, 'add' => false, 'edit' => false, 'delete' => false];
                }
            }
        }

        $batchId = (string) Str::uuid();
        $entries = [];

        DB::transaction(function () use ($tenant, $profile, $wanted, $auto, $current, $actor, $source, $note, $relatedProfile, $batchId, &$entries) {
            foreach ([['wanted', $wanted], ['ancestor', $auto]] as [$kind, $set]) {
                foreach ($set as $menu => $after) {
                    $before = $current[$menu] ?? null;
                    // A flag the change does not mention stays as it is.
                    $afterStored = $this->grants($after)
                        ? [
                            'view' => $after['view'], 'add' => $after['add'], 'edit' => $after['edit'], 'delete' => $after['delete'],
                            'dashboard' => $after['dashboard'] ?? ($before['dashboard'] ?? false),
                            'mobile' => $after['mobile'] ?? ($before['mobile'] ?? false),
                        ]
                        : null;

                    if ($this->same($before, $afterStored)) {
                        continue;
                    }

                    $this->write($tenant, $profile, (int) $menu, $afterStored);
                    $entries[] = [
                        'menuId' => (int) $menu,
                        'name' => $this->menus()[(int) $menu]['name'] ?? '',
                        'before' => $before,
                        'after' => $afterStored,
                        'source' => $kind === 'ancestor' ? 'ancestor' : $source,
                    ];
                }
            }

            foreach ($entries as $entry) {
                DB::table('role_access_changes')->insert([
                    'batch_id' => $batchId,
                    'sub_institute_id' => $tenant,
                    'actor_id' => $actor['id'] ?? null,
                    'actor_name' => $actor['name'] ?? null,
                    'profile_id' => $profile,
                    'menu_id' => $entry['menuId'],
                    'before_rights' => $entry['before'] === null ? null : json_encode($entry['before']),
                    'after_rights' => $entry['after'] === null ? null : json_encode($entry['after']),
                    'source' => $entry['source'],
                    'related_profile_id' => $relatedProfile,
                    'note' => $note !== null ? mb_substr($note, 0, 255) : null,
                    'created_at' => now(),
                ]);
            }
        });

        return [
            'batchId' => $batchId,
            'changed' => count($entries),
            'autoGranted' => array_keys($auto),
            'rejected' => $rejected,
            'entries' => $entries,
        ];
    }

    /**
     * Reverse a batch. A menu whose rights have changed since the batch is skipped rather
     * than overwritten, and reported, so an undo can never silently erase a newer decision.
     *
     * @param  array{id:?int,name:?string}  $actor
     * @return array{batchId:string,reverted:int,skipped:array<int,array<string,mixed>>}|null  null when there is nothing to undo
     */
    public function undo(int $tenant, string $batchId, array $actor): ?array
    {
        $entries = DB::table('role_access_changes')
            ->where('sub_institute_id', $tenant)->where('batch_id', $batchId)
            ->whereNull('undone_by_batch')->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $profile = (int) $entries->first()->profile_id;
        $current = $this->rightsFor($tenant, $profile);
        $newBatch = (string) Str::uuid();
        $reverted = 0;
        $skipped = [];

        // Parents last: a parent granted only so its child could render goes back only if no
        // granted child still needs it, which is known once the children have been reverted.
        $entries = $entries->sortBy(fn ($e) => $e->source === 'ancestor' ? 1 : 0)->values();

        DB::transaction(function () use ($entries, $tenant, $profile, &$current, $actor, $newBatch, $batchId, &$reverted, &$skipped) {
            foreach ($entries as $entry) {
                $menu = (int) $entry->menu_id;
                $after = $entry->after_rights === null ? null : json_decode((string) $entry->after_rights, true);
                $before = $entry->before_rights === null ? null : json_decode((string) $entry->before_rights, true);

                if (! $this->same($current[$menu] ?? null, $after)) {
                    $skipped[] = [
                        'menuId' => $menu,
                        'name' => $this->menus()[$menu]['name'] ?? '',
                        'reason' => 'Its rights were changed again after this batch, so they were left as they are.',
                    ];

                    continue;
                }

                if ($entry->source === 'ancestor' && $before === null && $this->neededByChild($menu, $current)) {
                    $skipped[] = [
                        'menuId' => $menu,
                        'name' => $this->menus()[$menu]['name'] ?? '',
                        'reason' => 'A menu under it is still granted, and it would not show without this one.',
                    ];

                    continue;
                }

                $this->write($tenant, $profile, $menu, $before);
                if ($before === null) {
                    unset($current[$menu]);
                } else {
                    $current[$menu] = $before;
                }
                DB::table('role_access_changes')->insert([
                    'batch_id' => $newBatch,
                    'sub_institute_id' => $tenant,
                    'actor_id' => $actor['id'] ?? null,
                    'actor_name' => $actor['name'] ?? null,
                    'profile_id' => $profile,
                    'menu_id' => $menu,
                    'before_rights' => $entry->after_rights,
                    'after_rights' => $entry->before_rights,
                    'source' => 'undo',
                    'note' => 'Undo of batch '.$batchId,
                    'created_at' => now(),
                ]);
                DB::table('role_access_changes')->where('id', $entry->id)->update(['undone_by_batch' => $newBatch]);
                $reverted++;
            }
        });

        return ['batchId' => $newBatch, 'reverted' => $reverted, 'skipped' => $skipped];
    }

    /**
     * Whether any menu currently granted sits below this one.
     *
     * @param  array<int, array<string,bool>>  $current
     */
    private function neededByChild(int $menuId, array $current): bool
    {
        foreach ($current as $other => $rights) {
            if ($other !== $menuId && $this->grants($rights) && in_array($menuId, $this->ancestors((int) $other), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,int> parent menu ids, nearest first */
    public function ancestors(int $menuId): array
    {
        $menus = $this->menus();
        $out = [];
        $guard = 0;
        $parent = $menus[$menuId]['parent'] ?? 0;
        while ($parent > 0 && isset($menus[$parent]) && $guard++ < 6) {
            $out[] = $parent;
            $parent = $menus[$parent]['parent'];
        }

        return $out;
    }

    /**
     * `dashboard` and `mobile` are carried only when the change names them, so a change that
     * does not mention them leaves what the menu already has.
     *
     * @param array<string,mixed> $change
     */
    public function normalise(array $change): array
    {
        $r = [];
        foreach (self::FLAGS as $flag) {
            $r[$flag] = ! empty($change[$flag]);
        }
        foreach (self::EXTRAS as $flag) {
            if (array_key_exists($flag, $change)) {
                $r[$flag] = ! empty($change[$flag]);
            }
        }
        // Doing anything to a menu requires being able to see it.
        if ($r['add'] || $r['edit'] || $r['delete'] || ! empty($r['dashboard']) || ! empty($r['mobile'])) {
            $r['view'] = true;
        }

        return $r;
    }

    /** @param array<string,bool> $rights */
    public function grants(array $rights): bool
    {
        return $rights['view'] || $rights['add'] || $rights['edit'] || $rights['delete'];
    }

    /** Two stored states are the same when both are absent or every flag matches. */
    public function same(?array $a, ?array $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        foreach ([...self::FLAGS, ...self::EXTRAS] as $flag) {
            if (! empty($a[$flag]) !== ! empty($b[$flag])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,bool>|null $after null removes the role's access to the menu */
    private function write(int $tenant, int $profile, int $menu, ?array $after): void
    {
        $existing = $this->rows($tenant, $profile)->where('menu_id', $menu);
        $sort = $existing->first()->sort_order ?? null;

        DB::table('tblgroupwise_rights')
            ->where('sub_institute_id', $tenant)->where('profile_id', $profile)
            ->where('menu_id', $menu)->delete();

        if ($after === null) {
            return;
        }

        $row = [
            'menu_id' => $menu,
            'profile_id' => $profile,
            'sub_institute_id' => $tenant,
            'can_view' => ! empty($after['view']) ? 1 : 0,
            'can_add' => ! empty($after['add']) ? 1 : 0,
            'can_edit' => ! empty($after['edit']) ? 1 : 0,
            'can_delete' => ! empty($after['delete']) ? 1 : 0,
            'is_mobile' => ! empty($after['mobile']) ? 1 : 0,
            'created_at' => now(),
        ];
        if ($this->hasDashboard()) {
            $row['dashboard_right'] = ! empty($after['dashboard']) ? 1 : 0;
        }
        if ($sort !== null) {
            $row['sort_order'] = $sort;
        }

        DB::table('tblgroupwise_rights')->insert($row);
    }

    private function rows(int $tenant, int $profile)
    {
        return DB::table('tblgroupwise_rights')
            ->where('sub_institute_id', $tenant)->where('profile_id', $profile)->get();
    }

    private function hasDashboard(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('tblgroupwise_rights', 'dashboard_right');
    }
}
