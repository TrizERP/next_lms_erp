<?php

namespace App\Services\lms\Simulation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reads a `k12.sim-package/1` file (database/simulation-packages/<slug>.package.json) and puts the simulation it carries into the platform
 * tenant's gallery through SimulationStore, so a package is held to exactly the rules the authoring screen is.
 *
 * THE SPEC IS TEXT, NOT AN OBJECT. The package carries `spec_json`, the canonical specification text exactly as hp_erp's store keeps it, and
 * `spec_sha256`, the sha256 of that text. Three checks stand between a package and the database, each a refusal:
 *   1. sha256(spec_json) must equal spec_sha256 (a package edited or damaged in transit is not imported);
 *   2. the text must pass the store's own validation, and the store's canonical form of it must equal spec_json byte for byte (the canonical form
 *      is idempotent by design, so a genuine package passes and a re-laid-out one does not);
 *   3. every file the spec names must be registered for the owner (`lms_simulation_asset`) AND present on the disk.
 * The version number is the store's next integer, never copied from the source (hp_erp's dev and live number the same spec differently).
 *
 * IDEMPOTENT on (sub_institute_id, slug, spec_sha256): the same hash is a no-op (current, or kept as an older version); a new hash for a slug that
 * exists is a new version; a slug that does not exist is created. The importer never deletes anything.
 */
final class SimulationPackageImporter
{
    public const FORMAT = 'k12.sim-package/1';

    public function __construct(private readonly SimulationStore $store)
    {
    }

    /**
     * Reads and checks a package file (checks 1 and 2 above, and the shape of the manifest). Touches no database.
     *
     * @return array<string, mixed>  the package, with `parsed` (the store's parse of spec_json) added
     *
     * @throws SimulationException
     */
    public function load(string $file): array
    {
        if (! is_file($file)) {
            throw new SimulationException(["No such package file: {$file}"]);
        }
        try {
            $package = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SimulationException([basename($file) . ' is not valid JSON: ' . $e->getMessage()]);
        }
        if (! is_array($package) || array_is_list($package)) {
            throw new SimulationException([basename($file) . ' must be a JSON object.']);
        }

        $problems = [];
        if (($package['format'] ?? null) !== self::FORMAT) {
            $problems[] = '"format" must be "' . self::FORMAT . '".';
        }
        $slug = $package['slug'] ?? null;
        if (! is_string($slug) || $slug === '') {
            $problems[] = '"slug" is required.';
        }
        $json = $package['spec_json'] ?? null;
        $hash = $package['spec_sha256'] ?? null;
        if (! is_string($json) || $json === '') {
            $problems[] = '"spec_json" is required: the canonical specification text.';
        }
        if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            $problems[] = '"spec_sha256" must be 64 lowercase hexadecimal characters.';
        }
        if ($problems !== []) {
            throw new SimulationException($problems);
        }

        // 1. the hash of the text
        if (hash('sha256', $json) !== $hash) {
            throw new SimulationException(["The package \"{$slug}\" is refused: sha256(spec_json) is " . hash('sha256', $json) . ", but spec_sha256 says {$hash}. The file was changed or damaged after it was written."]);
        }
        // 2. the store's own validation, and its canonical text must be this text
        $parsed = $this->store->parse($json);
        if ($parsed['json'] !== $json) {
            throw new SimulationException(["The package \"{$slug}\" is refused: its spec_json is not in the store's canonical form (the store would save different text, and so a different sha256). Export it again from the source."]);
        }
        if ($parsed['facts']['slug'] !== $slug) {
            throw new SimulationException(["The package slug is \"{$slug}\" but the specification's id is \"{$parsed['facts']['slug']}\"."]);
        }

