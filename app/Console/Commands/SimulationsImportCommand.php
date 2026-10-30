<?php

namespace App\Console\Commands;

use App\Services\lms\Simulation\SimulationException;
use App\Services\lms\Simulation\SimulationPackageImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports Simulation Learning packages (`k12.sim-package/1` files from database/simulation-packages/) into the platform tenant's gallery.
 *
 *   php artisan lms:simulations-import database/simulation-packages                     (dry run: checks everything, writes nothing)
 *   php artisan lms:simulations-import database/simulation-packages/eye-lab.package.json
 *   php artisan lms:simulations-import database/simulation-packages --apply             (writes)
 *   php artisan lms:simulations-import database/simulation-packages --apply --tenant=1 --user=123
 *
 * DRY RUN BY DEFAULT: without --apply nothing is written, and the report says what would happen. Every package goes through the store's own
 * validation, its spec_sha256 must match its spec_json, and every file the spec names must already be registered and on the disk (run
 * lms:simulations-assets-copy first). Idempotent on (tenant, slug, spec_sha256): the same hash is a no-op, a new hash is a new version. Exit code
 * 1 when any package is refused.
 */
class SimulationsImportCommand extends Command
{
    protected $signature = 'lms:simulations-import
        {path : A package file, or a folder of *.package.json files}
        {--tenant= : The owning tenant (default: the first platform tenant; it must be one of lms_content.platform_sub_institute_ids)}
        {--user= : The user id recorded as the author}
        {--apply : Write. Without it nothing is written}';

    protected $description = 'Imports Simulation Learning package files into the platform tenant gallery (dry run unless --apply).';

    public function handle(SimulationPackageImporter $importer): int
    {
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));
        $tenant = $this->option('tenant') !== null ? $this->option('tenant') : ($platform[0] ?? 1);
        if (! ctype_digit((string) $tenant) || ! in_array((int) $tenant, $platform, true)) {
            $this->error('--tenant must be a platform tenant (' . implode(', ', $platform) . '): only the platform tenant owns simulations.');

            return self::INVALID;
        }
        $tenant = (int) $tenant;
        if ($this->option('user') !== null && ! ctype_digit((string) $this->option('user'))) {
            $this->error('--user must be a user id (digits).');

            return self::INVALID;
        }
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        $apply = (bool) $this->option('apply');

        $files = $this->files((string) $this->argument('path'));
        if ($files === []) {
            $this->error('No package files found at: ' . $this->argument('path'));

            return self::INVALID;
        }

        $before = $this->counts($tenant);
        $this->line(($apply ? 'APPLY' : 'DRY RUN (nothing is written; add --apply to write)') . " - tenant {$tenant}, " . count($files) . ' package file(s)');
        $failed = 0;
        $made = ['create' => 0, 'version' => 0, 'noop' => 0];

        foreach ($files as $file) {
            $label = basename($file);
            try {
                $package = $importer->load($file);
                $plan = $importer->plan($package, $tenant);
                if ($plan['problems'] !== []) {
                    throw new SimulationException($plan['problems']);
                }
                if ($apply) {
                    $done = $importer->apply($package, $tenant, $user, $label);
                    $this->info("  {$label}: {$done['action']} - {$done['note']}");
                    $made[$done['action']]++;
                } else {
                    $this->line("  {$label}: would {$plan['action']} - {$plan['note']}");
                    $made[$plan['action']]++;
                }
            } catch (SimulationException $e) {
                $failed++;
                $this->error("  {$label}: REFUSED");
                foreach ($e->problems as $problem) {
                    $this->line('    - ' . $problem);
                }
            }
        }

        $after = $apply ? $this->counts($tenant) : [
            'simulations' => $before['simulations'] + $made['create'],
            'versions' => $before['versions'] + $made['create'] + $made['version'],
        ];
        $this->line('Row counts (tenant ' . $tenant . ')           before -> ' . ($apply ? 'after' : 'after, if applied'));
        $this->line(sprintf('  lms_simulation           %6d -> %d', $before['simulations'], $after['simulations']));
        $this->line(sprintf('  lms_simulation_version   %6d -> %d', $before['versions'], $after['versions']));
        $this->line("Summary: {$made['create']} to create, {$made['version']} new version(s), {$made['noop']} unchanged, {$failed} refused.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    private function files(string $path): array
    {
        foreach ([$path, base_path($path)] as $candidate) {
            if (is_file($candidate)) {
                return [$candidate];
            }
            if (is_dir($candidate)) {
                $found = glob(rtrim($candidate, '/\\') . DIRECTORY_SEPARATOR . '*.package.json') ?: [];
                sort($found);

                return $found;
            }
        }

        return [];
    }

    /** @return array{simulations: int, versions: int} */
    private function counts(int $tenant): array
    {
        $ids = DB::table('lms_simulation')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->pluck('id');

        return [
            'simulations' => $ids->count(),
            'versions' => $ids->isEmpty() ? 0 : DB::table('lms_simulation_version')->whereIn('simulation_id', $ids)->count(),
        ];
    }
}
