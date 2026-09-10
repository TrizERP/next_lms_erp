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
    ) {
    }

    /**
     * Resolve the provider, model and credential for one module.
     *
     * @param  string|null  $moduleKey  A key from `AiModuleRegistry`, or null for the
     *                                  unbound pool behaviour every legacy caller has.
     */
    public function resolve(?string $moduleKey, int|string|null $subInstituteId = null): ResolvedAiConfiguration
    {
        $moduleKey = $moduleKey !== null && $this->modules->exists($moduleKey) ? $moduleKey : null;

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

        $key = $this->keys->resolve(
            $this->providers->apiType($provider),
            $subInstituteId,
            $this->providers->envKey($provider),
        );

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
        if ($moduleKey === null || ! Schema::hasTable('ai_api_keys') || ! Schema::hasColumn('ai_api_keys', 'ai_module')) {
            return null;
        }

        $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);
        $institute = $institute === '' ? null : $institute;

        $attempts = [];

        if ($institute !== null) {
            $attempts[] = ['module', fn ($q) => $q->where('ai_module', $moduleKey)->where('sub_institute_id', $institute)];
        }

        $attempts[] = ['module_platform', fn ($q) => $q->where('ai_module', $moduleKey)->whereNull('sub_institute_id')];

        foreach ($attempts as [$source, $filter]) {
            try {
                $query = DB::table('ai_api_keys')->where('status', 1);
                $filter($query);

                // Newest active row wins, matching ProviderKeyResolver: the convention
                // a rotated key expects, and what stops a dead older key being picked.
                $row = $query->orderByDesc('id')->first();
            } catch (Throwable) {
                // A key-table outage falls through to the env fallback rather than
                // failing the call outright.
                return null;
            }

            if ($row === null || empty($row->api_key) || trim((string) $row->api_key) === '-') {
                continue;
            }

            $row->source = $source;

            return $row;
        }

        return null;
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

    /** The saved model, else the catalogue's first for this provider, else config's. */
    private function modelFor(string $provider, ?string $saved, int|string|null $subInstituteId): ?string
    {
        $saved = trim((string) $saved);

        if ($saved !== '') {
            return $saved;
        }

        return $this->models->defaultFor($provider, $subInstituteId)
            ?? $this->providers->defaultModel($provider);
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
