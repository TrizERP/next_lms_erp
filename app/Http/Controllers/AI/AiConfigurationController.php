<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Configuration\AiConfigurationResolver;
use App\Domain\AI\Configuration\AiModuleRegistry;
use App\Domain\AI\Configuration\CredentialInspector;
use App\Domain\AI\Configuration\ModelCatalog;
use App\Domain\AI\Configuration\ProviderCatalog;
use App\Domain\AI\Configuration\ProviderCompatibility;
use App\Domain\AI\Configuration\ProviderModelDiscovery;
use App\Domain\AI\Support\AiAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * AI Provider & Model Management — the write half of the console.
 *
 * `CapabilityController` reports what is configured; this changes it. They are
 * deliberately separate: one is a read-only view over every capability, this is the
 * small number of endpoints allowed to write credentials, and keeping the writes in
 * their own controller keeps the surface that can touch `ai_api_keys` easy to find.
 *
 * A CREDENTIAL IS NEVER RETURNED
 *
 * No response from this controller contains an API key. Rows come back with a
 * `key_preview` — first four and last four characters — which is enough for an
 * administrator to tell two keys apart and not enough to use one. `store()` and
 * `update()` accept a key but never echo it, and `update()` treats an omitted key as
 * "leave it alone" so an edit of the model does not require re-typing the credential.
 *
 * TENANT SCOPE COMES FROM THE TOKEN
 *
 * Every write is stamped with `$this->scope($request)->selectedInstituteId` and every
 * read is filtered by it. A caller cannot save a configuration into another school by
 * naming one, because the school is never read from input.
 */
class AiConfigurationController extends AiController
{
    public function __construct(
        private readonly AiModuleRegistry $modules,
        private readonly ProviderCatalog $providers,
        private readonly ModelCatalog $models,
        private readonly AiConfigurationResolver $resolver,
        private readonly AiAuditLogger $audit,
        private readonly ProviderCompatibility $compatibility,
        private readonly CredentialInspector $credentials,
        private readonly ProviderModelDiscovery $discovery,
    ) {
    }

