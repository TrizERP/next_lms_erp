<?php

namespace App\Domain\AI\Configuration;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One answer to "what should this module call", for every AI module — as an ordered list.
 *
 * A module does not resolve to one provider but to a CHAIN: the first entry is what it calls,
 * the rest are what it falls back to when the first fails or its quota is spent. A single
 * `resolve()` is the head of that chain, so callers that want one answer still get one.
 *
 * WHERE THE CHAIN COMES FROM — AND WHAT IT NEVER COMES FROM
 *
 *   0. `binding`         — a product module's own choice on its own AI Stack (one entry, no fallback:
 *                          the module chose a model deliberately).
 *   1. `module`          — this school's saved rows for the module, ranked by `priority`.
 *   2. `module_platform` — the platform's saved rows for the module, ranked the same way.
 *      A module with no rows of its own uses the module it `inherits` from, if it names one.
 *   3. `pool`            — this school's shared keys, one per provider, when the module has no rows.
 *   4. `pool_platform`   — the platform's shared keys. This is where every module with nothing
 *                          saved resolves today.
 *
 * There is no step for the environment file. Credentials come from `ai_api_keys` and nowhere
 * else (`config('ai.allow_env_credentials')` exists for a deliberate bootstrap and is off). A
 * module whose saved configuration is missing or unusable resolves to NOTHING and is reported
 * as such — it is never quietly handed a provider the administrator did not choose.
 *
 * A PROVIDER MUST BE ABLE TO DO THE JOB
 *
 * Every candidate is checked against the module's declared needs (`AiModuleRegistry::requires`)
 * using what the provider's client can really do (`ProviderCapabilities`). An incapable
 * provider is skipped here exactly as the admin screen refuses to save it.
 *
 * HOW A MODULE WITH NOTHING SAVED IS ORDERED
 *
 * Among the shared pool's providers, an administrator's explicit priority decides. With none, the
 * order is what suits THIS module (`ProviderFitRanker`) — the same order the admin dropdown shows,
 * so what is displayed is what is executed. Only a call with no module at all lists the provider
 * named by `ai.provider.driver` first. Either way this orders credentials already stored in the
 * database; it never supplies a provider or a key that is not.
 *
 * NOTHING HERE READS REQUEST INPUT
 *
 * `$subInstituteId` comes from the caller's `McpRequestContext`, derived from their token, so no
 * caller can resolve another school's credential by naming it.
 */
final class AiConfigurationResolver
{
    /** @var array<string, array<int, object>|null> */
    private array $rowCache = [];

    /** @var array<string, array<string, mixed>|null> */
    private array $poolKeyCache = [];

    /** @var array<string, string|null> */
    private array $defaultModelCache = [];

    public function __construct(
        private readonly ProviderCatalog $providers,
        private readonly ModelCatalog $models,
        private readonly AiModuleRegistry $modules,
        private readonly \App\Domain\AI\Support\ProviderKeyResolver $keys,
        private readonly \App\Domain\AI\Support\SchemaCache $schema,
        private readonly ModuleModelBindings $bindings,
        private readonly ProviderCapabilities $capabilities,
        private readonly ProviderFitRanker $ranker,
    ) {
    }

    public function flush(?string $institute = null): void
    {
        if ($institute === null) {
            $this->rowCache = [];
            $this->poolKeyCache = [];
            $this->defaultModelCache = [];
        } else {
            unset(
                $this->rowCache[$institute],
                $this->rowCache[''],
                $this->poolKeyCache[$institute],
                $this->poolKeyCache['']
            );
            $this->defaultModelCache = [];
        }
    }

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------

    /**
     * Resolve the provider, model and credential for one module — the head of its chain.
     *
     * @param  string|null  $moduleKey  A key from `AiModuleRegistry` — the CAPABILITY, e.g.
     *                                  `conversational_ai` — or null for no module.
     * @param  string|null  $productModuleKey  An `ai_modules` key — the PRODUCT module the call is
     *                                  being made for, e.g. `fees`.
     */
    public function resolve(
        ?string $moduleKey,
        int|string|null $subInstituteId = null,
        ?string $productModuleKey = null
    ): ResolvedAiConfiguration {
        $moduleKey = $moduleKey !== null && $this->modules->exists($moduleKey) ? $moduleKey : null;
        $chain = $this->resolveChain($moduleKey, $subInstituteId, $productModuleKey);

        return $chain[0] ?? $this->unconfigured($moduleKey);
    }

