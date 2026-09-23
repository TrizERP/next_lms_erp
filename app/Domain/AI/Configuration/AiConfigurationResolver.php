<?php

namespace App\Domain\AI\Configuration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One answer to "what should this module call", for every AI module.
 *
 * THE PRECEDENCE, AND WHY IT IS THIS ORDER
 *
 * Six rules, narrowest first. Each step is strictly more specific than the one below
 * it, so the most deliberate thing anyone has said always wins:
 *
 *   1. `module`          — this school saved a row for this module. The most specific
 *                          statement anyone can make, so nothing overrides it.
 *   2. `module_platform` — the platform saved a row for this module, for all schools.
 *   3. `pool`            — this school's key for the configured driver.
 *   4. `pool_platform`   — the platform's key for the configured driver. **This is where
 *                          every module on this estate resolves today**, which is why
 *                          nothing changes until somebody saves a module row.
 *   5. `env`             — the driver's key from `config/ai.php`, so a key-table
 *                          outage degrades instead of taking AI down.
 *   6. `config`          — no key at all: provider and model resolved, credential
 *                          missing. Returned rather than thrown so the caller can say
 *                          "not configured" instead of "something failed".
 *
 * Steps 3-6 are not reimplemented here — they are `ProviderKeyResolver`, called. This
 * class wraps it and adds the module dimension in front, so a caller with no module
 * gets byte-identical behaviour and the two cannot drift apart later.
 *
 * WHICH PROVIDER WHEN NOBODY SAID — AND WHY IT IS NOT THE NEWEST KEY
 *
 * Only a module row may name a provider. With no module row the provider is
 * `config('ai.provider.driver')` — the same value `AiServiceProvider` has always used
 * to pick a client — and the credential is then looked up *for that provider*.
 *
 * The inverse, taking the newest active key and inferring the provider from it, is
 * wrong and measurably so on this estate: the newest active row is a Gemini credential
 * while `AI_PROVIDER` is openrouter, so inferring would have moved every unconfigured
 * module onto a provider it is not running on today. Driver first, then its key.
 *
 * NOTHING HERE READS REQUEST INPUT
 *
 * `$subInstituteId` comes from the caller's `McpRequestContext`, which is derived from
 * their token. This class never reads the request, so no caller can resolve another
 * school's credential by naming it.
 */
final class AiConfigurationResolver
{
    public function __construct(
        private readonly ProviderCatalog $providers,
        private readonly ModelCatalog $models,
        private readonly AiModuleRegistry $modules,
        private readonly \App\Domain\AI\Support\ProviderKeyResolver $keys,
        private readonly \App\Domain\AI\Support\SchemaCache $schema,
        private readonly ModuleModelBindings $bindings,
    ) {
    }

    /**
     * Build a configuration from a product module's own binding.
     *
     * THE CREDENTIAL IS USUALLY NOT THE BINDING'S OWN
     *
     * A binding names a provider and a model; `api_key_id` is optional and usually null,
     * because the common case is a school wanting one module on a different MODEL while
     * still using the estate's credential and quota. When it is null the key is resolved
     * for the chosen provider exactly as it would have been — so choosing a model never
     * silently demands a second key, and a module whose provider matches the estate's
     * keeps using the same pool row it always did.
     *
     * A binding naming a key that has since been deleted or deactivated falls back to the
     * pool rather than failing: the module's model choice survives its credential being
     * rotated, which is the behaviour somebody rotating a key expects.
     */
    private function fromBinding(object $binding, int|string|null $subInstituteId): ResolvedAiConfiguration
    {
        // Reuses the same normalisation a credential row gets, so a binding saying
        // `OPENROUTER_API_KEY` and one saying `openrouter` resolve to one provider —
        // the screen offers canonical keys, but a row written by hand may not.
        $provider = $this->providerFromRow((object) ['api_type' => $binding->provider ?? null]);
        $key = $this->bindingKey($binding, $provider, $subInstituteId);

        return new ResolvedAiConfiguration(
            provider: $provider,
            model: $this->modelFor($provider, $binding->model ?? null, $subInstituteId),
            apiKey: $key['api_key'] ?? null,
            source: (string) $binding->source,
            keyId: $key['id'] ?? null,
            // The scope of the CHOICE, not of the credential: what the screen asked about
            // is whose decision this was.
            scope: ($binding->sub_institute_id ?? null) === null ? 'platform' : 'institute',
            maxOutputTokens: $this->bindingMaxTokens($binding, $key, $provider),
        );
    }

    /**
     * The credential a binding uses: its own named key if it has a usable one, else the pool.
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

    /**
     * Per-request memos.
     *
     * `overview()` asks this class the same three questions fourteen times over — which
     * module credentials exist, which credential the shared pool resolves to, and what the
     * model catalogue's default is for a provider. Measured before these memos, that call
     * ran 126 queries in 31.1 seconds against a remote database; the answers were
     * identical every time, because none of them can change inside one request.
     *
     * The instance is resolved per request, so nothing here outlives the request that
     * built it. `resolve()` for a single module behaves exactly as it did — it simply
     * fills a cache nobody else asks for.
     *
     * @var array<string, array<int, object>|null>
     */
    private array $moduleRowCache = [];

