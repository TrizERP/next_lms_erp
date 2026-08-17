<?php

namespace App\Services\PAL\H5P;

use Illuminate\Support\Facades\DB;

/**
 * The H5P Model registry.
 *
 * Single read path for every vocabulary, mapping and relationship the H5P
 * Model needs: the 21 H5P content types, the 12 pedagogies, the CASEL / NGSS /
 * NCDG / Music / Sports / Finance frameworks, the pedagogy × framework
 * coverage matrix, the xAPI verb map and the engagement signal weights.
 *
 * Everything comes out of `pal_vocabulary`. config/pal_h5p.php is the seed the
 * migration wrote from and the fallback used when the table is unreachable
 * (fresh schema, unit tests) — it is never consulted when the DB answers, so a
 * school that edits a row, retunes the coverage matrix or registers a 22nd H5P
 * type changes behaviour without a deploy.
 *
 * Tenancy: global system rows (sub_institute_id 0) are the base; a tenant's own
 * rows are merged on top of them by code, so an institute can override a label
 * or add a type without touching anyone else's registry.
 *
 * Aliases: a row whose metadata carries `alias_of` is a retired spelling. It is
 * hidden from the catalog but still resolves through normalize(), so content
 * tagged with a pre-PAL-V4 code keeps working.
 */
class H5PModelRegistry
{
    /** Domains the H5P Model reads. Anything else in the registry is ignored. */
    public const DOMAINS = [
        'h5p_types',
        'pedagogy_tags',
        'pedagogy_selection_rules',
        'casel_domains',
        'ngss_practices',
        'ncdg_goals',
        'music_domains',
        'sports_domains',
        'finance_levels',
        'gardner_intelligences',
        'riasec_signals',
        'hpc_lenses',
        'bloom_levels',
        'xapi_verbs',
        'engagement_signals',
    ];

    /** Registry domain → the framework key used in a pedagogy's coverage map. */
    public const FRAMEWORK_DOMAINS = [
        'casel' => 'casel_domains',
        'ngss' => 'ngss_practices',
        'ncdg' => 'ncdg_goals',
        'music' => 'music_domains',
        'sports' => 'sports_domains',
        'finance' => 'finance_levels',
    ];

    /** Per-request memo, keyed by tenant. One query serves a whole request. */
    protected static array $memo = [];

    /** Whether the last load came from the database or the config fallback. */
    protected static array $source = [];

    // ── Loading ─────────────────────────────────────────────────────────────

    /**
     * Every domain for a tenant: domain => [code => term].
     * A term is ['code','label','description','metadata','sort_order','alias_of','is_system'].
     */
    public function all(?int $subInstituteId = null): array
    {
        $tenant = (int) ($subInstituteId ?? 0);

        if (isset(self::$memo[$tenant])) {
            return self::$memo[$tenant];
        }

        $loaded = $this->loadFromDatabase($tenant);

        if ($loaded === null) {
            self::$source[$tenant] = 'config';

            return self::$memo[$tenant] = $this->loadFromConfig();
        }

        // A domain the DB has no rows for falls back to its seed, so a partially
        // migrated estate degrades one domain at a time rather than wholesale.
        $fallback = $this->loadFromConfig();
        foreach ($fallback as $domain => $terms) {
            if (empty($loaded[$domain])) {
                $loaded[$domain] = $terms;
            }
        }

        self::$source[$tenant] = 'database';

        return self::$memo[$tenant] = $loaded;
    }

    /** 'database' | 'config' — surfaced in the API so the UI can be honest. */
    public function source(?int $subInstituteId = null): string
    {
        $tenant = (int) ($subInstituteId ?? 0);
        if (! isset(self::$source[$tenant])) {
            $this->all($tenant);
        }

        return self::$source[$tenant] ?? 'config';
    }

    /** Canonical terms of one domain, aliases excluded, in sort order. */
    public function domain(string $domain, ?int $subInstituteId = null): array
    {
        $terms = $this->all($subInstituteId)[$domain] ?? [];

        return array_filter($terms, fn (array $term) => ! $term['is_alias']);
    }