    /**
     * Every provider this module may call, first choice first.
     *
     * @return array<int, ResolvedAiConfiguration>  Empty when nothing usable is configured.
     */
    public function resolveChain(
        ?string $moduleKey,
        int|string|null $subInstituteId = null,
        ?string $productModuleKey = null
    ): array {
        $moduleKey = $moduleKey !== null && $this->modules->exists($moduleKey) ? $moduleKey : null;

        // Step 0. The product module's OWN choice. Ahead of everything because it is the most
        // specific statement anybody has made: "when Fees makes a conversational call, use this model".
        $binding = $this->bindings->find($productModuleKey, $moduleKey, $subInstituteId);

        if ($binding !== null) {
            $config = $this->fromBinding($binding, $subInstituteId, $moduleKey);

            return $config !== null && $config->hasKey() ? [$config] : [];
        }

        // Steps 1-2. Saved rows for this module — or, with none, for the module it inherits from.
        $inheritedFrom = null;
        $rows = $this->moduleRowsFor($moduleKey, $subInstituteId, usableOnly: false);

        if ($rows === [] && $moduleKey !== null) {
            $parent = $this->modules->inheritsFrom($moduleKey);

            if ($parent !== null) {
                $rows = $this->moduleRowsFor($parent, $subInstituteId, usableOnly: false);
                $inheritedFrom = $rows === [] ? null : $parent;
            }
        }

        if ($rows !== []) {
            return $this->chainFromRows($rows, $moduleKey, $subInstituteId, $inheritedFrom);
        }

        // Steps 3-4. Nothing saved for this module: the shared pool, one entry per provider.
        return $this->chainFromPool($moduleKey, $subInstituteId);
    }

    /**
     * Why a module has no usable provider, as an exception an administrator can act on.
     *
     * Called by the factory when the chain is empty, so the message says WHICH of "nothing saved",
     * "saved with no key" and "saved but incapable" applies rather than one vague refusal.
     */
    public function explainEmpty(?string $moduleKey, int|string|null $subInstituteId = null): AiConfigurationException
    {
        $key = $moduleKey ?? 'ai';
        $label = $moduleKey !== null ? $this->modules->label($moduleKey) : 'This AI feature';
        $requires = $moduleKey !== null ? $this->modules->requires($moduleKey) : ['chat'];

        $rows = $this->moduleRowsFor($moduleKey, $subInstituteId, usableOnly: false);

        if ($rows === [] && $moduleKey !== null && ($parent = $this->modules->inheritsFrom($moduleKey)) !== null) {
            $rows = $this->moduleRowsFor($parent, $subInstituteId, usableOnly: false);
        }

        if ($rows !== []) {
            $row = $rows[0];
            $provider = $this->providerFromRow($row);

            if (trim((string) ($row->api_key ?? '')) === '' || trim((string) $row->api_key) === '-') {
                return AiConfigurationException::missingCredential($key, $label, $this->providers->label($provider));
            }

            $missing = $this->capabilities->missing($provider, $requires);

            if ($missing !== []) {
                return AiConfigurationException::noCapableProvider($key, $label, $this->capabilities->describe($missing));
            }
        }

        // Pool rows exist but none can serve the module: say what is missing.
        $poolProviders = $this->poolProviders($subInstituteId, $moduleKey);

        if ($rows === [] && $poolProviders !== []) {
            $needs = [];

            foreach ($poolProviders as $provider) {
                $needs = array_merge($needs, $this->capabilities->missing($provider, $requires));
            }

            if ($needs !== []) {
                return AiConfigurationException::noCapableProvider($key, $label, $this->capabilities->describe(array_values(array_unique($needs))));
            }
        }

        return AiConfigurationException::notConfigured($key, $label);
    }