    /** @var array<string, array<string, mixed>|null> */
    private array $poolKeyCache = [];

    /** @var array<string, string|null> */
    private array $defaultModelCache = [];

    /**
     * Resolve the provider, model and credential for one module.
     *
     * @param  string|null  $moduleKey  A key from `AiModuleRegistry` — the CAPABILITY, e.g.
     *                                  `conversational_ai` — or null for the unbound pool
     *                                  behaviour every legacy caller has.
     * @param  string|null  $productModuleKey  An `ai_modules` key — the PRODUCT module the
     *                                  call is being made for, e.g. `fees`. Optional, and
     *                                  omitting it gives exactly the behaviour this method
     *                                  had before per-module bindings existed.
     */
    public function resolve(
        ?string $moduleKey,
        int|string|null $subInstituteId = null,
        ?string $productModuleKey = null
    ): ResolvedAiConfiguration {
        $moduleKey = $moduleKey !== null && $this->modules->exists($moduleKey) ? $moduleKey : null;

        // Step 0. The product module's OWN choice, made on its own AI Stack.
        //
        // Ahead of everything else because it is the most specific statement anybody has
        // made: "when Fees makes a conversational call, use this model". A module that has
        // chosen nothing has no row and falls straight through, so this step is invisible
        // on an estate where nobody has used it.
        $binding = $this->bindings->find($productModuleKey, $moduleKey, $subInstituteId);

        if ($binding !== null) {
            return $this->fromBinding($binding, $subInstituteId);
        }

        // Steps 1-2. Only a module row may choose the provider, because only a module
        // row was saved by someone who meant to choose one.
        $row = $this->findModuleRow($moduleKey, $subInstituteId);

        if ($row !== null) {
            $provider = $this->providerFromRow($row);

            return new ResolvedAiConfiguration(
                provider: $provider,
                model: $this->modelFor($provider, $row->model ?? null, $subInstituteId),
                apiKey: trim((string) $row->api_key),
                source: $row->source,
                keyId: $row->id ?? null,
                scope: ($row->sub_institute_id ?? null) === null ? 'platform' : 'institute',
                maxOutputTokens: $this->maxTokens($row),
            );
        }

        // Steps 3-6. Nothing was saved for this module, so the provider is the
        // configured driver and the credential is that driver's own — which is
        // precisely `ProviderKeyResolver`, delegated to rather than reimplemented so
        // the two cannot drift.
        //
        // Deriving the provider from whichever key happens to be newest would be
        // wrong, and was: on this estate the newest active row is a Gemini key while
        // `AI_PROVIDER` is openrouter, so inferring from the row would silently move
        // every unconfigured module onto a different provider than the one running
        // today. The driver decides; the key is then looked up for it.
        $provider = $this->defaultProvider();
        $key = $this->poolKey($provider, $subInstituteId);

        return new ResolvedAiConfiguration(
            provider: $provider,
            model: $this->modelFor($provider, null, $subInstituteId),
            apiKey: $key['api_key'] ?? null,
            source: $this->sourceFor($key),
            keyId: $key['id'] ?? null,
            scope: $key['scope'] ?? 'config',
            maxOutputTokens: $this->poolMaxTokens($key, $provider),
        );
    }