    /** One term, resolving aliases. Null when the code is unknown. */
    public function term(string $domain, ?string $code, ?int $subInstituteId = null): ?array
    {
        $resolved = $this->normalize($domain, $code, $subInstituteId);
        if ($resolved === null) {
            return null;
        }

        return $this->all($subInstituteId)[$domain][$resolved] ?? null;
    }

    /**
     * Resolve a raw tag to its canonical registry code.
     *
     * Handles the spellings that actually turn up in this estate: display
     * labels ("Interactive Video"), hyphens ("inquiry-based"), the legacy
     * codes marked `alias_of`, and the per-pedagogy `aliases` list from the
     * seed. Returns null when nothing matches — an unknown tag is not silently
     * coerced into a real one.
     */
    public function normalize(string $domain, ?string $code, ?int $subInstituteId = null): ?string
    {
        $raw = trim((string) $code);
        if ($raw === '') {
            return null;
        }

        $terms = $this->all($subInstituteId)[$domain] ?? [];

        // RIASEC and NCDG codes are upper-case; everything else is snake_case.
        $candidates = array_unique([
            $raw,
            strtoupper($raw),
            strtolower(str_replace([' ', '-', '/'], '_', $raw)),
        ]);

        foreach ($candidates as $candidate) {
            if (isset($terms[$candidate])) {
                return $terms[$candidate]['is_alias']
                    ? $terms[$candidate]['alias_of']
                    : $candidate;
            }
        }

        // Match on label, then on a pedagogy's declared aliases.
        $needle = strtolower(str_replace([' ', '-', '_'], '', $raw));
        foreach ($terms as $termCode => $term) {
            if (strtolower(str_replace([' ', '-', '_'], '', (string) $term['label'])) === $needle) {
                return $term['is_alias'] ? $term['alias_of'] : $termCode;
            }
            foreach ((array) ($term['metadata']['aliases'] ?? []) as $alias) {
                if (strtolower(str_replace([' ', '-', '_'], '', (string) $alias)) === $needle) {
                    return $termCode;
                }
            }
        }

        return null;
    }

    // ── H5P content types ───────────────────────────────────────────────────

    /** All 21 types, sorted with the natively implemented ones first. */
    public function types(?int $subInstituteId = null): array
    {
        return $this->domain('h5p_types', $subInstituteId);
    }

    public function type(?string $code, ?int $subInstituteId = null): ?array
    {
        return $this->term('h5p_types', $code, $subInstituteId);
    }

    /**
     * The types with a real table behind them in this ERP. These are what the
     * H5P Content hub renders — the hub is a projection of the registry, not a
     * hand-written list of cards.
     */
    public function nativeTypes(?int $subInstituteId = null): array
    {
        return array_filter(
            $this->types($subInstituteId),
            fn (array $type) => ($type['metadata']['implementation']['status'] ?? 'planned') === 'native'
        );
    }

    /** Registry code for a native type backed by a given table, if any. */
    public function typeForTable(string $table, ?int $subInstituteId = null): ?string
    {
        foreach ($this->nativeTypes($subInstituteId) as $code => $type) {
            $implementation = $type['metadata']['implementation'] ?? [];
            if (($implementation['source_table'] ?? null) === $table
                || ($implementation['fallback_table'] ?? null) === $table) {
                return $code;
            }
        }

        return null;
    }

    // ── Pedagogies + the §9 coverage matrix ─────────────────────────────────

    public function pedagogies(?int $subInstituteId = null): array
    {
        return $this->domain('pedagogy_tags', $subInstituteId);
    }

    public function pedagogy(?string $code, ?int $subInstituteId = null): ?array
    {
        return $this->term('pedagogy_tags', $code, $subInstituteId);
    }

    /** H5P types a pedagogy is authored against, primary first. */
    public function h5pTypesForPedagogy(?string $pedagogy, ?int $subInstituteId = null): array
    {
        $term = $this->pedagogy($pedagogy, $subInstituteId);
        if ($term === null) {
            return ['primary' => [], 'secondary' => []];
        }

        return [
            'primary' => array_values((array) ($term['metadata']['primary_h5p'] ?? [])),
            'secondary' => array_values((array) ($term['metadata']['secondary_h5p'] ?? [])),
        ];
    }

