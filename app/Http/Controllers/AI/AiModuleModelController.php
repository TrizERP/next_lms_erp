<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\AI\Configuration\AiConfigurationResolver;
use App\Domain\AI\Configuration\AiModuleRegistry;
use App\Domain\AI\Configuration\ModelCatalog;
use App\Domain\AI\Configuration\ModuleModelBindings;
use App\Domain\AI\Configuration\ProviderCatalog;
use App\Domain\AI\Templates\TemplateModuleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * A product module's own model configuration, managed inside that module.
 *
 * WHY THIS EXISTS SEPARATELY FROM THE CENTRAL CONSOLE
 *
 * The AI Stack inside a module is decentralised. Opening Fees → AI Stack → Models and
 * choosing a model has to be a thing you do in Fees — it must not send you to AI &
 * Intelligence, and what you choose must apply to Fees and to nothing else.
 *
 * So this controller reads and writes `ai_module_model_bindings`, which is keyed on the
 * PRODUCT module. The central console keeps writing `ai_api_keys`, which is keyed on the
 * AI CAPABILITY, and the two never touch the same row. A module with no binding inherits
 * whatever the centre decided; a module with one overrides it for itself.
 *
 * WHAT A SAVE ACTUALLY CHANGES
 *
 * `AiConfigurationResolver::resolve()` consults these bindings before anything else, and
 * the three places that build a model client — the conversational fallback, the planner
 * and the generation service — all pass the product module through. So a save here changes
 * what the next call in that module does, not merely what a screen displays.
 *
 * SCOPE
 *
 * Everything is written against the caller's own `sub_institute_id`. A school configuring
 * its Fees module cannot change another school's, and cannot change the platform default
 * either — that remains the central console's to set.
 */
class AiModuleModelController extends AiController
{
    public function __construct(
        private readonly ModuleModelBindings $bindings,
        private readonly AiModuleRegistry $capabilities,
        private readonly ProviderCatalog $providers,
        private readonly ModelCatalog $models,
        private readonly AiConfigurationResolver $resolver,
        private readonly TemplateModuleCatalog $modules,
        private readonly AiAuditLogger $audit,
    ) {
    }

    /**
     * What this module's AI currently runs on, and what it could run on.
     *
     * One row per capability the module actually uses — taken from the module's own
     * capability flags, because offering a module a choice about a capability it never
     * invokes is a setting that does nothing.
     */
    public function index(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! $this->modules->exists($module, $institute)) {
                return $this->failure('That module is not one this school has.', 404);
            }

            $saved = $this->bindings->forModule($module, $institute);

            $rows = [];

            foreach ($this->capabilitiesFor($module, $institute) as $capability) {
                $key = $capability['key'];
                $binding = $saved[$key] ?? null;

                // What the next call in this module would ACTUALLY use, resolved through
                // the same code the call itself runs. Not a restatement of the row: if a
                // binding names a provider with no usable credential, this shows what the
                // fallback gave it.
                $effective = $this->resolver->resolve($key, $institute, $module);

                $rows[] = [
                    'capability' => $key,
                    'label' => $capability['label'],
                    'description' => $capability['description'],
                    // False means the platform reaches its provider its own way and a
                    // choice here is stored but not yet read. Said plainly rather than
                    // implying a binding that does not exist.
                    'wired' => $capability['wired'],
                    'binding' => $binding === null ? null : [
                        'provider' => $binding->provider,
                        'model' => $binding->model,
                        'api_key_id' => $binding->api_key_id === null ? null : (int) $binding->api_key_id,
                        'max_output_tokens' => $binding->max_output_tokens === null ? null : (int) $binding->max_output_tokens,
                        'scope' => ($binding->sub_institute_id ?? null) === null ? 'platform' : 'institute',
                        // A platform row is the estate's default for this module and is
                        // not this school's to edit — the screen shows it and offers to
                        // override it rather than to change it.
                        'editable' => ($binding->sub_institute_id ?? null) !== null,
                        'updated_at' => $binding->updated_at ?? null,
                    ],
                    'effective' => [
                        'provider' => $effective->provider,
                        'provider_label' => $this->providers->label($effective->provider),
                        'model' => $effective->model,
                        'source' => $effective->source,
                        'scope' => $effective->scope,
                        'has_credential' => $effective->apiKey !== null && $effective->apiKey !== '',
                        'max_output_tokens' => $effective->maxOutputTokens,
                    ],
                ];
            }

