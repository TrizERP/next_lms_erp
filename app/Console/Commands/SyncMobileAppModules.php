<?php

namespace App\Console\Commands;

use App\Services\Access\MobileAppModules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each role its starting set of native app screens (config/mobile_app_modules.php 'defaults').
 * Additive only: it never removes a module an admin already granted. Dry run unless --apply.
 */
class SyncMobileAppModules extends Command
{
    protected $signature = 'mobile:sync-app-modules {tenant : sub_institute_id} {--role= : only this profile name} {--apply : write the rows}';

    protected $description = 'Seed default Mobile app modules per role (dry run unless --apply)';

    public function handle(): int
    {
        $tenant = (int) $this->argument('tenant');
        if ($this->option('apply') && ! Schema::hasTable('mobile_role_modules')) {
            $this->error('mobile_role_modules does not exist. Run: php artisan migrate --path=database/migrations/2026_11_26_160000_create_mobile_role_modules_table.php');

            return self::FAILURE;
        }

        $profiles = DB::table('tbluserprofilemaster')->where('sub_institute_id', $tenant)->where('status', 1)->get(['id', 'name']);
        $rows = [];
        foreach ($profiles as $p) {
            if ($this->option('role') && strtolower(trim($p->name)) !== strtolower(trim($this->option('role')))) {
                continue;
            }
            $defaults = MobileAppModules::defaultsFor($p->name);
            if (! $defaults) {
                continue;
            }
            $have = MobileAppModules::grantedKeys($tenant, (int) $p->id);
            $add = array_values(array_diff($defaults, $have));
            $rows[] = [$p->id, $p->name, count($have), count($add)];
            if ($this->option('apply') && $add) {
                MobileAppModules::replace($tenant, (int) $p->id, $p->name, array_merge($have, $add), null);
            }
        }

        $this->table(['profile id', 'role', 'already granted', $this->option('apply') ? 'added' : 'would add'], $rows);
        $this->info($this->option('apply') ? 'Applied.' : 'Dry run only. Nothing was written. Re-run with --apply to write.');

        return self::SUCCESS;
    }
}
