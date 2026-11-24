<?php

namespace App\Console\Commands;

use App\Services\Access\RoleRightsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives each role the menus its dashboard needs, as defined in config/role_dashboards.php.
 *
 *   php artisan roles:sync-dashboard-rights 1            dry run (default) — writes nothing
 *   php artisan roles:sync-dashboard-rights 1 --list     dry run, with every menu named
 *   php artisan roles:sync-dashboard-rights 1 --apply    write, through RoleRightsService
 *
 * Additive only: a role keeps every right it already has. Every write goes through
 * RoleRightsService::apply(), so it lands in role_access_changes and can be undone from the
 * Roles & Access History tab.
 */
class SyncRoleDashboardRights extends Command
{
    protected $signature = 'roles:sync-dashboard-rights {tenant : sub_institute_id} {--apply : write the changes (default is a dry run)} {--list : name every menu} {--role= : only this role key}';

    protected $description = 'Grant each role the menus its dashboard needs (dry run unless --apply)';

    private const FLAG = ['v' => 'view', 'a' => 'add', 'e' => 'edit', 'd' => 'delete'];

    public function handle(RoleRightsService $service): int
    {
        $tenant = (int) $this->argument('tenant');
        $apply = (bool) $this->option('apply');
        $menus = $service->menus();

        $children = [];
        foreach ($menus as $id => $m) {
            $children[$m['parent']][] = $id;
        }

        $profiles = DB::table('tbluserprofilemaster')->where('sub_institute_id', $tenant)->get(['id', 'name']);
        $this->line($apply ? '<fg=yellow>APPLY — writing to tblgroupwise_rights</>' : '<fg=cyan>DRY RUN — nothing is written</>');
        $this->line("Tenant {$tenant}, ".count($profiles).' profiles');
        $this->newLine();

        $total = 0;
        foreach (config('role_dashboards.roles') as $key => $role) {
            if ($this->option('role') && $this->option('role') !== $key) {
                continue;
            }
            if (empty($role['rights'])) {
                continue;
            }

            $wanted = $this->expand($role['rights'], $menus, $children);

            foreach ($profiles as $profile) {
                if (! in_array(strtolower(trim($profile->name)), $role['profiles'], true)) {
                    continue;
                }

                $allowed = $service->allowedMenuIds($tenant, (int) $profile->id);
                $current = $service->rightsFor($tenant, (int) $profile->id);
                $users = DB::table('tbluser')->where('sub_institute_id', $tenant)->where('user_profile_id', $profile->id)->where('status', '1')->count();

                $changes = [];
                $rejected = [];
                foreach ($wanted as $menu => $flags) {
                    if (! isset($allowed[$menu])) {
                        $rejected[] = $menu;

                        continue;
                    }
                    $have = $current[$menu] ?? ['view' => false, 'add' => false, 'edit' => false, 'delete' => false];
                    $merged = $have;
                    foreach ($flags as $flag) {
                        $merged[$flag] = true;
                    }
                    $merged['view'] = true;
                    $isNew = ! isset($current[$menu]);
                    $adds = array_filter(self::FLAG, fn ($f) => ($merged[$f] ?? false) && ! ($have[$f] ?? false));
                    if ($isNew || $adds) {
                        $changes[] = ['menu_id' => $menu] + array_intersect_key($merged, array_flip(self::FLAG));
                    }
                }

                $this->info("{$role['label']} — profile #{$profile->id} \"{$profile->name}\" ({$users} active users, ".count($current).' menus now)');
                $this->line('  to add / widen: '.count($changes).' menus'.($rejected ? ', not assignable here: '.count($rejected) : ''));

                if ($this->option('list')) {
                    $byTop = [];
                    foreach ($changes as $c) {
                        $byTop[$this->top($c['menu_id'], $menus)][] = $menus[$c['menu_id']]['name'].' ['.$this->flagString($c).']';
                    }
                    foreach ($byTop as $top => $names) {
                        $this->line("    {$top}: ".implode('; ', $names));
                    }
                }

                $total += count($changes);
                if ($apply && $changes) {
                    $result = $service->apply(
                        $tenant,
                        (int) $profile->id,
                        $changes,
                        ['id' => null, 'name' => 'role-dashboard-sync'],
                        'dashboard-blueprint',
                        'Dashboard blueprint: '.$role['label'],
                    );
                    $this->line("  <fg=green>written: {$result['changed']} rows (batch {$result['batchId']}), rejected: ".count($result['rejected']).'</>');
                }
                $this->newLine();
            }
        }

        $this->line($apply ? "Done. {$total} menu rights written." : "Dry run complete. {$total} menu rights WOULD be written. Re-run with --apply to write them.");

        return self::SUCCESS;
    }

    /** @return array<int, string[]> menu id => list of flag names */
    private function expand(array $rights, array $menus, array $children): array
    {
        $out = [];
        foreach ($rights as [$id, $flags, $descendants]) {
            $ids = [$id];
            if ($descendants) {
                $stack = [$id];
                while ($stack) {
                    $next = array_pop($stack);
                    foreach ($children[$next] ?? [] as $child) {
                        $ids[] = $child;
                        $stack[] = $child;
                    }
                }
            }
            $names = [];
            foreach (str_split($flags) as $f) {
                $names[] = self::FLAG[$f];
            }
            foreach ($ids as $menu) {
                if (isset($menus[$menu])) {
                    $out[$menu] = array_values(array_unique(array_merge($out[$menu] ?? [], $names)));
                }
            }
        }

        return $out;
    }

    private function top(int $menu, array $menus): string
    {
        while (($menus[$menu]['parent'] ?? 0) > 0 && isset($menus[$menus[$menu]['parent']])) {
            $menu = $menus[$menu]['parent'];
        }

        return $menus[$menu]['name'] ?? '?';
    }

    private function flagString(array $c): string
    {
        $s = '';
        foreach (self::FLAG as $k => $f) {
            $s .= ! empty($c[$f]) ? $k : '';
        }

        return $s;
    }
}