            return $this->success('Module model configuration resolved.', [
                'module' => ['key' => $module],
                'rows' => $rows,
                'providers' => $this->providerOptions($institute),
                // Credentials this school may point a binding at. The key itself is never
                // returned — only enough to choose one.
                'credentials' => $this->credentialOptions($institute),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Save this module's choice for one capability. */
    public function update(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! $this->modules->exists($module, $institute)) {
                return $this->failure('That module is not one this school has.', 404);
            }

            $data = $request->validate([
                'capability' => ['required', 'string', 'max:100'],
                'provider' => ['required', 'string', 'max:60'],
                'model' => ['nullable', 'string', 'max:190'],
                'api_key_id' => ['nullable', 'integer'],
                'max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:200000'],
                'status' => ['nullable', 'boolean'],
            ]);

            $capability = (string) $data['capability'];

            if (! $this->capabilities->exists($capability)) {
                return $this->failure('That is not an AI capability this platform has.', 422);
            }

            if (! $this->appliesTo($module, $capability, $institute)) {
                return $this->failure(
                    'This module does not use that capability, so configuring it would change nothing.',
                    422
                );
            }

            if (! $this->providers->exists($data['provider'])) {
                return $this->failure('That is not a provider this platform can call.', 422);
            }

            // A credential must be one this school can actually see. Without this, an id
            // typed into the payload would bind another school's key.
            if (($data['api_key_id'] ?? null) !== null && ! $this->credentialVisible((int) $data['api_key_id'], $institute)) {
                return $this->failure('That credential is not one this school can use.', 422);
            }

            $saved = $this->bindings->save($module, $capability, $institute, $data, $scope->userId);

            if ($saved === null) {
                return $this->failure('Module model bindings are not available on this estate.', 503);
            }

            $effective = $this->resolver->resolve($capability, $institute, $module);

            return $this->success('This module will use that model.', [
                'capability' => $capability,
                'binding' => [
                    'provider' => $saved->provider,
                    'model' => $saved->model,
                    'api_key_id' => $saved->api_key_id === null ? null : (int) $saved->api_key_id,
                    'max_output_tokens' => $saved->max_output_tokens === null ? null : (int) $saved->max_output_tokens,
                    'scope' => 'institute',
                    'editable' => true,
                    'updated_at' => $saved->updated_at ?? null,
                ],
                'effective' => [
                    'provider' => $effective->provider,
                    'provider_label' => $this->providers->label($effective->provider),
                    'model' => $effective->model,
                    'source' => $effective->source,
                    'scope' => $effective->scope,
                    'has_credential' => $effective->apiKey !== null && $effective->apiKey !== '',
                    'max_output_tokens' => $effective->maxOutputTokens,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Return this module to whatever the estate's configuration decides. */
    public function destroy(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! $this->modules->exists($module, $institute)) {
                return $this->failure('That module is not one this school has.', 404);
            }

            $capability = trim((string) $request->query('capability', $request->input('capability', '')));

            if ($capability === '' || ! $this->capabilities->exists($capability)) {
                return $this->failure('Name the capability to clear.', 422);
            }

            // Only this school's own row. A platform default is the central console's and
            // clearing it here would change every other school's module too.
            $cleared = $this->bindings->clear($module, $capability, $institute);

            $effective = $this->resolver->resolve($capability, $institute, $module);

            return $this->success(
                $cleared
                    ? 'This module is back on the configuration the rest of the estate uses.'
                    : 'This module had no choice of its own to clear.',
                [
                    'capability' => $capability,
                    'cleared' => $cleared,
                    'effective' => [
                        'provider' => $effective->provider,
                        'provider_label' => $this->providers->label($effective->provider),
                        'model' => $effective->model,
                        'source' => $effective->source,
                        'scope' => $effective->scope,
                        'has_credential' => $effective->apiKey !== null && $effective->apiKey !== '',
                        'max_output_tokens' => $effective->maxOutputTokens,
                    ],
                ]
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Add a new credential — a provider, a model and an API key — from inside this
     * module, and immediately use it here.
     *
     * WHY THIS EXISTS ALONGSIDE THE CENTRAL CONSOLE'S OWN "ADD CONFIGURATION"
     *
     * `AiConfigurationController::store()` under AI & Intelligence does the same
     * underlying write — `ai_api_keys` is one estate-wide table regardless of which
     * screen adds to it, the same way an API key is inherently an estate resource no
     * matter which screen asks for one. What decentralised means here is that a person
     * never has to LEAVE the module to get one: the row this creates is tagged
     * `ai_module` = the CAPABILITY being configured (`conversational_ai`, `generative_ai`
     * or `agent_reasoning` — the same vocabulary the central console uses for that
     * column) and `sub_institute_id` = this caller's own school, never the platform. A
     * module can add its OWN credential; it can never add or touch the estate default.
     *
     * A model name outside the existing catalogue is added to it (institute-scoped, the
     * same as `AiConfigurationController::storeModel()`), because refusing to save a
     * model nobody has typed before is the placeholder-only failure this feature exists
     * to remove.
     *
     * After the credential exists, this module's binding for the capability is saved to
     * point at it in the same call — "add" and "use it here" are one action, not two.
     */
    public function storeCredential(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! $this->modules->exists($module, $institute)) {
                return $this->failure('That module is not one this school has.', 404);
            }

            if (! Schema::hasTable('ai_api_keys')) {
                return $this->failure('AI credentials are not available on this estate.', 503);
            }

            $data = $request->validate([
                'capability' => ['required', 'string', 'max:100'],
                'provider' => ['required', 'string', Rule::in($this->providers->keys())],
                'model' => ['required', 'string', 'max:120'],
                'model_label' => ['nullable', 'string', 'max:120'],
                'api_key' => ['required', 'string', 'min:8', 'max:4096'],
                'account_email' => ['nullable', 'email', 'max:191'],
                'api_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
                'max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:200000'],
            ]);

            $capability = (string) $data['capability'];

            if (! $this->capabilities->exists($capability)) {
                return $this->failure('That is not an AI capability this platform has.', 422);
            }

            if (! $this->appliesTo($module, $capability, $institute)) {
                return $this->failure(
                    'This module does not use that capability, so adding a model for it would change nothing.',
                    422
                );
            }

            $provider = $data['provider'];

            if (! $this->providers->isDriveable($provider)) {
                return $this->failure(
                    sprintf('%s is not callable from this platform yet.', $this->providers->label($provider)),
                    422
                );
            }

            $model = trim($data['model']);
            $apiType = $this->providers->apiType($provider);

            $modelId = $this->ensureModelCatalogued($provider, $model, $data['model_label'] ?? null, $institute, $scope);

            $existing = DB::table('ai_api_keys')
                ->where('ai_module', $capability)
                ->where('api_type', $apiType)
                ->where('sub_institute_id', $institute)
                ->first();

            if ($existing !== null) {
                return $this->failure(
                    'This module already has a credential of its own for that provider. Edit it instead of adding another.',
                    409,
                    ['id' => $existing->id]
                );
            }

            $credentialId = DB::table('ai_api_keys')->insertGetId([
                'ai_module' => $capability,
                'api_type' => $apiType,
                'model' => $model,
                'api_key' => trim($data['api_key']),
                'account_email' => $data['account_email'] ?? null,
                'api_limit' => isset($data['api_limit']) ? (string) $data['api_limit'] : null,
                'status' => 1,
                'sub_institute_id' => $institute,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record('ai.module_model.credential_created', $scope, [
                'subject_entity_key' => 'ai_module',
                'subject_id' => null,
                'related_type' => 'ai_api_keys',
                'related_id' => $credentialId,
                'message' => sprintf(
                    '%s added a %s credential for %s in %s.',
                    $scope->userId !== null ? 'A user' : 'The system',
                    $this->providers->label($provider),
                    $capability,
                    $module
                ),
            ]);

            // Use it here, immediately — the whole point of adding it from inside the
            // module rather than the central console.
            $saved = $this->bindings->save($module, $capability, $institute, [
                'provider' => $provider,
                'model' => $model,
                'api_key_id' => $credentialId,
                'max_output_tokens' => $data['max_output_tokens'] ?? null,
                'status' => 1,
            ], $scope->userId);

            $effective = $this->resolver->resolve($capability, $institute, $module);

            return $this->success('Model added and this module will use it.', [
                'capability' => $capability,
                'credential' => [
                    'id' => (int) $credentialId,
                    'provider' => $provider,
                    'label' => trim((string) ($data['account_email'] ?? '')) ?: ('Credential #'.$credentialId),
                    'daily_limit' => isset($data['api_limit']) ? (int) $data['api_limit'] : null,
                    'scope' => 'institute',
                ],
                'model_id' => $modelId,
                'binding' => $saved === null ? null : [
                    'provider' => $saved->provider,
                    'model' => $saved->model,
                    'api_key_id' => $saved->api_key_id === null ? null : (int) $saved->api_key_id,
                    'max_output_tokens' => $saved->max_output_tokens === null ? null : (int) $saved->max_output_tokens,
                    'scope' => 'institute',
                    'editable' => true,
                    'updated_at' => $saved->updated_at ?? null,
                ],
                'effective' => [
                    'provider' => $effective->provider,
                    'provider_label' => $this->providers->label($effective->provider),
                    'model' => $effective->model,
                    'source' => $effective->source,
                    'scope' => $effective->scope,
                    'has_credential' => $effective->apiKey !== null && $effective->apiKey !== '',
                    'max_output_tokens' => $effective->maxOutputTokens,
                ],
            ], 201);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Edit or enable/disable a credential this module added — never a platform row.
     *
     * The key is optional, the same as the central console: an administrator changing
     * the model or the limit should not have to re-enter a secret they cannot read back.
     */
    public function updateCredential(Request $request, string $module, int $credential)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! $this->modules->exists($module, $institute)) {
                return $this->failure('That module is not one this school has.', 404);
            }

            if (! Schema::hasTable('ai_api_keys')) {
                return $this->failure('AI credentials are not available on this estate.', 503);
            }

            $row = DB::table('ai_api_keys')->where('id', $credential)->first();

            if ($row === null) {
                return $this->failure('That credential was not found.', 404);
            }

            if (($row->sub_institute_id ?? null) === null) {
                return $this->failure(
                    'This credential is part of the shared platform configuration and cannot be edited from a module.',
                    403
                );
            }

            if ((string) $row->sub_institute_id !== (string) $institute) {
                return $this->failure('That credential was not found.', 404);
            }

            $data = $request->validate([
                'model' => ['nullable', 'string', 'max:120'],
                'model_label' => ['nullable', 'string', 'max:120'],
                'api_key' => ['nullable', 'string', 'min:8', 'max:4096'],
                'account_email' => ['nullable', 'email', 'max:191'],
                'api_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
                'status' => ['nullable', 'integer', Rule::in([0, 1])],
            ]);

            $update = ['updated_at' => now()];

            if (array_key_exists('model', $data) && trim((string) $data['model']) !== '') {
                $model = trim($data['model']);
                $this->ensureModelCatalogued((string) $row->api_type, $model, $data['model_label'] ?? null, $institute, $scope);
                $update['model'] = $model;
            }

            if (array_key_exists('account_email', $data)) {
                $update['account_email'] = $data['account_email'];
            }

            if (array_key_exists('api_limit', $data)) {
                $update['api_limit'] = $data['api_limit'] === null ? null : (string) $data['api_limit'];
            }

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $update['status'] = (int) $data['status'];
            }

            $key = $data['api_key'] ?? null;

            if (is_string($key) && trim($key) !== '') {
                $update['api_key'] = trim($key);
            }

            DB::table('ai_api_keys')->where('id', $credential)->update($update);

            $this->audit->record('ai.module_model.credential_updated', $scope, [
                'related_type' => 'ai_api_keys',
                'related_id' => $credential,
                'message' => sprintf('A credential for %s was updated in %s.', $row->ai_module, $module),
            ]);

            $effective = $this->resolver->resolve((string) $row->ai_module, $institute, $module);

            return $this->success('Credential updated.', [
                'capability' => (string) $row->ai_module,
                'credential' => [
                    'id' => (int) $credential,
                    'provider' => (string) $row->api_type,
                    'label' => trim((string) ($update['account_email'] ?? $row->account_email ?? '')) ?: ('Credential #'.$credential),
                    'daily_limit' => isset($update['api_limit'])
                        ? ($update['api_limit'] === null ? null : (int) $update['api_limit'])
                        : ($row->api_limit === null ? null : (int) $row->api_limit),
                    'status' => $update['status'] ?? (int) $row->status,
                    'scope' => 'institute',
                ],
                'effective' => [
                    'provider' => $effective->provider,
                    'provider_label' => $this->providers->label($effective->provider),
                    'model' => $effective->model,
                    'source' => $effective->source,
                    'scope' => $effective->scope,
                    'has_credential' => $effective->apiKey !== null && $effective->apiKey !== '',
                    'max_output_tokens' => $effective->maxOutputTokens,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    // ------------------------------------------------------------------ internals

    /**
     * Add a model to the institute-scoped catalogue if it is not there yet, the same way
     * `AiConfigurationController::storeModel()` does for the central console. Returns the
     * catalogue row's id whether it already existed or was just created.
     */
    private function ensureModelCatalogued(
        string $provider,
        string $modelId,
        ?string $label,
        int|string|null $institute,
        $scope
    ): ?int {
        if (! Schema::hasTable('ai_models')) {
            return null;
        }

        if ($this->models->offers($provider, $modelId, $institute)) {
            return DB::table('ai_models')
                ->where('provider', $provider)
                ->where('model_id', $modelId)
                ->where(fn ($query) => $query->whereNull('sub_institute_id')->orWhere('sub_institute_id', $institute))
                ->value('id');
        }

        $id = DB::table('ai_models')->insertGetId([
            'provider' => $provider,
            'model_id' => $modelId,
            'label' => trim((string) $label) !== '' ? trim((string) $label) : $modelId,
            'max_output_tokens' => null,
            'input_cost_per_1k' => null,
            'output_cost_per_1k' => null,
            'sort_order' => 0,
            'status' => 1,
            'sub_institute_id' => $institute,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record('ai.model.created', $scope, [
            'related_type' => 'ai_models',
            'related_id' => $id,
            'message' => sprintf('Model %s added for %s from a module\'s AI Stack.', $modelId, $provider),
        ]);

        return (int) $id;
    }

    /**
     * The AI capabilities this module actually uses.
     *
     * Read from the module's own `ai_modules.capabilities` flags and mapped onto the
     * capability registry. A module with `generative` off is never offered a generative
     * model, because configuring one would be a setting that does nothing — the same
     * reasoning that keeps unwired capabilities labelled rather than hidden.
     *
     * @return array<int, array<string, mixed>>
     */
    private function capabilitiesFor(string $module, int|string|null $institute): array
    {
        $flags = $this->moduleFlags($module, $institute);

        $lanes = [
            'conversational' => 'conversational_ai',
            'generative' => 'generative_ai',
            'agent' => 'agent_reasoning',
        ];

        $rows = [];

        foreach ($lanes as $flag => $capability) {
            if (empty($flags[$flag])) {
                continue;
            }

            $definition = $this->capabilities->find($capability);

            if ($definition === null) {
                continue;
            }

            $rows[] = $definition;
        }

        return $rows;
    }

    private function appliesTo(string $module, string $capability, int|string|null $institute): bool
    {
        foreach ($this->capabilitiesFor($module, $institute) as $row) {
            if ($row['key'] === $capability) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, bool> */
    private function moduleFlags(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_modules')) {
            return [];
        }

        $row = DB::table('ai_modules')
            ->where('module_key', $module)
            ->where(fn ($query) => $query->where('sub_institute_id', $institute)->orWhereNull('sub_institute_id'))
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->value('capabilities');

        $decoded = json_decode((string) $row, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Providers this platform can actually call, with the models known for each.
     *
     * @return array<int, array<string, mixed>>
     */
    private function providerOptions(int|string|null $institute): array
    {
        $options = [];

        foreach ($this->providers->keys() as $provider) {
            if (! $this->providers->isDriveable($provider)) {
                // A provider with no client cannot be chosen, so it is not offered. The
                // central console still lists it, because that is where one is set up.
                continue;
            }

            $options[] = [
                'key' => $provider,
                'label' => $this->providers->label($provider),
                'default_model' => $this->models->defaultFor($provider, $institute) ?? $this->providers->defaultModel($provider),
                'models' => $this->modelOptions($provider, $institute),
            ];
        }

        return $options;
    }

    /** @return array<int, array<string, mixed>> */
    private function modelOptions(string $provider, int|string|null $institute): array
    {
        try {
            $models = $this->models->forProvider($provider, $institute);
        } catch (Throwable) {
            // A model catalogue outage leaves the provider choosable with a free-text
            // model, which is better than a provider nobody can pick.
            return [];
        }

        return array_map(
            static fn (array $model) => [
                'key' => (string) $model['model_id'],
                'label' => (string) $model['label'],
                'max_output_tokens' => $model['max_output_tokens'] ?? null,
                // A platform model is shared with every school; an institute one is
                // this school's own. Shown so a chooser knows which is which.
                'scope' => (string) $model['scope'],
            ],
            $models
        );
    }

    /**
     * Credentials this school may point a binding at.
     *
     * The key itself is NEVER returned — only the id, the provider and a label. A screen
     * that needs to choose a credential does not need to see one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function credentialOptions(int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return [];
        }

        return DB::table('ai_api_keys')
            ->where('status', 1)
            ->where(fn ($query) => $query->whereNull('sub_institute_id')->orWhere('sub_institute_id', $institute))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'api_type', 'account_email', 'api_limit', 'sub_institute_id'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'provider' => (string) $row->api_type,
                // An account email identifies a key to the person who added it without
                // exposing anything that could be used.
                'label' => trim((string) ($row->account_email ?? '')) ?: ('Credential #'.$row->id),
                'daily_limit' => $row->api_limit === null ? null : (int) $row->api_limit,
                'scope' => ($row->sub_institute_id ?? null) === null ? 'platform' : 'institute',
            ])
            ->all();
    }

    private function credentialVisible(int $id, int|string|null $institute): bool
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return false;
        }

        return DB::table('ai_api_keys')
            ->where('id', $id)
            ->where('status', 1)
            ->where(fn ($query) => $query->whereNull('sub_institute_id')->orWhere('sub_institute_id', $institute))
            ->exists();
    }
}
