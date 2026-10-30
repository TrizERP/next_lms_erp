<?php

namespace App\Console\Commands;

use App\Services\lms\Simulation\SimulationException;
use App\Services\lms\Simulation\SimulationMapping;
use Illuminate\Console\Command;

/**
 * Loads the reviewed, names-keyed mapping of simulations to concepts (database/simulation-packages/mapping.json) and creates the links, in `review`.
 *
 *   php artisan lms:simulations-mapping-import database/simulation-packages/mapping.json            (dry run)
 *   php artisan lms:simulations-mapping-import database/simulation-packages/mapping.json --apply
 *
 * DRY RUN BY DEFAULT. Standard, subject, chapter, topic and concept are resolved by NAME inside the platform tenant, as
 * PrayogshalaStandard9ScienceSeeder resolves its chapters. A name tuple must match EXACTLY ONE concept: none, or more than one, is a refusal that
 * lists the tuple, and the first match is never picked. `topic` may be null (a concept with no topic). If anything is refused, NOTHING is written,
 * with or without --apply, and the exit code is 1. A link that already exists is left exactly as it is, so running it again changes nothing. Links
 * are created in `review`: a teacher publishes them (the link editor, or the sign-off sheet from lms:simulations-mapping-sheet).
 */
class SimulationsMappingImportCommand extends Command
{
    protected $signature = 'lms:simulations-mapping-import
        {file : The mapping JSON (database/simulation-packages/mapping.json)}
        {--tenant= : The tenant that owns the simulations and concepts (default: the first platform tenant)}
        {--user= : The user id recorded as the creator of the links}
        {--apply : Write. Without it nothing is written}';

    protected $description = 'Creates simulation-to-concept links (in review) from the names-keyed mapping file; refuses what it cannot resolve to exactly one concept.';

    public function handle(SimulationMapping $mapping): int
    {
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));
        $tenant = $this->option('tenant') !== null ? $this->option('tenant') : ($platform[0] ?? 1);
        if (! ctype_digit((string) $tenant) || ! in_array((int) $tenant, $platform, true)) {
            $this->error('--tenant must be a platform tenant (' . implode(', ', $platform) . ').');

            return self::INVALID;
        }
        if ($this->option('user') !== null && ! ctype_digit((string) $this->option('user'))) {
            $this->error('--user must be a user id (digits).');

            return self::INVALID;
        }
        $apply = (bool) $this->option('apply');

        $file = (string) $this->argument('file');
        if (! is_file($file) && is_file(base_path($file))) {
            $file = base_path($file);
        }
        try {
            $plan = $mapping->plan($mapping->load($file), (int) $tenant);
        } catch (SimulationException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        $this->line(($apply ? 'APPLY' : 'DRY RUN (nothing is written; add --apply to write)') . ' - tenant ' . (int) $tenant . ', ' . count($plan['resolved']) . ' link(s) resolved, ' . count($plan['refusals']) . ' refused');
        if ($plan['refusals'] !== []) {
            $this->error('REFUSED - nothing was written. These could not be resolved to exactly one concept:');
            foreach ($plan['refusals'] as $refusal) {
                $this->line('  - ' . $refusal);
            }

            return self::FAILURE;
        }

        $new = count(array_filter($plan['resolved'], fn ($r) => ! $r['exists']));
        foreach ($plan['resolved'] as $r) {
            $this->line(sprintf('  %-16s -> concept #%d  %s', $r['simulation']->slug, $r['concept']->id, $r['exists'] ? '(already linked, left as it is)' : '(new, in review)'));
        }
        if ($apply) {
            $done = $mapping->apply($plan['resolved'], $this->option('user') !== null ? (int) $this->option('user') : null);
            $this->info("Created {$done['created']} link(s) in review; {$done['existing']} already existed and were left alone.");
        } else {
            $this->line("Would create {$new} link(s) in review; " . (count($plan['resolved']) - $new) . ' already exist.');
        }

        return self::SUCCESS;
    }
}