    /**
     * How a pool credential was found, in this class's vocabulary.
     *
     * `ProviderKeyResolver` reports `institute`, `platform` or `env`; the overview
     * screen and the logs speak in precedence steps, so they are mapped here rather
     * than leaking two vocabularies for one fact.
     */
    private function sourceFor(?array $key): string
    {
        return match ($key['scope'] ?? null) {
            'institute' => 'pool',
            'platform' => 'pool_platform',
            'env' => 'env',
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
     * What every module resolves to right now, for the admin screen's list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(int|string|null $subInstituteId = null): array
    {
        $out = [];

        foreach ($this->modules->all() as $module) {
            $config = $this->resolve($module['key'], $subInstituteId);

            $out[] = [
                'module' => $module['key'],
                'module_label' => $module['label'],
                'description' => $module['description'],
                'wired' => $module['wired'],
                'provider' => $config->provider,
                'provider_label' => $this->providers->label($config->provider),
                'model' => $config->model,
                'source' => $config->source,
                'scope' => $config->scope,
                'key_id' => $config->keyId,
                'has_key' => $config->hasKey(),
                'driveable' => $this->providers->isDriveable($config->provider),
            ];
        }

        return $out;
    }

    /**
     * The module's own row — this school's first, then the platform's.
     *
     * Two ordered queries rather than one clever one: the precedence is the part of
     * this class most likely to be read in an incident, and two named steps are worth
     * more then than a saved round trip.
     *
     * Returns null when the module column has not been migrated onto this estate, so
     * an un-migrated deployment simply never takes steps 1-2 and behaves as it always
     * has.
     */
    private function findModuleRow(?string $moduleKey, int|string|null $subInstituteId): ?object
    {
        if ($moduleKey === null || ! $this->schema->hasColumn('ai_api_keys', 'ai_module')) {
            return null;
        }

        $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);
        $institute = $institute === '' ? null : $institute;

        $rows = $this->moduleRows($institute);

        if ($rows === null) {
            // A key-table outage falls through to the env fallback rather than failing
            // the call outright — the same answer the per-module query gave before.
            return null;
        }

        // Precedence, unchanged: this school's own row first, then the platform's, and
        // within each the newest active row — the convention a rotated key expects, and
        // what stops a dead older key being picked.
        $attempts = [];

        if ($institute !== null) {
            $attempts[] = ['module', fn (object $row) => (string) ($row->sub_institute_id ?? '') === $institute];
        }

        $attempts[] = ['module_platform', fn (object $row) => ($row->sub_institute_id ?? null) === null];

        foreach ($attempts as [$source, $matches]) {
            foreach ($rows as $row) {
                if ((string) ($row->ai_module ?? '') !== $moduleKey || ! $matches($row)) {
                    continue;
                }

                if (empty($row->api_key) || trim((string) $row->api_key) === '-') {
                    // Keep looking within this scope, then fall through to the next one,
                    // exactly as the `limit 1` query did by returning a row the caller
                    // then rejected.
                    continue;
                }

                // Cloned before stamping: the row is shared with every other module
                // resolved from this cache, and writing `source` onto the original would
                // leak one module's precedence onto the next one's copy.
                $found = clone $row;
                $found->source = $source;

                return $found;
            }
        }

        return null;
    }

    /**
     * Every module-bound credential this institute can see, read once.
     *
     * `overview()` resolves fourteen modules, and each one used to run two `ai_api_keys`
     * queries of its own — twenty-eight round trips to a remote database for rows that
     * one query returns. The filter is deliberately wider than any single module's and
     * the choosing is done in PHP, so the precedence above stays the only place that
     * decides which row wins.
     *
     * Returns null — distinct from an empty list — when the table could not be read, so
     * the caller can tell "no module credential" from "no answer".
     *
     * @return array<int, object>|null
     */
    private function moduleRows(?string $institute): ?array
    {
        $cacheKey = $institute ?? '';

        if (array_key_exists($cacheKey, $this->moduleRowCache)) {
            return $this->moduleRowCache[$cacheKey];
        }

        try {
            $rows = DB::table('ai_api_keys')
                ->where('status', 1)
                ->whereNotNull('ai_module')
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
            return $this->moduleRowCache[$cacheKey] = null;
        }

        return $this->moduleRowCache[$cacheKey] = $rows;
    }

    /**
     * Which provider a credential row belongs to.
     *
     * A row saved by the admin screen carries a provider key in `api_type`. The rows
     * that predate it carry whatever convention was current when they were written —
     * `gemini` for one, `OPENROUTER_API_KEY` for another — so each catalogue entry's
     * declared `api_type` is matched before falling back to the active driver.
     */
    private function providerFromRow(object $row): string
    {
        $apiType = trim((string) ($row->api_type ?? ''));

        if ($apiType === '') {
            return $this->defaultProvider();
        }

        if ($this->providers->exists($apiType)) {
            return $apiType;
        }

        foreach ($this->providers->keys() as $provider) {
            if (strcasecmp($this->providers->apiType($provider), $apiType) === 0) {
                return $provider;
            }
        }

        return $this->defaultProvider();
    }

    /**
     * The shared pool's credential for a provider, resolved once per (provider, institute).
     *
     * Delegates to `ProviderKeyResolver` exactly as before — this only stops fourteen
     * identical lookups happening where one will do.
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
            $this->providers->envKey($provider),
        );
    }

    /** The saved model, else the catalogue's first for this provider, else config's. */
    private function modelFor(string $provider, ?string $saved, int|string|null $subInstituteId): ?string
    {
        $saved = trim((string) $saved);

        if ($saved !== '') {
            return $saved;
        }

        $cacheKey = $provider . '|' . ($subInstituteId === null ? '' : (string) $subInstituteId);

        if (! array_key_exists($cacheKey, $this->defaultModelCache)) {
            $this->defaultModelCache[$cacheKey] = $this->models->defaultFor($provider, $subInstituteId);
        }

        return $this->defaultModelCache[$cacheKey] ?? $this->providers->defaultModel($provider);
    }

    /**
     * `api_limit` has always doubled as the per-key output ceiling — `OpenRouterClient`
     * reads it that way — so it keeps that meaning here rather than gaining a column.
     */
    private function maxTokens(object $row): ?int
    {
        $limit = $row->api_limit ?? null;

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    private function defaultProvider(): string
    {
        $driver = trim((string) config('ai.provider.driver', 'gemini'));

        return $this->providers->exists($driver) ? $driver : 'gemini';
    }
}