    /**
     * What every module resolves to right now, for the admin screen's list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(int|string|null $subInstituteId = null): array
    {
        $out = [];

        foreach ($this->modules->all() as $module) {
            $chain = $this->resolveChain($module['key'], $subInstituteId);
            $head = $chain[0] ?? null;

            $out[] = [
                'module' => $module['key'],
                'module_label' => $module['label'],
                'description' => $module['description'],
                'wired' => $module['wired'],
                'runtime' => $module['runtime'],
                'gap' => $module['gap'],
                'requires' => $module['requires'],
                // Nothing configured means no provider — the environment's driver is not an answer.
                'provider' => $head?->provider,
                'provider_label' => $head !== null ? $this->providers->label($head->provider) : null,
                'model' => $head?->model,
                'source' => $head?->source ?? 'config',
                'scope' => $head?->scope ?? 'config',
                'key_id' => $head?->keyId,
                'has_key' => $head?->hasKey() ?? false,
                'driveable' => $head !== null && $this->providers->isDriveable($head->provider),
                'inherited_from' => $head?->inheritedFrom,
                'chain' => array_map(fn (ResolvedAiConfiguration $c, int $i) => [
                    'order' => $i + 1,
                    'provider' => $c->provider,
                    'provider_label' => $this->providers->label($c->provider),
                    'model' => $c->model,
                    'priority' => $c->priority,
                    'source' => $c->source,
                    'key_id' => $c->keyId,
                    'has_key' => $c->hasKey(),
                ], $chain, array_keys($chain)),
            ];
        }

        return $out;
    }

    /** Whether `ai_api_keys.priority` exists on this estate. */
    public function supportsPriority(): bool
    {
        return $this->schema->hasColumn('ai_api_keys', 'priority');
    }