    /**
     * Everything the Add/Edit form needs to render: modules, providers, models.
     *
     * One call rather than three, because the form is useless until it has all three
     * and three round trips is three chances to render half a form.
     */
    public function options(Request $request)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            return $this->success('AI configuration options resolved.', [
                'modules' => $this->modules->all(),
                'providers' => $this->providers->all(),
                'models' => $this->models->grouped($institute, false),
                'priority_supported' => $this->resolver->supportsPriority(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The saved configurations, plus what every module currently resolves to.
     *
     * Both halves matter. `configurations` is the list an administrator edits;
     * `resolved` is what the runtime will actually do — including for modules with no
     * row of their own, which fall back to the pool. Showing only the first would hide
     * the fact that an unconfigured module is still calling something.
     */
    public function index(Request $request)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            // `verify=0` reads cached credential verdicts only — for a caller that must not wait on
            // a provider. The default asks, once per distinct key, and the answer is cached.
            $live = $request->query('verify', '1') !== '0';

            return $this->success('AI configurations resolved.', [
                'sub_institute_id' => $institute,
                'priority_supported' => $this->resolver->supportsPriority(),
                'configurations' => $this->configurations($institute),
                'resolved' => $this->resolvedWithCredentials($institute, $live),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Which providers and models can really serve one module, ranked.
     *
     * What the form asks the moment a module is picked. It reads the providers' own model lists, so
     * it is slower than `options` and is asked per module rather than for all of them at once.
     */
    public function compatibility(Request $request)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            $request->validate(['module' => ['required', 'string', Rule::in($this->modules->keys())]]);

            return $this->success('AI compatibility resolved.', $this->compatibility->forModule(
                (string) $request->query('module'),
                $institute,
                $request->query('verify', '1') !== '0'
            ));
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Rank a module's providers: `order` is this school's configuration ids, first choice first.
     *
     * Only rows this school owns can be ranked, and only for the module named — an id from another
     * school or another module is refused, not skipped, so a stale screen cannot half-apply.
     */
    public function reorder(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $data = $request->validate([
                'ai_module' => ['required', 'string', Rule::in($this->modules->keys())],
                'order' => ['required', 'array', 'min:1', 'max:20'],
                'order.*' => ['integer', 'distinct'],
            ]);

            if (! $this->resolver->supportsPriority()) {
                return $this->failure(
                    'Provider priority cannot be saved on this installation yet — the priority column has not been added. '
                    . 'Providers are tried newest-first until it is.',
                    409
                );
            }

            $rows = DB::table('ai_api_keys')
                ->whereIn('id', $data['order'])
                ->where('ai_module', $data['ai_module'])
                ->where('sub_institute_id', $institute)
                ->pluck('id')
                ->all();

            if (count($rows) !== count($data['order'])) {
                return $this->failure('One of those configurations was not found for this module.', 422);
            }

            DB::transaction(function () use ($data) {
                foreach (array_values($data['order']) as $position => $id) {
                    DB::table('ai_api_keys')->where('id', $id)->update([
                        'priority' => $position + 1,
                        'updated_at' => now(),
                    ]);
                }
            });

            $this->resolver->flush($institute !== null ? (string) $institute : null);

            $this->audit->record('ai.configuration.reordered', $scope, [
                'related_type' => 'ai_api_keys',
                'message' => sprintf('Provider order set for %s.', $this->modules->label($data['ai_module'])),
                'payload' => ['ai_module' => $data['ai_module'], 'order' => array_values($data['order'])],
            ]);

            return $this->success('Provider order saved.', [
                'configurations' => $this->configurations($institute),
                'resolved' => $this->resolvedWithCredentials($institute, false),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Ask the provider whether a stored key is accepted, and say so. */
    public function verify(Request $request, int $id)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            // Visible rows, not only owned ones: a school may check the shared key it is using.
            $row = DB::table('ai_api_keys')
                ->where('id', $id)
                ->where(function ($q) use ($institute) {
                    $q->whereNull('sub_institute_id');

                    if ($institute !== null && trim((string) $institute) !== '') {
                        $q->orWhere('sub_institute_id', $institute);
                    }
                })
                ->first();

            if ($row === null) {
                return $this->failure('That AI configuration was not found.', 404);
            }

            $provider = $this->resolver->providerFromRow($row);

            if ($provider === null) {
                return $this->failure('This row is not tagged with a provider this platform knows.', 422);
            }

            $key = \App\Support\KeyVault::reveal(trim((string) ($row->api_key ?? '')));
            $verdict = $key === '' ? null : $this->discovery->verify($provider, $key, fresh: true);

            return $this->success('Credential checked.', [
                'id' => $id,
                'credential' => $key === '' ? $this->credentials->inspect($provider, null) : $this->credentials->describe($verdict),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The overview, with each module's credential judged rather than assumed.
     *
     * A module's credential is the head of its chain: the key it will actually send. It is checked
     * once per distinct key, so fourteen modules sharing two keys cost two checks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolvedWithCredentials(int|string|null $institute, bool $live): array
    {
        $rows = $this->resolver->overview($institute);
        $apiKeys = [];

        foreach ($rows as $i => $row) {
            if ($row['runtime'] === 'none') {
                $rows[$i]['credential'] = [
                    'status' => 'not_required',
                    'label' => 'Not used',
                    'detail' => 'This module makes no model call, so no credential is used.',
                ];
                continue;
            }

            if (! $row['has_key'] || $row['provider'] === null) {
                $rows[$i]['credential'] = $this->credentials->inspect((string) ($row['provider'] ?? ''), null);
                continue;
            }

            $keyId = $row['key_id'];
            $cacheKey = $row['provider'] . '|' . ($keyId ?? 'env');

            if (! array_key_exists($cacheKey, $apiKeys)) {
                $stored = $keyId === null ? null : \App\Support\KeyVault::reveal(DB::table('ai_api_keys')->where('id', $keyId)->value('api_key'));
                $apiKeys[$cacheKey] = $this->credentials->inspect($row['provider'], $stored, $live);
            }

            $rows[$i]['credential'] = $apiKeys[$cacheKey];

            // The chain's other legs get a status too, so a fallback that would fail shows as such.
            foreach ($rows[$i]['chain'] as $c => $leg) {
                if ($c === 0) {
                    $rows[$i]['chain'][$c]['credential'] = $apiKeys[$cacheKey];
                    continue;
                }

                $legKey = $leg['key_id'];
                $legCache = $leg['provider'] . '|' . ($legKey ?? 'env');

                if (! array_key_exists($legCache, $apiKeys)) {
                    $stored = $legKey === null ? null : \App\Support\KeyVault::reveal(DB::table('ai_api_keys')->where('id', $legKey)->value('api_key'));
                    $apiKeys[$legCache] = $this->credentials->inspect($leg['provider'], $stored, $live);
                }

                $rows[$i]['chain'][$c]['credential'] = $apiKeys[$legCache];
            }
        }

        return $rows;
    }

    /** Add a configuration: module → provider → model → key. */
    public function store(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $data = $this->validated($request, $institute, requireKey: true);

            // One row per (module, provider, institute). Saving the same pairing twice
            // is an edit, not a second row — two active rows for one module would make
            // resolution depend on insertion order, which is the coin flip this whole
            // change exists to remove.
            $existing = DB::table('ai_api_keys')
                ->where('ai_module', $data['ai_module'])
                ->where('api_type', $data['api_type'])
                ->where('sub_institute_id', $institute)
                ->first();

            if ($existing !== null) {
                return $this->failure(
                    'A configuration for this module and provider already exists. Edit it instead.',
                    409,
                    ['id' => $existing->id]
                );
            }

            // The provider is asked whether it accepts the key AND has access to the model BEFORE anything is written
            $credential = $this->checkCredential($data['provider'], $data['api_key'], $data['model']);

            if ($data['model'] !== null) {
                $this->models->ensure($data['provider'], $data['model'], $institute);
            }

            $insert = [
                'ai_module' => $data['ai_module'],
                'api_type' => $data['api_type'],
                'model' => $data['model'],
                'api_key' => \App\Support\KeyVault::seal($data['api_key']),
                'account_email' => $data['account_email'],
                'api_limit' => $data['api_limit'],
                'status' => $data['status'],
                'sub_institute_id' => $institute,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($this->resolver->supportsPriority()) {
                // A new provider goes to the back of the module's order unless one was named.
                $insert['priority'] = $data['priority'] ?? ((int) DB::table('ai_api_keys')
                    ->where('ai_module', $data['ai_module'])
                    ->where('sub_institute_id', $institute)
                    ->where('status', 1)
                    ->max('priority') + 1);
            }

            $id = DB::table('ai_api_keys')->insertGetId($insert);

            $this->resolver->flush($institute !== null ? (string) $institute : null);

            $this->recordChange('created', $id, $data, $scope, $request);

            return $this->success('AI configuration saved.', [
                'configuration' => $this->configuration((int) $id, $institute),
                'credential' => $credential,
                // The table the form refreshes: what each module calls, straight after the write,
                // from the same resolver the runtime uses — not from what the form was sent.
                'resolved' => $this->resolvedWithCredentials($institute, false),
            ], 201);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Edit a configuration.
     *
     * The key is optional here. An administrator changing a model should not have to
     * re-enter a credential they cannot read back off the screen, and forcing them to
     * would mean the key gets kept in a text file somewhere so it can be re-pasted.
     */
    public function update(Request $request, int $id)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $row = $this->ownedRow($id, $institute);

            if ($row === null) {
                return $this->failure('That AI configuration was not found.', 404);
            }

            $data = $this->validated($request, $institute, requireKey: false);

            $previousProvider = $this->resolver->providerFromRow($row);

            // A key belongs to one provider. Pointing an existing row at a different provider while
            // keeping the old key would store, say, a Google key as an OpenAI credential.
            if ($data['api_key'] === null && $previousProvider !== null && $previousProvider !== $data['provider']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'api_key' => ['Changing the provider needs that provider\'s own API key — the stored key belongs to ' . $this->providers->label($previousProvider) . '.'],
                ]);
            }

            $keyToCheck = $data['api_key'] ?? \App\Support\KeyVault::reveal($row->api_key);
            $credential = $keyToCheck !== null ? $this->checkCredential($data['provider'], $keyToCheck, $data['model']) : null;

            if ($data['model'] !== null) {
                $this->models->ensure($data['provider'], $data['model'], $institute);
            }

            $update = [
                'ai_module' => $data['ai_module'],
                'api_type' => $data['api_type'],
                'model' => $data['model'],
                'account_email' => $data['account_email'],
                'api_limit' => $data['api_limit'],
                'status' => $data['status'],
                'updated_at' => now(),
            ];

            if ($data['priority'] !== null && $this->resolver->supportsPriority()) {
                $update['priority'] = $data['priority'];
            }

            if ($data['api_key'] !== null) {
                $update['api_key'] = \App\Support\KeyVault::seal($data['api_key']);
            }

            DB::table('ai_api_keys')->where('id', $id)->update($update);

            $this->resolver->flush($institute !== null ? (string) $institute : null);

            $this->recordChange('updated', $id, $data, $scope, $request);

            return $this->success('AI configuration updated.', [
                'configuration' => $this->configuration($id, $institute),
                'credential' => $credential,
                'resolved' => $this->resolvedWithCredentials($institute, false),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Retire a configuration.
     *
     * Sets `status = 0` rather than deleting. A deleted credential row takes with it
     * the only record of which key a past generation ran on, and `ai_generation_requests`
     * rows point at it. Retired rows stop resolving immediately, which is the part that
     * matters operationally.
     */
    public function destroy(Request $request, int $id)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if ($this->ownedRow($id, $institute) === null) {
                return $this->failure('That AI configuration was not found.', 404);
            }

            DB::table('ai_api_keys')->where('id', $id)->update([
                'status' => 0,
                'updated_at' => now(),
            ]);

            $this->resolver->flush($institute !== null ? (string) $institute : null);

            $this->audit->record('ai.configuration.retired', $scope, [
                'related_type' => 'ai_api_keys',
                'related_id' => $id,
                'message' => 'AI configuration retired.',
            ]);

            return $this->success('AI configuration retired.', ['id' => $id]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    // ---------------------------------------------------------------------
    // Model catalogue — the list the model dropdown reads.
    // ---------------------------------------------------------------------

    public function models(Request $request)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            return $this->success('AI models resolved.', [
                'sub_institute_id' => $institute,
                'providers' => $this->providers->all(),
                'models' => $this->models->grouped($institute),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function storeModel(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! Schema::hasTable('ai_models')) {
                return $this->failure('The model catalogue is not installed on this estate.', 422);
            }

            $data = $this->validatedModel($request);

            $exists = DB::table('ai_models')
                ->where('provider', $data['provider'])
                ->where('model_id', $data['model_id'])
                ->where('sub_institute_id', $institute)
                ->exists();

            if ($exists) {
                return $this->failure('That model is already in the catalogue for this school.', 409);
            }

            $id = DB::table('ai_models')->insertGetId($data + [
                'sub_institute_id' => $institute,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record('ai.model.created', $scope, [
                'related_type' => 'ai_models',
                'related_id' => $id,
                'message' => sprintf('Model %s added for %s.', $data['model_id'], $data['provider']),
            ]);

            return $this->success('Model added.', [
                'model' => $this->models->find((int) $id, $institute),
            ], 201);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Edit a model.
     *
     * Only a school's own rows. A platform row is shared by every school on the
     * estate, so letting one rename or retire it would change what every other school
     * sees — the screen marks those read-only and this refuses them regardless, since
     * a UI flag is not an authorisation check.
     */
    public function updateModel(Request $request, int $id)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! Schema::hasTable('ai_models')) {
                return $this->failure('The model catalogue is not installed on this estate.', 422);
            }

            $row = DB::table('ai_models')->where('id', $id)->first();

            if ($row === null) {
                return $this->failure('That model was not found.', 404);
            }

            if (($row->sub_institute_id ?? null) === null) {
                return $this->failure(
                    'This model is part of the shared platform catalogue and cannot be edited here.',
                    403
                );
            }

            if ((string) $row->sub_institute_id !== (string) $institute) {
                return $this->failure('That model was not found.', 404);
            }

            $data = $this->validatedModel($request);

            DB::table('ai_models')->where('id', $id)->update($data + ['updated_at' => now()]);

            $this->audit->record('ai.model.updated', $scope, [
                'related_type' => 'ai_models',
                'related_id' => $id,
                'message' => sprintf('Model %s updated.', $data['model_id']),
            ]);

            return $this->success('Model updated.', [
                'model' => $this->models->find($id, $institute),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /**
     * @return array{ai_module:string, api_type:string, provider:string, model:string|null, api_key:string|null, account_email:string|null, api_limit:string|null, status:int}
     */
    private function validated(Request $request, int|string|null $institute, bool $requireKey): array
    {
        $validated = $request->validate([
            'ai_module' => ['required', 'string', Rule::in($this->modules->keys())],
            'provider' => ['required', 'string', Rule::in($this->providers->keys())],
            'model' => ['nullable', 'string', 'max:120'],
            'api_key' => [$requireKey ? 'required' : 'nullable', 'string', 'min:8', 'max:4096'],
            'account_email' => ['nullable', 'email', 'max:191'],
            'api_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $provider = $validated['provider'];

        if (isset($validated['priority']) && ! $this->resolver->supportsPriority()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'priority' => ['Provider priority cannot be saved on this installation yet — the priority column has not been added.'],
            ]);
        }

        // Refused at Save rather than at the moment a user asks a question. A provider
        // with no client cannot be called, and storing the configuration anyway would
        // mean the failure surfaces later, to someone who did not make the choice.
        if (! $this->providers->isDriveable($provider)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'provider' => [sprintf(
                    '%s is not callable from this platform yet, so it cannot be saved as a module\'s provider.',
                    $this->providers->label($provider)
                )],
            ]);
        }

        $model = trim((string) ($validated['model'] ?? ''));
        $model = $model === '' ? null : $model;

        if ($model !== null && ! $this->models->offers($provider, $model, $institute)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'model' => ['That model is not in the catalogue for this provider. Add it in Model Management first.'],
            ]);
        }

        // The same rules the form shows beside each provider and model, applied here as well: a
        // provider that cannot do what the module needs, or a model that does not fit it, is
        // refused no matter how the request was built.
        $refusals = $this->compatibility->refusals($validated['ai_module'], $provider, $model, $institute);

        if ($refusals !== []) {
            $errors = [];

            foreach ($refusals as $refusal) {
                $errors[$refusal['field']][] = $refusal['message'];
            }

            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        $key = $validated['api_key'] ?? null;
        $key = is_string($key) && trim($key) !== '' ? trim($key) : null;

        return [
            'ai_module' => $validated['ai_module'],
            'provider' => $provider,
            // The value credentials for this provider are tagged with, so a row saved
            // here resolves through the same lookup as the rows that predate this screen.
            'api_type' => $this->providers->apiType($provider),
            'model' => $model,
            'api_key' => $key,
            'account_email' => $validated['account_email'] ?? null,
            'api_limit' => isset($validated['api_limit']) ? (string) $validated['api_limit'] : null,
            'status' => (int) ($validated['status'] ?? 1),
            'priority' => isset($validated['priority']) ? (int) $validated['priority'] : null,
        ];
    }

    private function checkCredential(string $provider, ?string $apiKey, ?string $model = null): array
    {
        if ($apiKey === null) {
            return $this->credentials->inspect($provider, null);
        }

        if ($model !== null) {
            $access = $this->discovery->verifyModelAccess($provider, $apiKey, $model);
            if (! $access['ok']) {
                $field = ($access['status'] === 'invalid_key') ? 'api_key' : 'model';
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => [$access['message'] ?? sprintf('%s model access verification failed.', $this->providers->label($provider))],
                ]);
            }
        } else {
            $verdict = $this->discovery->verify($provider, $apiKey, fresh: true);
            if (($verdict['status'] ?? null) === 'invalid') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'api_key' => [sprintf(
                        '%s rejected this API key. Check that it is a %s key and that it has not been revoked.',
                        $this->providers->label($provider),
                        $this->providers->label($provider)
                    )],
                ]);
            }
        }

        $verdict = $this->discovery->verify($provider, $apiKey, fresh: true);

        return $this->credentials->describe($verdict);
    }

    /** @return array<string, mixed> */
    private function validatedModel(Request $request): array
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($this->providers->keys())],
            'model_id' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:120'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'input_cost_per_1k' => ['nullable', 'numeric', 'min:0'],
            'output_cost_per_1k' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
        ]);

        return [
            'provider' => $validated['provider'],
            'model_id' => trim($validated['model_id']),
            'label' => trim($validated['label']),
            'max_output_tokens' => $validated['max_output_tokens'] ?? null,
            'input_cost_per_1k' => $validated['input_cost_per_1k'] ?? null,
            'output_cost_per_1k' => $validated['output_cost_per_1k'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => (int) ($validated['status'] ?? 1),
        ];
    }

    // ---------------------------------------------------------------------
    // Reads
    // ---------------------------------------------------------------------

    /**
     * A row this school is allowed to edit.
     *
     * Platform rows (`sub_institute_id IS NULL`) are excluded: they are the shared
     * fallback every school resolves through, and one school editing them would change
     * every other school's AI. They stay visible on the screen, marked read-only.
     */
    private function ownedRow(int $id, int|string|null $institute): ?object
    {
        return DB::table('ai_api_keys')
            ->where('id', $id)
            ->where('sub_institute_id', $institute)
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    private function configurations(int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return [];
        }

        $hasModule = Schema::hasColumn('ai_api_keys', 'ai_module');

        $rows = DB::table('ai_api_keys')
            ->where(function ($query) use ($institute) {
                $query->whereNull('sub_institute_id');

                if ($institute !== null && trim((string) $institute) !== '') {
                    $query->orWhere('sub_institute_id', $institute);
                }
            })
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->orderByDesc('id')
            ->get();

        return $rows->map(fn ($row) => $this->present($row, $institute, $hasModule))->all();
    }

    private function configuration(int $id, int|string|null $institute): ?array
    {
        $row = DB::table('ai_api_keys')->where('id', $id)->first();

        return $row ? $this->present($row, $institute, Schema::hasColumn('ai_api_keys', 'ai_module')) : null;
    }

    /** @return array<string, mixed> */
    private function present(object $row, int|string|null $institute, bool $hasModule): array
    {
        $module = $hasModule ? ($row->ai_module ?? null) : null;
        $provider = $this->providerFor($row);
        // Cached verdict only: a list of rows must not make one provider call per row. Saving and the
        // Verify button are what put a verdict in the cache.
        $credential = $this->credentials->inspect($provider, (string) \App\Support\KeyVault::reveal((string) ($row->api_key ?? '')), live: false);
        $isPlatform = ($row->sub_institute_id ?? null) === null;

        return [
            'id' => (int) $row->id,
            'ai_module' => $module,
            // An unbound row is not a mistake — it is the pool every module falls back
            // to — so it is labelled as what it is rather than left blank.
            'module_label' => $module === null ? 'All modules (shared pool)' : $this->modules->label($module),
            'module_wired' => $module !== null && $this->modules->isWired($module),
            'module_runtime' => $module !== null ? $this->modules->runtime($module) : null,
            'priority' => is_numeric($row->priority ?? null) && (int) $row->priority > 0 ? (int) $row->priority : null,
            'credential' => $credential,
            'provider' => $provider,
            'provider_label' => $this->providers->label($provider),
            'api_type' => (string) ($row->api_type ?? ''),
            'model' => $hasModule ? ($row->model ?? null) : null,
            'account_email' => $row->account_email ?? null,
            'api_limit' => $row->api_limit ?? null,
            'status' => (int) $row->status,
            'scope' => $isPlatform ? 'platform' : 'institute',
            // Platform rows are shared across the estate; this school may look but not
            // touch. The API enforces this too — see ownedRow().
            'editable' => ! $isPlatform && (string) ($row->sub_institute_id ?? '') === (string) $institute,
            'key_preview' => $this->preview((string) \App\Support\KeyVault::reveal((string) ($row->api_key ?? ''))),
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function providerFor(object $row): string
    {
        $apiType = trim((string) ($row->api_type ?? ''));

        if ($this->providers->exists($apiType)) {
            return $apiType;
        }

        foreach ($this->providers->keys() as $provider) {
            if (strcasecmp($this->providers->apiType($provider), $apiType) === 0) {
                return $provider;
            }
        }

        return $apiType !== '' ? $apiType : 'unknown';
    }

    /**
     * Enough of a key to recognise it, never enough to use it.
     *
     * A short value is masked entirely rather than partly: revealing four of eight
     * characters is a meaningful fraction of the secret.
     */
    private function preview(string $key): ?string
    {
        $key = trim($key);

        if ($key === '') {
            return null;
        }

        if (mb_strlen($key) < 16) {
            return str_repeat('•', 8);
        }

        return mb_substr($key, 0, 4) . str_repeat('•', 8) . mb_substr($key, -4);
    }

    private function recordChange(string $verb, int|string $id, array $data, $scope, Request $request): void
    {
        $this->audit->record("ai.configuration.{$verb}", $scope, [
            'related_type' => 'ai_api_keys',
            'related_id' => (int) $id,
            'message' => sprintf(
                'AI configuration %s: %s → %s / %s.',
                $verb,
                $this->modules->label($data['ai_module']),
                $this->providers->label($data['provider']),
                $data['model'] ?? 'provider default'
            ),
            // AiAuditLogger redacts `api_key`, and it is not put in here anyway —
            // two independent reasons the credential cannot reach an audit row.
            'payload' => [
                'ai_module' => $data['ai_module'],
                'provider' => $data['provider'],
                'model' => $data['model'],
                'status' => $data['status'],
            ],
        ]);
    }
}
