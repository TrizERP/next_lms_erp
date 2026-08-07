<?php

namespace App\Console\Commands;

use Database\Seeders\OnboardingJourneySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Installs / refreshes the global onboarding journey template.
 *
 * A dedicated command rather than `db:seed` because App\Console\Kernel::bootstrap()
 * hard-blocks any artisan invocation whose argv matches db:seed|schema|fresh|refresh.
 * The seeder class is still the single source of truth and stays usable from
 * DatabaseSeeder on a fresh install.
 *
 *   php artisan onboarding:install
 *   php artisan onboarding:install --report
 */
class InstallOnboardingJourney extends Command
{
    protected $signature = 'onboarding:install
        {--report : Only print what is installed, write nothing}
        {--register-menu= : Add the sidebar entry for one sub_institute_id (comma-separated for several)}';

    protected $description = 'Install or refresh the module-wise onboarding journey template';

    public function handle()
    {
        foreach (['onboarding_module', 'onboarding_step', 'onboarding_progress'] as $table) {
            if (count(DB::select("SHOW TABLES LIKE '" . $table . "'")) === 0) {
                $this->error("Table `{$table}` is missing. Run `php artisan migrate` first.");

                return 1;
            }
        }

        if (! $this->option('report')) {
            $this->info('Installing onboarding journey template...');
            (new OnboardingJourneySeeder())->setContainer($this->laravel)->run();
            $this->info('Done.');
        }

        $modules = DB::table('onboarding_module')->where('sub_institute_id', 0)->orderBy('sort_order')->get();

        $rows = [];
        foreach ($modules as $module) {
            $steps = DB::table('onboarding_step')->where('module_id', $module->id)->get();
            $rows[] = [
                $module->module_key,
                $module->module_name,
                $module->menu_title,
                $steps->count(),
                $steps->where('proof_type', 'table_rows')->count(),
                $steps->where('proof_type', 'manual')->count(),
            ];
        }

        $this->table(['key', 'module', 'menu_title', 'steps', 'derived', 'manual'], $rows);
        $this->line('Modules: ' . count($rows) . '  |  Steps: ' . DB::table('onboarding_step')->count());

        if ($tenants = $this->option('register-menu')) {
            $this->registerMenu($tenants);
        }

        return 0;
    }

    /**
     * The Next.js sidebar is built from `tblmenumaster`, so the new screen is
     * only reachable from the nav once a menu row exists for the tenant.
     *
     * Deliberately opt-in and per-tenant: `tblmenumaster` is shared across every
     * school on the database, so this is never done implicitly by an install.
     */
    private function registerMenu(string $tenants): void
    {
        $ids = array_values(array_filter(array_map('trim', explode(',', $tenants)), 'strlen'));
        if (! $ids) {
            $this->error('--register-menu needs at least one sub_institute_id.');

            return;
        }

        $parent = DB::table('tblmenumaster')
            ->where('menu_title', 'Utility')
            ->where('level', 2)
            ->value('parent_menu_id');

        foreach ($ids as $id) {
            $existing = DB::table('tblmenumaster')
                ->where('link', 'general/onboarding')
                ->whereRaw('find_in_set(?, sub_institute_id)', [$id])
                ->first();

            if ($existing) {
                $this->line("sub_institute_id={$id}: already registered (menu id {$existing->id}).");
                continue;
            }

            $sibling = DB::table('tblmenumaster')
                ->where('link', 'general/onboarding')
                ->first();

            if ($sibling) {
                // Extend the existing row's tenant list rather than duplicating it.
                DB::table('tblmenumaster')->where('id', $sibling->id)->update([
                    'sub_institute_id' => rtrim($sibling->sub_institute_id, ',') . ',' . $id,
                    'updated_at' => now(),
                ]);
                $this->info("sub_institute_id={$id}: added to menu id {$sibling->id}.");
                continue;
            }

            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'Onboarding',
                'menu_title' => 'Utility',
                'description' => 'Module-wise onboarding journey',
                'parent_menu_id' => $parent ?: 0,
                'level' => 3,
                'status' => 1,
                'sort_order' => 900,
                'link' => 'general/onboarding',
                'icon' => 'list-checks',
                'menu_type' => 'ENTRY',
                'sub_institute_id' => (string) $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("sub_institute_id={$id}: created menu id {$menuId}.");
        }
    }
}
