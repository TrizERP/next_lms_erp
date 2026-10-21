<?php

namespace App\Domain\AI\Configuration;

use App\Domain\AI\Support\SchemaCache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Which providers and models can really serve a module, ranked.
 *
 * THE QUESTION, AND WHAT ANSWERS IT
 *
 *   Can the provider do the job?    `ProviderCapabilities` — read off the client class — against
 *                                   the module's declared needs (`AiModuleRegistry::requires`).
 *   Is there a key, and is it good? `CredentialInspector` — the provider is asked.
 *   Does the model exist, and does  `ProviderModelDiscovery` — the provider's own model list: its
 *   it fit?                         context window, output ceiling, modality, structured-output
 *                                   support — falling back to the catalogue's recorded ceiling.
 *
 * Nothing here names a provider or a model. A provider that gains a capability, or a model the
 * vendor adds or retires, changes this screen's answer with no edit to it.
 *
 * WHAT IS NOT KNOWN IS SAID
 *
 * When there is no usable key the provider's model list cannot be read, so its models are judged
 * on the catalogue alone and marked `verified: false`. They are not called unavailable, because
 * the catalogue is all that is known — and they are not called verified either.
 *
 * RANKING IS PER MODULE
 *
 * Providers the administrator has already saved for the module come first, in their saved order.
 * The rest are ordered by `ProviderFitRanker` — the module's own proven providers first, then those
 * with a model that fits, then the catalogue order — which is the same function the runtime uses to
 * order a module that has nothing saved. A stored key never moves a provider up: it only decides
 * whether the provider is READY (`credential.status`), reported beside it. Providers that cannot
 * serve the module come last, marked `not_capable`, and cannot be selected.
 */