    /**
     * Which provider a credential row belongs to.
     *
     * A row saved by the admin screen carries a provider key in `api_type`. The rows that predate
     * it carry whatever convention was current when they were written — `gemini` for one,
     * `OPENROUTER_API_KEY` for another — so each catalogue entry's declared `api_type` is matched.
     */
    public function providerFromRow(object $row): ?string
    {
        $apiType = trim((string) ($row->api_type ?? ''));

        if ($apiType === '') {
            return null;
        }

        if ($this->providers->exists($apiType)) {
            return $apiType;
        }

        foreach ($this->providers->keys() as $provider) {
            if (strcasecmp($this->providers->apiType($provider), $apiType) === 0) {
                return $provider;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Chain construction
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, object>  $rows  Already scoped and ranked: school before platform, then priority.
     * @return array<int, ResolvedAiConfiguration>
     */
    private function chainFromRows(array $rows, ?string $moduleKey, int|string|null $subInstituteId, ?string $inheritedFrom): array
    {
        $requires = $moduleKey !== null ? $this->modules->requires($moduleKey) : ['chat'];
        $chain = [];
        $seen = [];
        $instituteProviders = [];

        foreach ($rows as $row) {
            $provider = $this->providerFromRow($row);

            if ($provider === null) {
                continue;
            }

            $isPlatform = ($row->source ?? '') === 'module_platform';

            // A school's row for a provider shadows the platform's rows for that provider.
            if ($isPlatform && isset($instituteProviders[$provider])) {
                continue;
            }

            // A row the administrator saved with no usable key is reported, not skipped past to the pool.
            if (empty($row->api_key) || trim((string) $row->api_key) === '-') {
                continue;
            }

            if (! $this->providers->isDriveable($provider) || $this->capabilities->missing($provider, $requires) !== []) {
                continue;
            }

            if ($moduleKey !== null && ! $this->modules->isOffered($moduleKey, $provider)) {
                continue;
            }

            $model = $this->modelFor($provider, $row->model ?? null, $subInstituteId, $moduleKey);
            $key = trim((string) $row->api_key);
            $signature = $provider . '|' . $model . '|' . $key;

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            if (! $isPlatform) {
                $instituteProviders[$provider] = true;
            }

            $chain[] = new ResolvedAiConfiguration(
                provider: $provider,
                model: $model,
                apiKey: $key,
                source: (string) $row->source,
                keyId: $row->id ?? null,
                scope: ($row->sub_institute_id ?? null) === null ? 'platform' : 'institute',
                maxOutputTokens: $this->maxTokens($row),
                priority: $this->priorityOf($row),
                inheritedFrom: $inheritedFrom,
                module: $moduleKey,
            );
        }

        return $chain;
    }

    /**
     * One entry per provider that has an active key in the shared pool.
     *
     * @return array<int, ResolvedAiConfiguration>
     */
    private function chainFromPool(?string $moduleKey, int|string|null $subInstituteId): array
    {
        $requires = $moduleKey !== null ? $this->modules->requires($moduleKey) : ['chat'];
        $chain = [];

        foreach ($this->poolProviders($subInstituteId, $moduleKey) as $provider) {
            if (! $this->providers->isDriveable($provider) || $this->capabilities->missing($provider, $requires) !== []) {
                continue;
            }

            if ($moduleKey !== null && ! $this->modules->isOffered($moduleKey, $provider)) {
                continue;
            }

            $key = $this->poolKey($provider, $subInstituteId);

            if ($key === null) {
                continue;
            }

            $chain[] = new ResolvedAiConfiguration(
                provider: $provider,
                model: $this->modelFor($provider, null, $subInstituteId, $moduleKey),
                apiKey: $key['api_key'],
                source: $this->sourceFor($key),
                keyId: $key['id'] ?? null,
                scope: $key['scope'] ?? 'platform',
                maxOutputTokens: $this->poolMaxTokens($key, $provider),
                priority: $key['priority'] ?? null,
                module: $moduleKey,
            );
        }

        return $chain;
    }

    /** A product module's own binding as a configuration, or null when its provider is unusable. */
    private function fromBinding(object $binding, int|string|null $subInstituteId, ?string $moduleKey): ?ResolvedAiConfiguration
    {
        $provider = $this->providerFromRow((object) ['api_type' => $binding->provider ?? null]);

        if ($provider === null || ! $this->providers->isDriveable($provider)) {
            return null;
        }

        $requires = $moduleKey !== null ? $this->modules->requires($moduleKey) : ['chat'];

        if ($this->capabilities->missing($provider, $requires) !== []) {
            return null;
        }

        $key = $this->bindingKey($binding, $provider, $subInstituteId);

        return new ResolvedAiConfiguration(
            provider: $provider,
            model: $this->modelFor($provider, $binding->model ?? null, $subInstituteId, $moduleKey),
            apiKey: $key['api_key'] ?? null,
            source: (string) $binding->source,
            keyId: $key['id'] ?? null,
            // The scope of the CHOICE, not of the credential: what the screen asked about is whose decision this was.
            scope: ($binding->sub_institute_id ?? null) === null ? 'platform' : 'institute',
            maxOutputTokens: $this->bindingMaxTokens($binding, $key, $provider),
            module: $moduleKey,
        );
    }

    /**
     * The credential a binding uses: its own named key if it has a usable one, else the pool's key
     * for the same provider.
     *
     * @return array<string, mixed>|null
     */
    private function bindingKey(object $binding, string $provider, int|string|null $subInstituteId): ?array
    {
        $keyId = $binding->api_key_id ?? null;

        if ($keyId !== null && $this->schema->hasTable('ai_api_keys')) {
            $row = DB::table('ai_api_keys')
                ->where('id', (int) $keyId)
                ->where('status', 1)
                ->where(function ($query) use ($subInstituteId) {
                    $query->whereNull('sub_institute_id');

                    if ($subInstituteId !== null) {
                        $query->orWhere('sub_institute_id', $subInstituteId);
                    }
                })
                ->first();

            if ($row !== null && trim((string) ($row->api_key ?? '')) !== '' && trim((string) $row->api_key) !== '-') {
                return [
                    'id' => (int) $row->id,
                    'api_key' => trim((string) $row->api_key),
                    'api_limit' => $row->api_limit ?? null,
                    'scope' => ($row->sub_institute_id ?? null) === null ? 'platform' : 'institute',
                ];
            }
        }

        return $this->poolKey($provider, $subInstituteId);
    }

    /** The binding's own ceiling, else the credential's, else the provider's configured one. */
    private function bindingMaxTokens(object $binding, ?array $key, string $provider): ?int
    {
        $own = $binding->max_output_tokens ?? null;

        if (is_numeric($own) && (int) $own > 0) {
            return (int) $own;
        }

        return $this->poolMaxTokens($key, $provider);
    }

    /** What a module resolves to when nothing usable exists: no key, so nothing can be called. */
    private function unconfigured(?string $moduleKey): ResolvedAiConfiguration
    {
        return new ResolvedAiConfiguration(
            provider: $this->defaultProvider(),
            model: null,
            apiKey: null,
            source: 'config',
            keyId: null,
            scope: 'config',
            maxOutputTokens: null,
            module: $moduleKey,
        );
    }

    // ---------------------------------------------------------------------
    // Rows
    // ---------------------------------------------------------------------

    /**
     * Active rows saved for one module, school first then platform, each ranked by priority.
     *
     * @return array<int, object>
     */
    private function moduleRowsFor(?string $moduleKey, int|string|null $subInstituteId, bool $usableOnly = true): array
    {
        if ($moduleKey === null || ! $this->schema->hasColumn('ai_api_keys', 'ai_module')) {
            return [];
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $all = $this->activeRows($institute);

        if ($all === null) {
            // A key-table outage: no rows is the honest answer, and the caller reports it.
            return [];
        }

        $out = [];

        foreach ([['module', fn (object $r) => $institute !== null && (string) ($r->sub_institute_id ?? '') === $institute],
                  ['module_platform', fn (object $r) => ($r->sub_institute_id ?? null) === null]] as [$source, $matches]) {
            $scoped = [];

            foreach ($all as $row) {
                if ((string) ($row->ai_module ?? '') !== $moduleKey || ! $matches($row)) {
                    continue;
                }

                if ($usableOnly && (empty($row->api_key) || trim((string) $row->api_key) === '-')) {
                    continue;
                }

                // Cloned before stamping: the row is shared with every other module resolved from
                // this cache, and writing `source` onto the original would leak one module's
                // precedence onto the next one's copy.
                $found = clone $row;
                $found->source = $source;
                $scoped[] = $found;
            }

            $out = array_merge($out, $this->ranked($scoped));
        }

        return $out;
    }

    /**
     * Providers that have an active key in the shared pool, first choice first.
     *
     * @return array<int, string>
     */
    private function poolProviders(int|string|null $subInstituteId, ?string $moduleKey = null): array
    {
        $institute = $this->normaliseInstitute($subInstituteId);
        $all = $this->activeRows($institute);

        if ($all === null) {
            return [];
        }

        $hasModuleColumn = $this->schema->hasColumn('ai_api_keys', 'ai_module');
        $pool = [];

        foreach ($all as $row) {
            if ($hasModuleColumn && ($row->ai_module ?? null) !== null && trim((string) $row->ai_module) !== '') {
                continue;
            }

            if (empty($row->api_key) || trim((string) $row->api_key) === '-') {
                continue;
            }

            $pool[] = $row;
        }

        // A school's own keys come before the platform's, as everywhere else; each group is ranked.
        $isOwn = fn (object $r) => $institute !== null && (string) ($r->sub_institute_id ?? '') === $institute;

        $ranked = array_merge(
            $this->ranked(array_values(array_filter($pool, $isOwn))),
            $this->ranked(array_values(array_filter($pool, fn (object $r) => ! $isOwn($r))))
        );

        $anyRanked = false;

        foreach ($ranked as $row) {
            if ($this->priorityOf($row) !== null) {
                $anyRanked = true;
                break;
            }
        }

        $providers = [];

        foreach ($ranked as $row) {
            $provider = $this->providerFromRow($row);

            if ($provider !== null && ! in_array($provider, $providers, true)) {
                $providers[] = $provider;
            }
        }

        // Nobody ranked the pool. For a module, order by what suits THAT module (`ProviderFitRanker` —
        // the same order the admin dropdown shows). Only with no module at all is the configured
        // driver listed first; either way this orders credentials already in the database and
        // supplies none.
        if (! $anyRanked) {
            if ($moduleKey !== null) {
                $providers = $this->ranker->order($moduleKey, $providers, $subInstituteId);
            } else {
                $driver = $this->defaultProvider();

                if (in_array($driver, $providers, true)) {
                    $providers = array_values(array_unique([$driver, ...$providers]));
                }
            }
        }

        return $providers;
    }

    /**
     * Rank by priority (1 first, unranked last), newest first among equals — the convention a
     * rotated key expects, and what stops a dead older key being picked.
     *
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    private function ranked(array $rows): array
    {
        usort($rows, function (object $a, object $b) {
            $pa = $this->priorityOf($a) ?? PHP_INT_MAX;
            $pb = $this->priorityOf($b) ?? PHP_INT_MAX;

            return [$pa, -((int) ($a->id ?? 0))] <=> [$pb, -((int) ($b->id ?? 0))];
        });

        return array_values($rows);
    }

    private function priorityOf(object $row): ?int
    {
        $value = $row->priority ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Every active credential row this institute can see, read once per request.
     *
     * Returns null — distinct from an empty list — when the table could not be read, so a caller
     * can tell "no credentials" from "no answer".
     *
     * @return array<int, object>|null
     */
    private function activeRows(?string $institute): ?array
    {
        $cacheKey = $institute ?? '';

        if (array_key_exists($cacheKey, $this->rowCache)) {
            return $this->rowCache[$cacheKey];
        }

        if (! $this->schema->hasTable('ai_api_keys')) {
            return $this->rowCache[$cacheKey] = [];
        }

        try {
            $rows = DB::table('ai_api_keys')
                ->where('status', 1)
                ->where(function ($inner) use ($institute) {
                    $inner->whereNull('sub_institute_id');

                    if ($institute !== null) {
                        $inner->orWhere('sub_institute_id', $institute);
                    }
                })
                ->orderByDesc('id')
                ->get()
                ->all();
        } catch (Throwable) {
            return $this->rowCache[$cacheKey] = null;
        }

        return $this->rowCache[$cacheKey] = $rows;
    }

    /**
     * The shared pool's credential for a provider, resolved once per (provider, institute).
     *
     * Delegates to `ProviderKeyResolver` — with no environment fallback — so the key a legacy
     * caller finds and the key this chain finds cannot disagree.
     *
     * @return array<string, mixed>|null
     */
    private function poolKey(string $provider, int|string|null $subInstituteId): ?array
    {
        $cacheKey = $provider . '|' . ($subInstituteId === null ? '' : (string) $subInstituteId);

        if (array_key_exists($cacheKey, $this->poolKeyCache)) {
            return $this->poolKeyCache[$cacheKey];
        }

        return $this->poolKeyCache[$cacheKey] = $this->keys->resolve(
            $this->providers->apiType($provider),
            $subInstituteId,
            null,
        );
    }

    /** How a pool credential was found, in this class's vocabulary. */
    private function sourceFor(?array $key): string
    {
        return match ($key['scope'] ?? null) {
            'institute' => 'pool',
            'platform' => 'pool_platform',
            default => 'config',
        };
    }

    /** The pool row's own ceiling, else the provider's configured one. */
    private function poolMaxTokens(?array $key, string $provider): ?int
    {
        $limit = $key['api_limit'] ?? null;

        if (is_numeric($limit) && (int) $limit > 0) {
            return (int) $limit;
        }

        return ((int) config("ai.provider.{$provider}.max_output_tokens")) ?: null;
    }

    /**
     * The saved model, else the catalogue's first model for this provider that can serve the
     * module, else config's.
     */
    private function modelFor(string $provider, ?string $saved, int|string|null $subInstituteId, ?string $moduleKey = null): ?string
    {
        $saved = trim((string) $saved);

        if ($saved !== '') {
            return $saved;
        }

        // An embedding or image module has no sensible text-model default: handing it the catalogue's
        // chat model would 404 at the provider. Null lets the caller use its own purpose-specific default.
        if ($moduleKey !== null && array_intersect($this->modules->requires($moduleKey), ['embedding', 'image_generation']) !== []) {
            return null;
        }

        $cacheKey = $provider . '|' . ($subInstituteId === null ? '' : (string) $subInstituteId) . '|' . ($moduleKey ?? '');

        if (! array_key_exists($cacheKey, $this->defaultModelCache)) {
            $this->defaultModelCache[$cacheKey] = $this->catalogueDefault($provider, $subInstituteId, $moduleKey);
        }

        return $this->defaultModelCache[$cacheKey] ?? $this->providers->defaultModel($provider);
    }

    private function catalogueDefault(string $provider, int|string|null $subInstituteId, ?string $moduleKey): ?string
    {
        $min = $moduleKey !== null ? $this->modules->minOutputTokens($moduleKey) : null;

        foreach ($this->models->forProvider($provider, $subInstituteId) as $model) {
            $ceiling = $model['max_output_tokens'] ?? null;

            // A model whose recorded ceiling is below what the module needs is not a default for it.
            if ($min !== null && $ceiling !== null && $ceiling < $min) {
                continue;
            }

            return $model['model_id'];
        }

        return null;
    }

    /**
     * `api_limit` has always doubled as the per-key output ceiling — `OpenRouterClient` reads it
     * that way — so it keeps that meaning here rather than gaining a column.
     */
    private function maxTokens(object $row): ?int
    {
        $limit = $row->api_limit ?? null;

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    private function normaliseInstitute(int|string|null $subInstituteId): ?string
    {
        $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);

        return $institute === '' ? null : $institute;
    }

    /** Only used to label an unconfigured module and to order unranked pool providers. */
    private function defaultProvider(): string
    {
        $driver = trim((string) config('ai.provider.driver', 'gemini'));

        return $this->providers->exists($driver) ? $driver : 'gemini';
    }
}
