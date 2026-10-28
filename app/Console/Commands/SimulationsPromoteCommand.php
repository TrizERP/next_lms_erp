<?php

namespace App\Console\Commands;

use App\Services\lms\Simulation\SimulationException;
use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Makes a saved version of a simulation the one that is played (and the card say what it says). Ported from hp_erp's `simulations:promote`: it is how
 * an earlier version is brought back, since the importer only ever moves forward.
 *
 *   php artisan lms:simulations-promote 3 1             (dry run: says what would change)
 *   php artisan lms:simulations-promote 3 1 --apply
 *
 * The version goes through the checks of a save again, so one the rules now refuse, or that names a file since removed, is not switched on.
 */
class SimulationsPromoteCommand extends Command
{
    protected $signature = 'lms:simulations-promote
        {id : The simulation (its number)}
        {version : The saved version to play}
        {--user= : The user id recorded as the one who switched it}
        {--apply : Write. Without it nothing is written}';

    protected $description = 'Makes a saved version of a simulation the one that is played (dry run unless --apply).';

    public function handle(SimulationStore $store): int
    {
        if (! ctype_digit((string) $this->argument('id')) || ! ctype_digit((string) $this->argument('version')) || (int) $this->argument('version') < 1) {
            $this->error('The simulation and the version are numbers: lms:simulations-promote <id> <version>.');

            return self::INVALID;
        }
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));
        $sim = DB::table('lms_simulation')->where('id', (int) $this->argument('id'))->whereNull('deleted_at')->first();
        if ($sim === null || ! in_array((int) $sim->sub_institute_id, $platform, true)) {
            $this->error("There is no simulation {$this->argument('id')} owned by a platform tenant.");

            return self::FAILURE;
        }
        $version = (int) $this->argument('version');
        $before = (int) $sim->current_version;
        if ($before === $version) {
            $this->info("\"{$sim->title}\" (id {$sim->id}) already plays version {$version}; nothing to change.");

            return self::SUCCESS;
        }
        if (! $this->option('apply')) {
            $this->line("DRY RUN: \"{$sim->title}\" (id {$sim->id}) plays version {$before}; it would play version {$version}. Add --apply to switch.");
            if ($store->specJson($sim, $version) === null) {
                $this->error("\"{$sim->slug}\" has no version {$version}.");

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        try {
            $sim = $store->promote($sim, $this->option('user') !== null ? (int) $this->option('user') : null, $version);
        } catch (SimulationException $e) {
            $this->error($e->getMessage());
            foreach ($e->problems as $problem) {
                $this->line(' - ' . $problem);
            }

            return self::FAILURE;
        }
        $this->info("\"{$sim->title}\" (id {$sim->id}) now plays version {$sim->current_version} (it was {$before}).");

        return self::SUCCESS;
    }
}
