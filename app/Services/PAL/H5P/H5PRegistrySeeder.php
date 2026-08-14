<?php

namespace App\Services\PAL\H5P;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publishes config/pal_h5p.php into `pal_vocabulary`.
 *
 * Used by the `2026_08_14_140000_seed_pal_h5p_model_registry` migration on
 * install and by `php artisan pal:h5p-registry-sync` whenever the seed file
 * changes — the registry is the runtime source of truth, so it has to be
 * re-publishable without inventing a new migration each time.
 *
 * The sync is additive and idempotent:
 *   - a missing code is INSERTED;
 *   - an existing code has its `description`, `metadata` and `sort_order`
 *     refreshed, but NOT its `label` — a label an admin edited in the UI is
 *     theirs to keep;
 *   - a tenant's own rows (sub_institute_id > 0) are never touched.
 */
class H5PRegistrySeeder
{
    /** Domains this seeder owns outright and may remove on rollback. */
    public const OWNED_DOMAINS = [
        'pedagogy_tags',
        'pedagogy_selection_rules',
        'music_domains',
        'sports_domains',
        'finance_levels',
        'xapi_verbs',
        'engagement_signals',
    ];

    /** H5P type codes §8.1 defines that the registry did not already carry. */
    public const ADDED_H5P_TYPES = [
        'crossword', 'summary', 'audio_recorder',
        'arithmetic_quiz', 'find_the_hotspot', 'image_sequencing',
    ];

    /**
     * Write the whole H5P Model into the registry.
     *
     * @return array<string,array{inserted:int,updated:int}> per-domain counts
     */
    public function sync(): array
    {
        if (! Schema::hasTable('pal_vocabulary')) {
            $this->createRegistryTable();
        }

        $seed = config('pal_h5p', []);
        $report = [];

        // §8.1 — H5P content types, natively implemented ones sorted first.
        $report['h5p_types'] = $this->write(
            'h5p_types',
            $seed['h5p_types'] ?? [],
            function (array $def) {
                return [
                    'label' => $def['label'],
                    'description' => $def['description'] ?? null,
                    'metadata' => array_filter([
                        'pal_use_cases' => $def['pal_use_cases'] ?? [],
                        'bloom_from' => $def['bloom_from'] ?? null,
                        'bloom_to' => $def['bloom_to'] ?? null,
                        'xapi_events' => $def['xapi_events'] ?? [],
                        'fluency_trackable' => $def['fluency_trackable'] ?? 'no',
                        'engagement_weight' => $def['engagement_weight'] ?? 1.0,
                        'social_mode' => $def['social_mode'] ?? 'individual',
                        'gamification_potential' => $def['gamification_potential'] ?? 'low',
                        'retry_allowed' => $def['retry_allowed'] ?? true,
                        'offline_compatible' => $def['offline_compatible'] ?? false,
                        'mobile_optimised' => $def['mobile_optimised'] ?? true,
                        'implementation' => $def['implementation'] ?? ['status' => 'planned'],
                    ], fn ($value) => $value !== null && $value !== []),
                ];
            },
            $this->h5pTypeOrder($seed['h5p_types'] ?? [])
        );

        // §1.2 + §9 pedagogies, §1.3 selection rules, §2–§7 frameworks.
        $wholeMetadata = function (array $def) {
            $metadata = $def;
            unset($metadata['label'], $metadata['description']);

            return [
                'label' => $def['label'],
                'description' => $def['description'] ?? null,
                'metadata' => $metadata,
            ];
        };

        $report['pedagogy_tags'] = $this->write('pedagogy_tags', $seed['pedagogy_tags'] ?? [], $wholeMetadata);

        $report['pedagogy_selection_rules'] = $this->write(
            'pedagogy_selection_rules',
            $seed['pedagogy_selection_rules'] ?? [],
            fn (array $def) => [
                'label' => $def['label'],
                'description' => null,
                'metadata' => ['when' => $def['when'] ?? [], 'then' => $def['then'] ?? []],
            ],
            array_map(fn ($def) => $def['sort_order'] ?? 50, $seed['pedagogy_selection_rules'] ?? [])
        );

        foreach (['casel_domains', 'ngss_practices', 'ncdg_goals', 'music_domains', 'sports_domains', 'finance_levels'] as $domain) {
            $report[$domain] = $this->write($domain, $seed[$domain] ?? [], $wholeMetadata);
        }

        // Cross-cutting signals — label only.
        foreach (['gardner_intelligences', 'riasec_signals', 'hpc_lenses'] as $domain) {
            $report[$domain] = $this->write(
                $domain,
                $seed[$domain] ?? [],
                fn (array $def) => ['label' => $def['label'], 'description' => null, 'metadata' => []]
            );
        }

        // §8.2 verb map + engagement composition.
        $report['xapi_verbs'] = $this->write('xapi_verbs', $seed['xapi_verbs'] ?? [], function (array $def) {
            $metadata = $def;
            unset($metadata['label']);

            return ['label' => $def['label'], 'description' => $def['iri'] ?? null, 'metadata' => $metadata];
        });

        $report['engagement_signals'] = $this->write(
            'engagement_signals',
            $seed['engagement_signals'] ?? [],
            fn (array $def) => ['label' => $def['label'], 'description' => null, 'metadata' => ['weight' => $def['weight']]]
        );

        $report['legacy_aliases'] = $this->markLegacyAliases($seed['legacy_aliases'] ?? []);

        H5PModelRegistry::flush();

        return $report;
    }