    /** Pedagogies that name this H5P type, split by primary / secondary. */
    public function pedagogiesForH5pType(?string $h5pType, ?int $subInstituteId = null): array
    {
        $type = $this->normalize('h5p_types', $h5pType, $subInstituteId);
        if ($type === null) {
            return ['primary' => [], 'secondary' => []];
        }

        $primary = [];
        $secondary = [];
        foreach ($this->pedagogies($subInstituteId) as $code => $term) {
            if (in_array($type, (array) ($term['metadata']['primary_h5p'] ?? []), true)) {
                $primary[] = $code;
            } elseif (in_array($type, (array) ($term['metadata']['secondary_h5p'] ?? []), true)) {
                $secondary[] = $code;
            }
        }

        return ['primary' => $primary, 'secondary' => $secondary];
    }

    /**
     * §9 — pedagogy × framework coverage.
     *
     * pedagogy => framework => [tag => 'strong'|'supporting'].
     */
    public function coverageMatrix(?int $subInstituteId = null): array
    {
        $matrix = [];
        foreach ($this->pedagogies($subInstituteId) as $code => $term) {
            $coverage = (array) ($term['metadata']['coverage'] ?? []);
            $row = [];
            foreach (array_keys(self::FRAMEWORK_DOMAINS) as $framework) {
                $row[$framework] = (array) ($coverage[$framework] ?? []);
            }
            $matrix[$code] = $row;
        }

        return $matrix;
    }

    /**
     * Inverse of the matrix: which pedagogies generate evidence for a given
     * framework tag, and how strongly. Drives the "SEL coverage is low —
     * prioritise these pedagogies" reading of §9.
     */
    public function pedagogiesForFrameworkTag(string $framework, string $tag, ?int $subInstituteId = null): array
    {
        $out = [];
        foreach ($this->coverageMatrix($subInstituteId) as $pedagogy => $row) {
            $strength = $row[$framework][$tag] ?? null;
            if ($strength !== null) {
                $out[$pedagogy] = $strength;
            }
        }

        return $out;
    }

    // ── Frameworks ──────────────────────────────────────────────────────────

    /** All six frameworks keyed by their coverage-matrix key. */
    public function frameworks(?int $subInstituteId = null): array
    {
        $out = [];
        foreach (self::FRAMEWORK_DOMAINS as $framework => $domain) {
            $out[$framework] = $this->domain($domain, $subInstituteId);
        }

        return $out;
    }

    // ── xAPI ────────────────────────────────────────────────────────────────

    /** Verb IRI => short verb code (§8.2 verbMap, read from the registry). */
    public function verbIriMap(?int $subInstituteId = null): array
    {
        $map = [];
        foreach ($this->domain('xapi_verbs', $subInstituteId) as $code => $term) {
            $iri = $term['metadata']['iri'] ?? null;
            if ($iri) {
                $map[$iri] = $code;
            }
        }

        return $map;
    }

    /** Short verb code => PAL event type. */
    public function verbEventTypeMap(?int $subInstituteId = null): array
    {
        $map = [];
        foreach ($this->domain('xapi_verbs', $subInstituteId) as $code => $term) {
            $map[$code] = $term['metadata']['pal_event_type'] ?? $code;
        }

        return $map;
    }

    /** Verb codes flagged `important` — the teacher-visible signal events. */
    public function importantVerbs(?int $subInstituteId = null): array
    {
        return array_keys(array_filter(
            $this->domain('xapi_verbs', $subInstituteId),
            fn (array $term) => (bool) ($term['metadata']['important'] ?? false)
        ));
    }

    /** Engagement signal weights, normalised to sum to 1. */
    public function engagementWeights(?int $subInstituteId = null): array
    {
        $weights = [];
        foreach ($this->domain('engagement_signals', $subInstituteId) as $code => $term) {
            $weights[$code] = (float) ($term['metadata']['weight'] ?? 0);
        }

        $total = array_sum($weights);
        if ($total <= 0) {
            return $weights;
        }

        return array_map(fn (float $w) => round($w / $total, 4), $weights);
    }