final class ProviderCompatibility
{
    public function __construct(
        private readonly AiModuleRegistry $modules,
        private readonly ProviderCatalog $providers,
        private readonly ProviderCapabilities $capabilities,
        private readonly ModelCatalog $models,
        private readonly ProviderModelDiscovery $discovery,
        private readonly CredentialInspector $credentials,
        private readonly AiConfigurationResolver $resolver,
        private readonly SchemaCache $schema,
        private readonly ProviderFitRanker $ranker,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forModule(string $moduleKey, int|string|null $subInstituteId = null, bool $live = true): array
    {
        $requires = $this->modules->requires($moduleKey);
        $minOutput = $this->modules->minOutputTokens($moduleKey);
        $configured = $this->configuredRows($moduleKey, $subInstituteId);

        // Capable providers, ordered for THIS module. Not-capable ones keep the catalogue order after them.
        $capable = array_values(array_filter(
            $this->providers->keys(),
            fn (string $p) => $this->providers->isDriveable($p) && $this->capabilities->missing($p, $requires) === []
        ));
        $fit = array_flip($this->ranker->order($moduleKey, $capable, $subInstituteId));

        $out = [];

        // Only the providers this module lists — in its own order. Others are not shown at all.
        $listed = array_values(array_filter(
            $this->modules->offered($moduleKey),
            fn (string $p) => $this->providers->exists($p)
        ));

        foreach ($listed as $order => $provider) {
            $out[] = $this->evaluate($provider, $order, $fit[$provider] ?? null, $moduleKey, $requires, $minOutput, $configured, $subInstituteId, $live);
        }

        usort($out, fn (array $a, array $b) => [$a['_sort']] <=> [$b['_sort']]);

        $rank = 0;

        foreach ($out as $i => $row) {
            unset($out[$i]['_sort']);
            $out[$i]['rank'] = $row['capable'] ? ++$rank : null;
        }

        return [
            'module' => $moduleKey,
            'module_label' => $this->modules->label($moduleKey),
            'runtime' => $this->modules->runtime($moduleKey),
            'gap' => $this->modules->find($moduleKey)['gap'] ?? null,
            'requires' => array_map(fn (string $c) => ['key' => $c, 'label' => $this->capabilities->label($c)], $requires),
            'min_output_tokens' => $minOutput,
            'priority_supported' => $this->resolver->supportsPriority(),
            'providers' => array_values($out),
        ];
    }

    /**
     * Whether one provider/model may be saved for a module — the same rules the screen shows,
     * applied at Save so a hand-built request cannot do what the form would have refused.
     *
     * @return array<int, array{field:string, message:string}>  What is refused, and on which field;
     *         empty means it is acceptable.
     */
    public function refusals(string $moduleKey, string $provider, ?string $model, int|string|null $subInstituteId = null): array
    {
        $requires = $this->modules->requires($moduleKey);
        $minOutput = $this->modules->minOutputTokens($moduleKey);
        $out = [];

        if (! $this->modules->isOffered($moduleKey, $provider)) {
            return [['field' => 'provider', 'message' => sprintf('%s is not one of the providers offered for %s.', $this->providers->label($provider), $this->modules->label($moduleKey))]];
        }

        if (! $this->providers->isDriveable($provider)) {
            return [['field' => 'provider', 'message' => sprintf('%s is not callable from this platform yet.', $this->providers->label($provider))]];
        }

        $missing = $this->capabilities->missing($provider, $requires);

        if ($missing !== []) {
            return [['field' => 'provider', 'message' => sprintf(
                '%s is not capable of serving %s: it lacks %s.',
                $this->providers->label($provider),
                $this->modules->label($moduleKey),
                $this->capabilities->describe($missing)
            )]];
        }

        if ($model !== null) {
            $catalogued = null;

            foreach ($this->models->forProvider($provider, $subInstituteId) as $row) {
                if ($row['model_id'] === $model) {
                    $catalogued = $row;
                    break;
                }
            }

            if ($catalogued === null) {
                $out[] = ['field' => 'model', 'message' => sprintf('%s is not a %s model in the catalogue.', $model, $this->providers->label($provider))];
            } else {
                foreach ($this->modelReasons($catalogued, null, $requires, $minOutput) as $reason) {
                    $out[] = ['field' => 'model', 'message' => $reason];
                }
            }
        }

        return $out;
    }
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, string>  $requires
     * @param  array<string, array<string, mixed>>  $configured  keyed by provider
     * @return array<string, mixed>
     */
    private function evaluate(
        string $provider,
        int $order,
        ?int $fitAt,
        string $moduleKey,
        array $requires,
        ?int $minOutput,
        array $configured,
        int|string|null $subInstituteId,
        bool $live
    ): array {
        $driveable = $this->providers->isDriveable($provider);
        $missing = $driveable ? $this->capabilities->missing($provider, $requires) : $requires;
        $capable = $driveable && $missing === [];

        $reasons = [];

        if (! $driveable) {
            $reasons[] = 'This platform has no client that can call this provider yet.';
        } elseif ($missing !== []) {
            $reasons[] = sprintf('Does not support %s, which this module needs.', $this->capabilities->describe($missing));
        }

        $own = $configured[$provider] ?? null;
        $apiKey = $own['api_key'] ?? $this->poolKeyFor($provider, $subInstituteId);
        $credential = $this->credentials->inspect($provider, $apiKey, $live && $capable);

        $discovered = null;

        if ($capable && $apiKey !== null && $apiKey !== '' && $credential['status'] !== 'invalid' && $live) {
            $listing = $this->discovery->models($provider, $apiKey);
            $discovered = $listing['status'] === 'ok' ? $listing['models'] : null;
        }

        $models = $capable ? $this->evaluateModels($provider, $requires, $minOutput, $discovered, $subInstituteId) : [];

        if ($capable && $models === []) {
            $reasons[] = 'There are no models in the catalogue for this provider. Add one in Model Management.';
        } elseif ($capable && ! array_filter($models, fn (array $m) => $m['compatible'])) {
            $reasons[] = 'None of its catalogued models fit what this module needs.';
        }

        $selectable = $capable;
        $proven = $capable && $this->ranker->isProven($moduleKey, $provider);
        $status = ! $capable ? 'not_capable' : (isset($own) ? 'configured' : ($fitAt === 0 ? 'best_fit' : 'compatible'));

        // Saved providers first (in their saved order), then the rest in this module's fit order. A key
        // being stored is deliberately absent from this: it is reported, not ranked.
        $sort = match (true) {
            ! $capable => 9_000_000 + $order,
            isset($own) => ($own['priority'] ?? 99_000) * 100 + $order,
            default => 1_000_000 + ($fitAt ?? 0),
        };

        $fit = match (true) {
            ! $capable => null,
            $fitAt === 0 => 'First choice for this module.',
            default => 'Fallback for this module, in its listed order.',
        };

        return [
            '_sort' => $sort,
            'provider' => $provider,
            'label' => $this->providers->label($provider),
            'capable' => $capable,
            'selectable' => $selectable,
            'status' => $status,
            'proven' => $proven,
            'fit' => $fit,
            'ready' => $capable && $credential['status'] === 'ready',
            'reasons' => $reasons,
            'capabilities' => $this->capabilities->forProvider($provider),
            'credential' => $credential,
            'configured' => isset($own) ? [
                'id' => $own['id'],
                'priority' => $own['priority'],
                'model' => $own['model'],
                'scope' => $own['scope'],
            ] : null,
            'models' => $models,
            'docs' => collect($this->providers->all())->firstWhere('key', $provider)['docs'] ?? null,
        ];
    }

    /**
     * @param  array<int, string>  $requires
     * @param  array<int, array<string, mixed>>|null  $discovered
     * @return array<int, array<string, mixed>>
     */
    private function evaluateModels(string $provider, array $requires, ?int $minOutput, ?array $discovered, int|string|null $subInstituteId): array
    {
        $byId = [];

        foreach ($discovered ?? [] as $row) {
            $byId[$row['id']] = $row;
        }

        $out = [];

        foreach ($this->models->forProvider($provider, $subInstituteId) as $i => $model) {
            $found = $byId[$model['model_id']] ?? null;
            $reasons = $this->modelReasons($model, $found, $requires, $minOutput);

            if ($discovered !== null && $found === null) {
                $reasons[] = 'The provider does not list this model for the stored key.';
            }

            $out[] = [
                'id' => $model['id'],
                'model_id' => $model['model_id'],
                'label' => $model['label'],
                'compatible' => $reasons === [],
                // True only when the provider itself confirmed the model — not merely when the catalogue has it.
                'verified' => $found !== null,
                'reasons' => $reasons,
                'max_output_tokens' => $found['max_output_tokens'] ?? $model['max_output_tokens'],
                'context_tokens' => $found['context_tokens'] ?? null,
                '_order' => $i,
            ];
        }

        usort($out, fn (array $a, array $b) => [! $a['compatible'], ! $a['verified'], $a['_order']] <=> [! $b['compatible'], ! $b['verified'], $b['_order']]);

        foreach ($out as $i => $row) {
            unset($out[$i]['_order']);
        }

        return array_values($out);
    }

    /**
     * Why a model does not fit — empty when it does.
     *
     * @param  array<string, mixed>  $catalogued
     * @param  array<string, mixed>|null  $found  The provider's own record of the model, when read.
     * @param  array<int, string>  $requires
     * @return array<int, string>
     */
    private function modelReasons(array $catalogued, ?array $found, array $requires, ?int $minOutput): array
    {
        $reasons = [];
        $ceiling = $found['max_output_tokens'] ?? $catalogued['max_output_tokens'] ?? null;

        if ($minOutput !== null && $ceiling !== null && $ceiling < $minOutput) {
            $reasons[] = sprintf('Output limit of %s tokens is below the %s this module needs.', number_format($ceiling), number_format($minOutput));
        }

        if ($found !== null) {
            $wantsText = array_intersect($requires, ['chat', 'json']) !== [];

            if ($wantsText && ($found['chat'] ?? true) === false) {
                $reasons[] = 'This is not a text-generation model.';
            }

            if (in_array('json', $requires, true) && ($found['structured_output'] ?? null) === false) {
                $reasons[] = 'The provider reports no structured-output support for this model.';
            }

            if (in_array('vision', $requires, true) && ($found['vision_input'] ?? null) === false) {
                $reasons[] = 'This model does not accept images or documents.';
            }

            if (in_array('image_generation', $requires, true) && ($found['image_output'] ?? null) === false) {
                $reasons[] = 'This model does not generate images.';
            }

            if (in_array('embedding', $requires, true) && ($found['embedding'] ?? null) === false) {
                $reasons[] = 'This is not an embedding model.';
            }
        }

        return $reasons;
    }

    /**
     * The module's own active rows, keyed by provider — this school's first, then the platform's.
     *
     * @return array<string, array<string, mixed>>
     */
    private function configuredRows(string $moduleKey, int|string|null $subInstituteId): array
    {
        if (! $this->schema->hasColumn('ai_api_keys', 'ai_module')) {
            return [];
        }

        $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);
        $hasPriority = $this->schema->hasColumn('ai_api_keys', 'priority');

        try {
            $rows = DB::table('ai_api_keys')
                ->where('ai_module', $moduleKey)
                ->where('status', 1)
                ->where(function ($q) use ($institute) {
                    $q->whereNull('sub_institute_id');

                    if ($institute !== null && $institute !== '') {
                        $q->orWhere('sub_institute_id', $institute);
                    }
                })
                ->orderByRaw('sub_institute_id IS NULL ASC')
                ->orderByDesc('id')
                ->get();
        } catch (Throwable) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $provider = $this->resolver->providerFromRow($row);

            if ($provider === null || isset($out[$provider])) {
                continue;
            }

            $out[$provider] = [
                'id' => (int) $row->id,
                'api_key' => trim((string) ($row->api_key ?? '')),
                'model' => $row->model ?? null,
                'priority' => $hasPriority && is_numeric($row->priority ?? null) && (int) $row->priority > 0 ? (int) $row->priority : null,
                'scope' => ($row->sub_institute_id ?? null) === null ? 'platform' : 'institute',
            ];
        }

        return $out;
    }

    /** The shared pool's key for a provider, for judging a provider nobody has configured this module for. */
    private function poolKeyFor(string $provider, int|string|null $subInstituteId): ?string
    {
        try {
            $key = app(\App\Domain\AI\Support\ProviderKeyResolver::class)->resolve(
                $this->providers->apiType($provider),
                $subInstituteId,
                null
            );
        } catch (Throwable) {
            return null;
        }

        return $key['api_key'] ?? null;
    }
}
