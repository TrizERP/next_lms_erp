<?php

namespace App\Console\Commands;

use Database\Seeders\PalPedagogyEngineSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Installs / refreshes the PAL V4 Pedagogy Engine rule set.
 *
 * A dedicated command rather than `db:seed` because App\Console\Kernel::bootstrap()
 * hard-blocks any artisan invocation whose argv matches db:seed|schema|fresh|refresh.
 * The seeder class stays the single source of truth.
 *
 *   php artisan pal:install-pedagogy-engine
 *   php artisan pal:install-pedagogy-engine --report
 */
class InstallPalPedagogyEngine extends Command
{
    protected $signature = 'pal:install-pedagogy-engine
        {--report : Only print what is installed, write nothing}';

    protected $description = 'Install or refresh the PAL V4 Pedagogy Engine rule set (tiers, engagement score, trigger map)';

    public function handle()
    {
        foreach (['pal_pedagogy_engine_sections', 'pal_pedagogy_engine_rules'] as $table) {
            if (count(DB::select("SHOW TABLES LIKE '" . $table . "'")) === 0) {
                $this->error("Table `{$table}` is missing. Run `php artisan migrate` first.");

                return 1;
            }
        }

        if (! $this->option('report')) {
            $this->info('Installing PAL Pedagogy Engine rule set...');
            (new PalPedagogyEngineSeeder())->setContainer($this->laravel)->run();
            $this->info('Done.');
        }

        $sections = DB::table('pal_pedagogy_engine_sections')->orderBy('sort_order')->get();

        $rows = [];
        foreach ($sections as $section) {
            $rows[] = [
                $section->section_key,
                $section->name,
                $section->section_type,
                DB::table('pal_pedagogy_engine_rules')->where('section_key', $section->section_key)->count(),
                $section->ui_visible ? 'yes' : 'no',
                $section->implementation_status,
            ];
        }

        $this->table(['key', 'section', 'type', 'rows', 'in UI', 'status'], $rows);
        $this->line('Sections: ' . count($rows) . '  |  Rows: ' . DB::table('pal_pedagogy_engine_rules')->count());

        return 0;
    }
}