    /**
     * Remove what this seeder is the author of. Domains that pre-existed
     * (h5p_types, casel_domains, …) keep their rows — dropping them would take
     * out vocabulary this seeder did not create.
     */
    public function prune(): int
    {
        if (! Schema::hasTable('pal_vocabulary')) {
            return 0;
        }

        $removed = DB::table('pal_vocabulary')
            ->whereIn('domain', self::OWNED_DOMAINS)
            ->where('is_system', 1)
            ->where('sub_institute_id', 0)
            ->delete();

        $removed += DB::table('pal_vocabulary')
            ->where('domain', 'h5p_types')
            ->whereIn('code', self::ADDED_H5P_TYPES)
            ->where('is_system', 1)
            ->where('sub_institute_id', 0)
            ->delete();

        $removed += DB::table('pal_vocabulary')
            ->where('domain', 'ncdg_goals')
            ->where('code', 'CM4')
            ->where('is_system', 1)
            ->where('sub_institute_id', 0)
            ->delete();

        H5PModelRegistry::flush();

        return $removed;
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * @param  array<string,array>   $definitions  code => definition
     * @param  callable(array):array $shape        definition → row fields
     * @param  array<string,int>     $order        code => sort_order
     * @return array{inserted:int,updated:int}
     */
    protected function write(string $domain, array $definitions, callable $shape, array $order = []): array
    {
        $now = now();
        $position = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($definitions as $code => $definition) {
            $position++;
            $fields = $shape((array) $definition);
            $metadata = $fields['metadata'] ?? [];

            $payload = [
                'description' => $fields['description'] ?? null,
                'metadata' => $metadata === []
                    ? null
                    : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sort_order' => $order[$code] ?? $position,
                'updated_at' => $now,
            ];

            $existingId = DB::table('pal_vocabulary')
                ->where('domain', $domain)
                ->where('code', $code)
                ->where('sub_institute_id', 0)
                ->value('id');

            if ($existingId !== null) {
                DB::table('pal_vocabulary')->where('id', $existingId)->update($payload);
                $updated++;
                continue;
            }

            DB::table('pal_vocabulary')->insert($payload + [
                'domain' => $domain,
                'code' => $code,
                'label' => $fields['label'],
                'status' => 1,
                'sub_institute_id' => 0,
                'scope' => 'global',
                'is_system' => 1,
                'created_at' => $now,
            ]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Stamp pre-PAL-V4 spellings as aliases so the canonical catalog stops
     * showing two rows for one practice, without deleting anything already
     * tagged with the old code.
     *
     * @param  array<string,array<string,string|null>>  $aliases
     * @return array{inserted:int,updated:int}
     */
    protected function markLegacyAliases(array $aliases): array
    {
        $updated = 0;

        foreach ($aliases as $domain => $map) {
            foreach ($map as $legacy => $canonical) {
                $row = DB::table('pal_vocabulary')
                    ->where('domain', $domain)
                    ->where('code', $legacy)
                    ->where('sub_institute_id', 0)
                    ->first();

                if (! $row) {
                    continue;
                }

                $metadata = json_decode((string) $row->metadata, true) ?: [];
                $metadata['alias_of'] = $canonical;
                $metadata['retired'] = true;

                DB::table('pal_vocabulary')->where('id', $row->id)->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sort_order' => 900,
                    'updated_at' => now(),
                ]);
                $updated++;
            }
        }

        return ['inserted' => 0, 'updated' => $updated];
    }

    /**
     * Natively-implemented types first, in the order their hub cards should
     * appear; everything else follows in declaration order.
     */
    protected function h5pTypeOrder(array $types): array
    {
        $order = [];
        $tail = 100;

        foreach ($types as $code => $def) {
            $implementation = $def['implementation'] ?? [];
            $order[$code] = ($implementation['status'] ?? 'planned') === 'native'
                ? (int) ($implementation['sort_order'] ?? 50)
                : ++$tail;
        }

        return $order;
    }

    /**
     * Only reached on an estate where the registry table was never created.
     * Mirrors the live table exactly, including the
     * (domain, code, sub_institute_id) uniqueness the sync relies on.
     */
    protected function createRegistryTable(): void
    {
        Schema::create('pal_vocabulary', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 64);
            $table->string('code', 128);
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(1);
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->string('scope', 16)->default('global');
            $table->boolean('is_system')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'code', 'sub_institute_id'], 'pv_domain_code_tenant_unique');
            $table->index(['domain', 'status', 'sort_order'], 'pv_lookup_idx');
            $table->index(['sub_institute_id', 'scope'], 'pv_tenant_idx');
        });
    }
}
