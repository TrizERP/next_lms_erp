<?php

namespace App\Console\Commands;

use App\Services\lms\Simulation\SimulationAssetService;
use App\Services\lms\Simulation\SimulationException;
use App\Services\lms\Simulation\SimulationPackageImporter;
use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Copies the asset objects (3D models, images, HDR skies) a package lists from hp_erp's prefix on the shared bucket to K12's own prefix, checks them,
 * and registers them in `lms_simulation_asset`.
 *
 *   php artisan lms:simulations-assets-copy                                   (dry run: checks the source and the target, writes nothing)
 *   php artisan lms:simulations-assets-copy --apply                           (copies and registers)
 *   php artisan lms:simulations-assets-copy --from-prefix=sim_assets/platform --packages=database/simulation-packages --apply
 *
 * WHY A COPY. The two applications share one bucket, and hp_erp deletes objects it believes are its own when an upload fails half way. A key both
 * apps use is therefore a delete hazard in both directions, so K12 serves its own copy at `k12_sim_assets/<sha256>.<ext>`.
 *
 * WHAT IT DOES TO THE BUCKET. It reads under --from-prefix and WRITES ONLY under k12_sim_assets/. It never deletes from the source prefix. The one
 * delete it can make is of a copy IT JUST MADE whose bytes did not hash to the manifest's sha256. After each copy it reads the copied object back,
 * hashes it and compares the sha256 with the package manifest; a mismatch is a failure (exit 1). An object already at the target is verified, never
 * overwritten. Dry run by default.
 */
class SimulationsAssetsCopyCommand extends Command
{
    private const EXTENSIONS = ['glb', 'png', 'jpg', 'webp', 'hdr'];

    protected $signature = 'lms:simulations-assets-copy
        {--from-prefix=sim_assets/platform : Where hp_erp keeps the objects (read only)}
        {--packages=database/simulation-packages : The folder (or one file) of packages whose manifests list the assets}
        {--tenant= : The tenant that owns the registry rows (default: the first platform tenant)}
        {--user= : The user id recorded as the one who registered them}
        {--apply : Copy and register. Without it nothing is written}';

    protected $description = 'Copies simulation asset objects to the K12 prefix on the shared bucket, verifies their sha256, and registers them (dry run unless --apply).';

