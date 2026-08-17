<?php

namespace App\Console\Commands\PAL;

use App\Services\PAL\H5P\H5PContentRepository;
use App\Services\PAL\H5P\H5PModelRegistry;
use App\Services\PAL\H5P\H5PRegistrySeeder;
use Illuminate\Console\Command;

/**
 * Re-publish the H5P Model (config/pal_h5p.php) into `pal_vocabulary`.
 *
 * The registry is the runtime source of truth for the 21 H5P types, the 12
 * pedagogies, the CASEL / NGSS / NCDG / Music / Sports / Finance frameworks,
 * the §9 coverage matrix, the xAPI verb map and the engagement weights. The
 * install migration seeds it once; this command re-publishes it whenever the
 * seed file changes, so no new migration is needed to correct a mapping.
 *
 * Additive and idempotent: missing codes are inserted, existing system rows
 * have their metadata refreshed, edited labels are preserved, and a tenant's
 * own rows are never touched.
 */
class H5PRegistrySyncCommand extends Command
{
    protected $signature = 'pal:h5p-registry-sync
        {--prune : remove the system rows this seeder owns instead of writing them}
        {--verify : after syncing, report what the registry resolves to}';

    protected $description = 'PAL V4: publish the H5P Model registry (types, pedagogies, frameworks, coverage matrix, xAPI verbs) into pal_vocabulary';

    public function handle(H5PRegistrySeeder $seeder, H5PModelRegistry $registry, H5PContentRepository $repository): int
    {
        if ($this->option('prune')) {
            $removed = $seeder->prune();
            $this->warn("Removed {$removed} system registry rows.");

            return self::SUCCESS;
        }

        $report = $seeder->sync();

        $rows = [];
        foreach ($report as $domain => $counts) {
            $rows[] = [$domain, $counts['inserted'], $counts['updated']];
        }
        $this->table(['Domain', 'Inserted', 'Updated'], $rows);

        if (! $this->option('verify')) {
            $this->info('H5P Model registry published.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<comment>Resolved registry</comment>');
        $this->line('  source: ' . $registry->source());
        $this->line('  H5P types: ' . count($registry->types()) . ' (' . count($registry->nativeTypes()) . ' natively implemented)');
        $this->line('  pedagogies: ' . count($registry->pedagogies()));
        $this->line('  xAPI verbs: ' . count($registry->domain('xapi_verbs')));

        $unmapped = [];
        foreach ($registry->types() as $code => $type) {
            $pedagogies = $registry->pedagogiesForH5pType($code);
            if ($pedagogies['primary'] === [] && $pedagogies['secondary'] === []) {
                $unmapped[] = $code;
            }
        }

        if ($unmapped !== []) {
            $this->newLine();
            $this->warn('H5P types no pedagogy is authored against (nodes of these types cannot derive a pedagogy):');
            foreach ($unmapped as $code) {
                $this->line("  - {$code}");
            }
        }

        $this->newLine();
        $this->line('<comment>Native type → source table</comment>');
        foreach ($registry->nativeTypes() as $code => $type) {
            $implementation = $type['metadata']['implementation'] ?? [];
            $this->line(sprintf('  %-20s %s', $code, $implementation['source_table'] ?? '—'));
        }

        $this->newLine();
        $inventory = $repository->inventory([]);
        $missing = array_keys(array_filter($inventory, fn ($row) => ! $row['available']));
        if ($missing !== []) {
            $this->warn('Native types whose table is absent on this database: ' . implode(', ', $missing));
        } else {
            $this->info('Every natively implemented type has its table present.');
        }

        return self::SUCCESS;
    }
}