    /** §1.3 selection rules, in evaluation order. */
    public function selectionRules(?int $subInstituteId = null): array
    {
        $rules = $this->domain('pedagogy_selection_rules', $subInstituteId);
        uasort($rules, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $rules;
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /** @return array|null null when the registry table cannot be read at all. */
    protected function loadFromDatabase(int $tenant): ?array
    {
        try {
            $rows = DB::table('pal_vocabulary')
                ->whereIn('domain', self::DOMAINS)
                ->where('status', 1)
                ->where(function ($query) use ($tenant) {
                    $query->where('sub_institute_id', 0);
                    if ($tenant > 0) {
                        $query->orWhere('sub_institute_id', $tenant);
                    }
                })
                // Tenant rows are read last so they overwrite the global row.
                ->orderBy('sub_institute_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['domain', 'code', 'label', 'description', 'metadata', 'sort_order', 'is_system']);
        } catch (\Throwable $e) {
            return null;
        }

        if ($rows->isEmpty()) {
            return null;
        }

        $out = [];
        foreach ($rows as $row) {
            $metadata = json_decode((string) $row->metadata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            $isAlias = array_key_exists('alias_of', $metadata);

            $out[$row->domain][$row->code] = [
                'code' => (string) $row->code,
                'label' => (string) $row->label,
                'description' => $row->description !== null && $row->description !== '' ? (string) $row->description : null,
                'metadata' => $metadata,
                'sort_order' => (int) $row->sort_order,
                'is_system' => (bool) $row->is_system,
                'is_alias' => $isAlias,
                'alias_of' => $isAlias ? $metadata['alias_of'] : null,
            ];
        }

        foreach ($out as $domain => $terms) {
            uasort($terms, fn ($a, $b) => [$a['sort_order'], $a['code']] <=> [$b['sort_order'], $b['code']]);
            $out[$domain] = $terms;
        }

        return $out;
    }

    /** Offline fallback shaped exactly like the DB load. */
    protected function loadFromConfig(): array
    {
        $seed = config('pal_h5p', []);
        $out = [];

        foreach (self::DOMAINS as $domain) {
            $definitions = $seed[$domain] ?? [];
            $position = 0;
            foreach ($definitions as $code => $definition) {
                $definition = (array) $definition;
                $metadata = $definition;
                unset($metadata['label'], $metadata['description']);

                $out[$domain][(string) $code] = [
                    'code' => (string) $code,
                    'label' => (string) ($definition['label'] ?? $code),
                    'description' => $definition['description'] ?? null,
                    'metadata' => $metadata,
                    'sort_order' => ++$position,
                    'is_system' => true,
                    'is_alias' => false,
                    'alias_of' => null,
                ];
            }
        }

        foreach ($seed['legacy_aliases'] ?? [] as $domain => $map) {
            foreach ($map as $legacy => $canonical) {
                $out[$domain][(string) $legacy] = [
                    'code' => (string) $legacy,
                    'label' => (string) $legacy,
                    'description' => null,
                    'metadata' => ['alias_of' => $canonical, 'retired' => true],
                    'sort_order' => 900,
                    'is_system' => true,
                    'is_alias' => true,
                    'alias_of' => $canonical,
                ];
            }
        }

        // bloom_levels lives in the Content Intelligence config, not this seed.
        foreach (config('pal_content.bloom_levels', []) as $code => $definition) {
            $out['bloom_levels'][(string) $code] = [
                'code' => (string) $code,
                'label' => (string) ($definition['label'] ?? $code),
                'description' => null,
                'metadata' => $definition,
                'sort_order' => (int) ($definition['ordinal'] ?? 0),
                'is_system' => true,
                'is_alias' => false,
                'alias_of' => null,
            ];
        }

        return $out;
    }

    /** Drop the per-request memo — used by tests and after a registry write. */
    public static function flush(): void
    {
        self::$memo = [];
        self::$source = [];
    }
}