    public function handle(SimulationPackageImporter $importer, SimulationAssetService $assets, SimulationStore $store): int
    {
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));
        $tenant = $this->option('tenant') !== null ? $this->option('tenant') : ($platform[0] ?? 1);
        if (! ctype_digit((string) $tenant) || ! in_array((int) $tenant, $platform, true)) {
            $this->error('--tenant must be a platform tenant (' . implode(', ', $platform) . ').');

            return self::INVALID;
        }
        $tenant = (int) $tenant;
        $user = $this->option('user') !== null && ctype_digit((string) $this->option('user')) ? (int) $this->option('user') : null;
        $from = trim((string) $this->option('from-prefix'), '/');
        if ($from === '' || $from === SimulationStore::ASSET_PREFIX || str_starts_with($from . '/', SimulationStore::ASSET_PREFIX . '/')) {
            $this->error('--from-prefix must be the source prefix (hp_erp\'s), not K12\'s own ' . SimulationStore::ASSET_PREFIX . '/.');

            return self::INVALID;
        }
        $apply = (bool) $this->option('apply');

        $files = $this->packageFiles((string) $this->option('packages'));
        if ($files === []) {
            $this->error('No package files found at: ' . $this->option('packages'));

            return self::INVALID;
        }

        // the manifest entries of every package, once per sha256
        $entries = [];
        $failures = [];
        foreach ($files as $file) {
            try {
                $package = $importer->load($file);
            } catch (SimulationException $e) {
                $failures[] = basename($file) . ': ' . $e->getMessage();

                continue;
            }
            foreach ((array) ($package['assets'] ?? []) as $asset) {
                $entries[$asset['sha256']] ??= $asset + ['_package' => basename($file)];
            }
        }

        $disk = Storage::disk(SimulationStore::ASSET_DISK);
        $this->line(($apply ? 'APPLY' : 'DRY RUN (nothing is written; add --apply to write)') . ' - ' . count($entries) . " asset(s), {$from}/ -> " . SimulationStore::ASSET_PREFIX . '/');
        $copied = 0;
        $verified = 0;
        $registered = 0;

        foreach ($entries as $sha => $asset) {
            $problem = $this->manifestProblem($asset, $from);
            if ($problem !== null) {
                $failures[] = "{$sha}: {$problem}";
                $this->error("  {$sha}: REFUSED - {$problem}");

                continue;
            }
            $name = basename((string) $asset['source_key']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $source = $from . '/' . $name;
            $target = $store->assetPath($sha, $ext);

            try {
                $outcome = $this->copyOne($disk, $source, $target, $sha, (int) $asset['bytes'], $apply);
            } catch (\Throwable $e) {
                $failures[] = "{$sha}: " . $e->getMessage();
                $this->error("  {$sha}: FAILED - " . $e->getMessage());

                continue;
            }
            // a dry run reports 'would copy': it counts as "to copy", or the summary would say 0 above a plan of 63
            $copied += in_array($outcome, ['copied', 'would copy'], true) ? 1 : 0;
            $verified += $outcome === 'verified' ? 1 : 0;
            $this->line("  {$sha}: {$outcome}" . ($outcome === 'would copy' ? " ({$source} -> {$target})" : ''));

            $existing = $assets->find($tenant, $sha);
            if ($apply) {
                $result = $assets->record($existing, $tenant, $sha, ['kind' => $asset['kind'], 'mime' => $asset['mime'], 'bytes' => (int) $asset['bytes']], $target, [
                    'name' => (string) $asset['name'],
                    'licence' => (string) $asset['licence'],
                    'credit' => (string) $asset['credit'],
                    'source' => (string) ($asset['source_url'] ?? ''),
                ], $user);
                $registered += $result['outcome'] === 'existing' ? 0 : 1;
                $this->line("    registry: {$result['outcome']} (asset {$result['id']})");
            } else {
                $wouldRegister = $existing === null || $existing->deleted_at !== null;
                $registered += $wouldRegister ? 1 : 0;
                $this->line('    registry: ' . ($wouldRegister ? 'would register' : 'already registered'));
            }
        }

        $this->line(sprintf('Summary: %d %s, %d already there and verified, %d %s, %d failed.', $copied, $apply ? 'copied' : 'to copy', $verified, $registered, $apply ? 'registered' : 'to register', count($failures)));
        foreach ($failures as $failure) {
            $this->line('  FAIL ' . $failure);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Makes sure `$target` holds the bytes whose sha256 is `$sha`. Returns 'copied', 'verified' (already there and right), or 'would copy' (dry run).
     *
     * @throws \RuntimeException  with the reason, when the source is missing, the target holds other bytes, or the copy does not hash to `$sha`
     */
    private function copyOne(Filesystem $disk, string $source, string $target, string $sha, int $bytes, bool $apply): string
    {
        if ($disk->exists($target)) {
            // never overwritten: it is checked, and a wrong one is for a person to look at
            if ($this->hashOf($disk, $target) !== $sha) {
                throw new \RuntimeException("{$target} exists but its bytes do not hash to {$sha}; it was left alone. Look at it by hand.");
            }

            return 'verified';
        }
        if (! $disk->exists($source)) {
            throw new \RuntimeException("the source object {$source} does not exist.");
        }
        if ($disk->size($source) !== $bytes) {
            throw new \RuntimeException("the source object {$source} is " . $disk->size($source) . " bytes but the manifest says {$bytes}.");
        }
        if (! $apply) {
            return 'would copy';
        }

        if (! $disk->copy($source, $target)) {
            throw new \RuntimeException("the disk did not copy {$source} to {$target}.");
        }
        try {
            $disk->setVisibility($target, 'private');
        } catch (\Throwable) {
            // a disk with no visibility (the fake one) has nothing to set; the bucket's own default is private
        }
        $actual = $this->hashOf($disk, $target);
        if ($actual !== $sha) {
            $disk->delete($target);   // the copy this run just made, and only that; the source is never touched

            throw new \RuntimeException("the copy at {$target} hashed to {$actual}, not the manifest's {$sha}; the copy was removed.");
        }

        return 'copied';
    }

    /** The sha256 of the object's bytes, read back as a stream. */
    private function hashOf(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new \RuntimeException("{$path} could not be read.");
        }
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    /** @param  array<string, mixed>  $asset */
    private function manifestProblem(array $asset, string $from): ?string
    {
        if (! in_array($asset['kind'] ?? null, SimulationStore::ASSET_KINDS, true)) {
            return 'kind must be one of: ' . implode(', ', SimulationStore::ASSET_KINDS) . '.';
        }
        foreach (['mime', 'name', 'licence', 'credit'] as $key) {
            if (! is_string($asset[$key] ?? null) || trim($asset[$key]) === '') {
                return "\"{$key}\" is required (a file without its licence and credit is never registered).";
            }
        }
        if (! is_int($asset['bytes'] ?? null) || $asset['bytes'] < 1) {
            return '"bytes" must be the size of the file.';
        }
        $key = $asset['source_key'] ?? null;
        $name = is_string($key) ? basename($key) : '';
        if ($name === '' || ! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true) || pathinfo($name, PATHINFO_FILENAME) !== $asset['sha256']) {
            return '"source_key" must end in <sha256>.<' . implode('|', self::EXTENSIONS) . '>.';
        }

        return null;
    }

    /** @return list<string> */
    private function packageFiles(string $path): array
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
}