        // the manifest: it carries the licence and credit each file is registered with, so it has to list every file the spec names
        $manifest = $package['assets'] ?? [];
        if (! is_array($manifest)) {
            throw new SimulationException(['"assets" must be a list.']);
        }
        $listed = [];
        foreach ($manifest as $i => $asset) {
            if (! is_array($asset) || ! is_string($asset['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $asset['sha256'])) {
                $problems[] = "\"assets.{$i}.sha256\" must be 64 lowercase hexadecimal characters.";

                continue;
            }
            $listed[$asset['sha256']] = true;
        }
        foreach ($parsed['facts']['assets'] as $named) {
            if (! isset($listed[$named])) {
                $problems[] = "The specification names the file {$named}, which the package's \"assets\" manifest does not list.";
            }
        }
        if ($problems !== []) {
            throw new SimulationException($problems);
        }

        return $package + ['parsed' => $parsed];
    }

    /**
     * What importing this package into `$tenant`'s gallery would do, and what stands in the way. Reads the database and the disk; writes nothing.
     *
     * @param  array<string, mixed>  $package  from load()
     * @return array{slug: string, action: 'create'|'version'|'noop', note: string, problems: list<string>}
     */
    public function plan(array $package, int $tenant): array
    {
        $slug = (string) $package['slug'];
        $sim = $this->existing($tenant, $slug);
        $hash = (string) $package['spec_sha256'];
        $action = 'create';
        $note = 'a new simulation, version 1';

        if ($sim !== null) {
            $versions = DB::table('lms_simulation_version')->where('simulation_id', $sim->id)->where('sha256', $hash)->orderByDesc('version')->pluck('version')->all();
            if ($versions !== []) {
                $action = 'noop';
                $note = in_array((int) $sim->current_version, array_map('intval', $versions), true)
                    ? "already imported: version {$sim->current_version} is this spec (id {$sim->id})"
                    : 'this spec is already stored as version ' . $versions[0] . " of id {$sim->id}, but version {$sim->current_version} is the one played (php artisan lms:simulations-promote {$sim->id} {$versions[0]} --apply brings it back)";
            } else {
                $next = (int) DB::table('lms_simulation_version')->where('simulation_id', $sim->id)->max('version') + 1;
                $action = 'version';
                $note = "a changed spec for id {$sim->id}: it becomes version {$next}";
            }
        }

        $problems = $this->store->assetProblems($tenant, $package['parsed']['spec']);
        if ($problems === []) {
            foreach ($this->store->assetRows((object) ['sub_institute_id' => $tenant], $package['parsed']['facts']['assets']) as $row) {
                if (! $this->onDisk((string) $row->storage_path)) {
                    $problems[] = "The file {$row->sha256} is registered, but is not on the disk ({$row->storage_path}). Run lms:simulations-assets-copy --apply first.";
                }
            }
        }

        return ['slug' => $slug, 'action' => $action, 'note' => $note, 'problems' => $problems];
    }

    /**
     * Imports one package. Refuses (SimulationException) on anything plan() lists; otherwise creates, adds a version or does nothing.
     *
     * @param  array<string, mixed>  $package  from load()
     * @return array{action: 'create'|'version'|'noop', note: string, simulation_id: int}
     *
     * @throws SimulationException
     */
    public function apply(array $package, int $tenant, ?int $userId, string $label = 'a package'): array
    {
        $plan = $this->plan($package, $tenant);
        if ($plan['problems'] !== []) {
            throw new SimulationException($plan['problems']);
        }
        $json = (string) $package['spec_json'];
        $note = 'imported from ' . $label;
        $sim = $this->existing($tenant, (string) $package['slug']);

        if ($plan['action'] === 'noop') {
            return ['action' => 'noop', 'note' => $plan['note'], 'simulation_id' => (int) $sim->id];
        }
        if ($plan['action'] === 'version') {
            $sim = $this->store->addVersion($sim, $userId, $json, $note);

            return ['action' => 'version', 'note' => "now version {$sim->current_version} of id {$sim->id}", 'simulation_id' => (int) $sim->id];
        }

        $cover = is_string($package['cover'] ?? null) ? trim($package['cover']) : '';
        $options = array_filter([
            'status' => 'published',
            'category' => $package['category'] ?? null,
            'tags' => $package['tags'] ?? null,
            'difficulty' => $package['difficulty'] ?? null,
            'cover_url' => $cover === '' ? null : (str_starts_with($cover, '/') || str_starts_with($cover, 'https://') ? $cover : '/' . $cover),
            'note' => $note,
        ], fn ($v) => $v !== null && $v !== '');
        $sim = $this->store->create($tenant, $userId, $json, $options);

        return ['action' => 'create', 'note' => "created id {$sim->id}, version {$sim->current_version}", 'simulation_id' => (int) $sim->id];
    }

    /** The live simulation of this tenant with this slug, if any. */
    public function existing(int $tenant, string $slug): ?object
    {
        return DB::table('lms_simulation')->where('sub_institute_id', $tenant)->where('slug', $slug)->whereNull('deleted_at')->first();
    }

    private function onDisk(string $path): bool
    {
        try {
            return Storage::disk(SimulationStore::ASSET_DISK)->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }
}
