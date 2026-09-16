<?php

namespace App\Domain\AI\Templates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Reads and writes `ai_templates` for the Template Management screen.
 *
 * `TemplateRegistry` is the runtime's view of the same table: find the published
 * template for a key, render it, hand it to the model. It deliberately knows nothing
 * about drafts, versions, ownership or the module a template is filed under, because
 * none of that matters at the moment of a generation. This class is the administrative
 * view — every version, every status, who owns each one — and it is separate for the
 * same reason `AiConfigurationController` is separate from `CapabilityController`:
 * the code allowed to write should be small and easy to find.
 *
 * TWO ROWS MAKE A TEMPLATE USABLE, NOT ONE
 *
 * Storing a row in `ai_templates` publishes a template centrally. It does *not* make
 * the Fees screen offer it — that takes a row in `ai_suggestions` binding
 * `module_key` to the template key, which is what `CapabilityResolver` reads when the
 * AI panel opens. Until now those two rows were written by two hand-authored
 * migrations, which is why three Fees templates took three files to ship.
 *
 * `syncBinding()` writes the second row from the first. Saving a published template
 * against a module is all it takes for that module to offer it; moving the template to
 * another module moves the binding with it; archiving it withdraws the binding rather
 * than leaving a menu entry pointing at a template the runtime will refuse to render.
 * That is the whole of "stored centrally so the respective module can use them
 * automatically", and it is one method rather than a convention someone has to know.
 *
 * PLATFORM ROWS ARE OVERRIDDEN, NEVER EDITED
 *
 * A template with `sub_institute_id = NULL` is the platform baseline that every school
 * on the estate resolves. A school editing one would silently change the other
 * ninety-one. So an edit to a baseline row from a tenant console writes a *copy* owned
 * by that school instead, which `TemplateRegistry::find()` already prefers over the
 * baseline. One school's rewording stays one school's.
 */
class TemplateCatalog
{
    /** Status values the table accepts. Kept here so the controller validates against one list. */
    public const STATUSES = ['draft', 'published', 'archived'];

    /**
     * What a template *is*.
     *
     * `prompt` is sent to a model, which writes prose. `report` is an HTML layout whose
     * placeholders are filled by substitution from rows an MCP tool fetched — no model
     * touches the figures. They share this table because everything else about them is
     * the same: module, version, status, tenant override. See the migration.
     */
    public const KINDS = ['prompt', 'report'];

    /** What the output of a template is expected to look like. */
    public const OUTPUT_FORMATS = ['text', 'markdown', 'json'];

    /**
     * The categories in use, offered as suggestions rather than enforced.
     *
     * `ai_templates.category` is a free string and the runtime never switches on it —
     * `TemplateRegistry::all()` only filters by it. Constraining it now would reject
     * the categories a school invents for its own templates, so this is a starting
     * list for a datalist, not a validation rule.
     */
    public const SUGGESTED_CATEGORIES = [
        'report', 'summary', 'explanation', 'recommendation', 'feedback',
        'field_edit', 'intervention', 'lesson', 'assessment', 'communication',
    ];

    public function __construct(
        private readonly TemplateModuleCatalog $modules,
        private readonly TemplateVariableCatalog $variables,
    ) {
    }

    /**
     * The templates for one module, newest version of each key first.
     *
     * `$moduleKey` is the selector's value: a real module key, the shared sentinel, or
     * null for "every module" — the last being what the screen shows before a module
     * is picked, so an administrator can see the whole estate at once.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forModule(?string $moduleKey, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_templates')) {
            return [];
        }

        $query = DB::table('ai_templates')->where(function ($inner) use ($institute) {
            $inner->whereNull('sub_institute_id');

            if ($institute !== null && $institute !== '') {
                $inner->orWhere('sub_institute_id', $institute);
            }
        });

        if ($moduleKey === TemplateModuleCatalog::SHARED) {
            $query->whereNull('module_key');
        } elseif ($moduleKey !== null && $moduleKey !== '') {
            $query->where('module_key', $moduleKey);
        }

        $rows = $query
            ->orderBy('template_key')
            ->orderByDesc('version')
            ->get();

        $bindings = $this->bindings($institute);

        return $rows->map(fn ($row) => $this->present($row, $institute, $bindings))->values()->all();
    }

    /**
     * One template, or null when it is not visible to this school.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id, int|string|null $institute): ?array
    {
        $row = $this->row($id, $institute);

        return $row === null ? null : $this->present($row, $institute, $this->bindings($institute));
    }

    /**
     * The raw row, scoped so a caller cannot reach another school's template by id.
     */
    public function row(int $id, int|string|null $institute): ?object
    {
        if (! Schema::hasTable('ai_templates')) {
            return null;
        }

        return DB::table('ai_templates')
            ->where('id', $id)
            ->where(function ($inner) use ($institute) {
                $inner->whereNull('sub_institute_id');

                if ($institute !== null && $institute !== '') {
                    $inner->orWhere('sub_institute_id', $institute);
                }
            })
            ->first();
    }

    /**
     * Store a new template and bind it to its module.
     *
     * @param array<string, mixed> $data
     * @return int The new row's id.
     */
    public function create(array $data, int|string|null $institute, int|string|null $clientId = null): int
    {
        $moduleKey = $this->modules->toColumn($data['module_key'] ?? null);

        // `shared` writes the row with no `sub_institute_id`, which is how a template
        // becomes available to every school on the estate rather than only the one that
        // wrote it — `TemplateRegistry` and `ReportTemplateResolver` both read a NULL
        // row as the platform baseline for everyone.
        //
        // Opt-in, and never the default: an ordinary save stays scoped to the author's
        // own school, because a screen that quietly published one school's wording to
        // ninety-one others would be a bad surprise. The Fees template screen asks for
        // it deliberately, since Fees templates are meant to serve the whole estate.
        $shared = (bool) ($data['shared'] ?? false);
        $owner = $shared ? null : $institute;

        $templateKey = $this->resolveKey($data, $moduleKey, $owner);

        $version = $this->nextVersion($templateKey, $owner);

        $id = (int) DB::table('ai_templates')->insertGetId(
            $this->columns($data, $templateKey, $moduleKey, $version) + [
                'created_by' => $data['created_by'] ?? null,
                'sub_institute_id' => $owner === '' ? null : $owner,
                'client_id' => $clientId === '' ? null : $clientId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // The module binding follows the template's own scope, so an estate-wide
        // template is offered estate-wide and a school's own template only in that
        // school. Passing `$institute` here regardless would have filed a shared
        // template's button under the author's school alone.
        $this->syncBinding($templateKey, $moduleKey, $data, $owner, $clientId);

        return $id;
    }

    /**
     * Change a template.
     *
     * Three outcomes, and the caller is told which one happened rather than having to
     * infer it from the returned id:
     *
     *   - `overridden`  — the row was the platform baseline, so a copy owned by this
     *                     school was written and the baseline left untouched.
     *   - `versioned`   — a new version was inserted and the previous published one
     *                     archived, so the change is reversible by republishing it.
     *   - `updated`     — the row was edited where it stood.
     *
     * @param array<string, mixed> $data
     * @return array{id:int, action:string}
     */
    public function update(
        int $id,
        array $data,
        int|string|null $institute,
        int|string|null $clientId = null,
        bool $asNewVersion = false
    ): array {
        $row = $this->row($id, $institute);

        if ($row === null) {
            throw new RuntimeException('That template could not be found.');
        }

        $moduleKey = $this->modules->toColumn($data['module_key'] ?? null);
        $templateKey = trim((string) ($data['template_key'] ?? $row->template_key));

        $isBaseline = $row->sub_institute_id === null;
        $ownsIt = $institute !== null && $institute !== '';

        // A school editing the platform baseline gets its own copy. Editing in place
        // would rewrite the template every other school on the estate resolves.
        if ($isBaseline && $ownsIt) {
            // Unless it already has one. Customising the same baseline twice used to
            // insert a second copy at the same (template_key, version, sub_institute_id)
            // and die on `ai_templates_key_ver_tenant_unique` — a 500 for the ordinary
            // act of correcting a typo in yesterday's override.
            $existing = DB::table('ai_templates')
                ->where('template_key', $templateKey)
                ->where('version', (int) $row->version)
                ->where('sub_institute_id', $institute)
                ->first();

            if ($existing !== null) {
                DB::table('ai_templates')
                    ->where('id', $existing->id)
                    ->update($this->columns($data, $templateKey, $moduleKey, (int) $existing->version) + [
                        'updated_at' => now(),
                    ]);

                $this->syncBinding($templateKey, $moduleKey, $data, $institute, $clientId);

                return ['id' => (int) $existing->id, 'action' => 'updated'];
            }

            $id = (int) DB::table('ai_templates')->insertGetId(
                $this->columns($data, $templateKey, $moduleKey, (int) $row->version) + [
                    'created_by' => $data['created_by'] ?? null,
                    'sub_institute_id' => $institute,
                    'client_id' => $clientId === '' ? null : $clientId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->syncBinding($templateKey, $moduleKey, $data, $institute, $clientId);

            return ['id' => $id, 'action' => 'overridden'];
        }

        if ($asNewVersion) {
            $version = $this->nextVersion($templateKey, $institute);

            $newId = (int) DB::table('ai_templates')->insertGetId(
                $this->columns($data, $templateKey, $moduleKey, $version) + [
                    'created_by' => $data['created_by'] ?? null,
                    'sub_institute_id' => $institute === '' ? null : $institute,
                    'client_id' => $clientId === '' ? null : $clientId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // The old version is archived rather than deleted, which is the whole
            // point of versioning it: republishing it is how a bad prompt is rolled
            // back, and that is impossible if the previous text is gone.
            DB::table('ai_templates')
                ->where('id', $row->id)
                ->where('status', 'published')
                ->update(['status' => 'archived', 'updated_at' => now()]);

            $this->syncBinding($templateKey, $moduleKey, $data, $institute, $clientId);

            return ['id' => $newId, 'action' => 'versioned'];
        }

        DB::table('ai_templates')
            ->where('id', $row->id)
            ->update($this->columns($data, $templateKey, $moduleKey, (int) $row->version) + [
                'updated_at' => now(),
            ]);

        // The key or module may have moved. Withdraw the binding the old pairing had,
        // or a retired template keeps its place in the module's panel.
        if ((string) $row->template_key !== $templateKey || $row->module_key !== $moduleKey) {
            $this->withdrawBinding((string) $row->template_key, $row->module_key, $institute);
        }

        $this->syncBinding($templateKey, $moduleKey, $data, $institute, $clientId);

        return ['id' => (int) $row->id, 'action' => 'updated'];
    }

    /**
     * Retire a template: archived, not deleted, and withdrawn from its module.
     *
     * Deleting would break every `ai_generation_requests` row that names it, which is
     * the audit trail for content this template already produced.
     */
    public function archive(int $id, int|string|null $institute): void
    {
        $row = $this->row($id, $institute);

        if ($row === null) {
            throw new RuntimeException('That template could not be found.');
        }

        if ($row->sub_institute_id === null && $institute !== null && $institute !== '') {
            throw new RuntimeException(
                'This is a platform template shared by every school, so it cannot be retired from here. '
                . 'Save your own version of it instead, or remove it from the module it is bound to.'
            );
        }

        DB::table('ai_templates')
            ->where('id', $row->id)
            ->update(['status' => 'archived', 'updated_at' => now()]);

        $this->withdrawBinding((string) $row->template_key, $row->module_key, $institute);
    }

    /**
     * Substitute a template's placeholders with sample values, without calling a model.
     *
     * This is the "view" half of the screen doing real work: it shows the prompt as the
     * model will receive it, so an author can see that `{{records}}` lands where they
     * meant it to and that nothing is left as a literal placeholder. Rendering locally
     * rather than generating is deliberate — it costs nothing, works when no provider
     * is configured, and answers the question the author actually has, which is about
     * the prompt and not about the model.
     *
     * @param array<string, mixed> $values
     * @return array{system:?string, user:string, unresolved:array<int, string>}
     */
    public function preview(string $systemPrompt, string $userPrompt, array $values): array
    {
        $render = function (string $text) use ($values): string {
            return preg_replace_callback(
                '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
                function (array $match) use ($values) {
                    $key = $match[1];

                    if (! array_key_exists($key, $values)) {
                        // Left as-is rather than blanked, so the caller can see exactly
                        // which placeholder had nothing behind it.
                        return $match[0];
                    }

                    return mb_substr((string) $values[$key], 0, 8000);
                },
                $text
            ) ?? $text;
        };

        $system = $systemPrompt === '' ? null : $render($systemPrompt);
        $user = $render($userPrompt);

        $unresolved = array_values(array_unique(array_merge(
            $this->variables->used($system ?? ''),
            $this->variables->used($user)
        )));

        return [
            'system' => $system,
            'user' => $user,
            'unresolved' => array_values(array_filter(
                $unresolved,
                fn (string $key) => ! array_key_exists($key, $values)
            )),
        ];
    }

    /**
     * Write the `ai_suggestions` row that puts this template in its module's AI panel.
     *
     * Only published templates filed under a real module get one. A draft in a module
     * panel is a promise the runtime will break — `TemplateRegistry::find()` resolves
     * published rows only, so the button would be there and the generation would fail.
     *
     * @param array<string, mixed> $data
     */
    private function syncBinding(
        string $templateKey,
        ?string $moduleKey,
        array $data,
        int|string|null $institute,
        int|string|null $clientId
    ): void {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $offer = ($data['offer_in_module'] ?? true)
            && $moduleKey !== null
            && ($data['status'] ?? 'draft') === 'published';

        if (! $offer) {
            $this->withdrawBinding($templateKey, $moduleKey, $institute);

            return;
        }

        $label = trim((string) ($data['suggestion_label'] ?? '')) !== ''
            ? trim((string) $data['suggestion_label'])
            : trim((string) ($data['name'] ?? $templateKey));

        $scope = $institute === '' ? null : $institute;

        $existing = DB::table('ai_suggestions')
            ->where('module_key', $moduleKey)
            ->where('capability', 'generative')
            ->where('action_type', 'generate')
            ->where('action_ref', $templateKey)
            ->where(function ($inner) use ($scope) {
                $scope === null ? $inner->whereNull('sub_institute_id') : $inner->where('sub_institute_id', $scope);
            })
            ->first();

        $payload = [
            'label' => mb_substr($label, 0, 150),
            'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                ? mb_substr(trim((string) $data['description']), 0, 500)
                : null,
            'requires_entity' => (bool) ($data['requires_entity'] ?? false),
            'status' => 1,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table('ai_suggestions')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('ai_suggestions')->insert($payload + [
            'module_key' => $moduleKey,
            'capability' => 'generative',
            'icon' => null,
            'action_type' => 'generate',
            'action_ref' => $templateKey,
            'prompt' => null,
            'payload' => null,
            'allowed_roles' => null,
            'required_permissions' => null,
            'sort_order' => $this->nextSortOrder($moduleKey),
            'sub_institute_id' => $scope,
            'client_id' => $clientId === '' ? null : $clientId,
            'created_at' => now(),
        ]);
    }

    /**
     * Take the template out of a module's panel without deleting the row.
     *
     * `status = 0` rather than a delete so that republishing the template restores the
     * entry with its label and position intact, instead of appending a fresh one at
     * the bottom of the list.
     */
    private function withdrawBinding(string $templateKey, ?string $moduleKey, int|string|null $institute): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $scope = $institute === '' ? null : $institute;

        $query = DB::table('ai_suggestions')
            ->where('capability', 'generative')
            ->where('action_type', 'generate')
            ->where('action_ref', $templateKey)
            ->where(function ($inner) use ($scope) {
                $scope === null ? $inner->whereNull('sub_institute_id') : $inner->where('sub_institute_id', $scope);
            });

        if ($moduleKey !== null) {
            $query->where('module_key', $moduleKey);
        }

        $query->update(['status' => 0, 'updated_at' => now()]);
    }

    /**
     * Which template keys are currently offered in a module panel, and where.
     *
     * Fetched once per request and passed into `present()` rather than queried per row:
     * a module with thirty templates would otherwise be thirty-one queries to answer
     * one question.
     *
     * @return array<string, array{module_key:string, label:string, status:int}>
     */
    private function bindings(int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return [];
        }

        $rows = DB::table('ai_suggestions')
            ->where('capability', 'generative')
            ->where('action_type', 'generate')
            ->whereNotNull('action_ref')
            ->where(function ($inner) use ($institute) {
                $inner->whereNull('sub_institute_id');

                if ($institute !== null && $institute !== '') {
                    $inner->orWhere('sub_institute_id', $institute);
                }
            })
            ->get(['module_key', 'label', 'action_ref', 'status', 'sub_institute_id']);

        $bindings = [];

        foreach ($rows as $row) {
            $key = (string) $row->action_ref;

            // A tenant binding overrides the platform one for the same template, the
            // same way a tenant template overrides a platform template.
            if (isset($bindings[$key]) && $row->sub_institute_id === null) {
                continue;
            }

            $bindings[$key] = [
                'module_key' => (string) $row->module_key,
                'label' => (string) $row->label,
                'status' => (int) $row->status,
            ];
        }

        return $bindings;
    }

    /**
     * One row as the API returns it.
     *
     * @param array<string, array{module_key:string, label:string, status:int}> $bindings
     * @return array<string, mixed>
     */
    private function present(object $row, int|string|null $institute, array $bindings): array
    {
        $binding = $bindings[(string) $row->template_key] ?? null;
        $variables = $this->decode($row->variables ?? null);
        $userPrompt = (string) ($row->user_prompt ?? '');
        $systemPrompt = (string) ($row->system_prompt ?? '');

        $usedGrounding = array_intersect(
            $this->variables->groundingKeys(),
            $this->variables->used($userPrompt . ' ' . $systemPrompt)
        );

        $kind = (string) ($row->kind ?? 'prompt');
        $layout = (string) ($row->html_layout ?? '');

        return [
            'id' => (int) $row->id,
            'template_key' => (string) $row->template_key,
            'name' => (string) $row->name,
            'description' => $row->description === null ? null : (string) $row->description,
            'module_key' => $this->modules->fromColumn($row->module_key ?? null),
            'module_label' => $this->modules->label($row->module_key ?? null, $institute),
            'kind' => $kind,
            'html_layout' => $layout === '' ? null : $layout,
            'data_source' => isset($row->data_source) && $row->data_source !== ''
                ? (string) $row->data_source
                : null,
            'data_arguments' => $this->decode($row->data_arguments ?? null),
            'domain' => (string) ($row->domain ?? 'shared'),
            'category' => $row->category === null ? null : (string) $row->category,
            'version' => (int) $row->version,
            'status' => (string) $row->status,
            'system_prompt' => $systemPrompt === '' ? null : $systemPrompt,
            'user_prompt' => $userPrompt,
            'variables' => $variables,
            'output_format' => (string) ($row->output_format ?? 'text'),
            'output_schema' => $this->decode($row->output_schema ?? null),
            'provider' => $row->provider === null ? null : (string) $row->provider,
            'model' => $row->model === null ? null : (string) $row->model,
            'temperature' => $row->temperature === null ? null : (float) $row->temperature,
            'max_tokens' => $row->max_tokens === null ? null : (int) $row->max_tokens,
            'safety_rules' => $this->decode($row->safety_rules ?? null),
            'allow_as_evidence' => (bool) $row->allow_as_evidence,
            'requires_review' => (bool) $row->requires_review,

            // Ownership, stated rather than implied. The screen needs it to decide
            // whether Edit means "change this" or "make your own copy of this".
            'sub_institute_id' => $row->sub_institute_id === null ? null : (int) $row->sub_institute_id,
            'is_platform' => $row->sub_institute_id === null,
            'editable_in_place' => ! ($row->sub_institute_id === null && $institute !== null && $institute !== ''),

            // What this template does in its module, which is the question an
            // administrator opening this screen actually has.
            'offered_in_module' => $binding !== null && $binding['status'] === 1,
            'offer_label' => $binding['label'] ?? null,
            'offer_module_key' => $binding['module_key'] ?? null,

            // Honest warnings rather than a validation error: a template with no
            // grounding variable may be deliberate (a drafting prompt), but far more
            // often it is an author who forgot to include the data.
            //
            // Only meaningful for a prompt. A report layout's placeholders are filled
            // from whatever columns its data source returns, which are not knowable
            // until it runs — so reporting them as "unresolvable" here would flag every
            // correct layout. `ReportLayoutRenderer` reports the real unresolved set at
            // render time, when the columns are known.
            'grounding_variables' => $kind === 'report' ? [] : array_values($usedGrounding),
            'unresolvable_variables' => $kind === 'report'
                ? []
                : $this->variables->unresolvable($userPrompt . ' ' . $systemPrompt, $variables),

            'updated_at' => $row->updated_at === null ? null : (string) $row->updated_at,
        ];
    }

    /**
     * The columns a create and an update both write.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function columns(array $data, string $templateKey, ?string $moduleKey, int $version): array
    {
        return [
            'template_key' => $templateKey,
            'name' => trim((string) $data['name']),
            'description' => $this->nullable($data['description'] ?? null),
            'domain' => trim((string) ($data['domain'] ?? 'k12')) ?: 'k12',
            'module_key' => $moduleKey,
            'kind' => (string) ($data['kind'] ?? 'prompt'),
            'html_layout' => $this->nullable($data['html_layout'] ?? null),
            'data_source' => $this->nullable($data['data_source'] ?? null),
            'data_arguments' => $this->encode($data['data_arguments'] ?? []),
            'category' => $this->nullable($data['category'] ?? null),
            'version' => $version,
            'status' => (string) ($data['status'] ?? 'draft'),
            'system_prompt' => $this->nullable($data['system_prompt'] ?? null),
            'user_prompt' => (string) $data['user_prompt'],
            'variables' => $this->encode($data['variables'] ?? []),
            'output_schema' => $this->encode($data['output_schema'] ?? null),
            'output_format' => (string) ($data['output_format'] ?? 'text'),
            'provider' => $this->nullable($data['provider'] ?? null),
            'model' => $this->nullable($data['model'] ?? null),
            // `?? null` first: Laravel's `nullable` rules leave an omitted field out of
            // the validated array entirely, so reading the key before defaulting it
            // raises on every payload that does not mention temperature at all.
            'temperature' => ($data['temperature'] ?? null) === null || $data['temperature'] === ''
                ? null
                : (float) $data['temperature'],
            'max_tokens' => ($data['max_tokens'] ?? null) === null || $data['max_tokens'] === ''
                ? null
                : (int) $data['max_tokens'],
            'safety_rules' => $this->encode($data['safety_rules'] ?? []),
            'allow_as_evidence' => (bool) ($data['allow_as_evidence'] ?? false),
            'requires_review' => (bool) ($data['requires_review'] ?? false),
        ];
    }

    /**
     * The key a new template gets when the author does not supply one.
     *
     * Built as `<domain>.<module>.<name>` so the convention the existing thirteen rows
     * follow keeps holding, and the key still reads as something when it shows up in
     * `ai_generation_requests` six months from now.
     *
     * @param array<string, mixed> $data
     */
    private function resolveKey(array $data, ?string $moduleKey, int|string|null $institute): string
    {
        $supplied = trim((string) ($data['template_key'] ?? ''));

        if ($supplied !== '') {
            return $supplied;
        }

        $domain = trim((string) ($data['domain'] ?? 'k12')) ?: 'k12';
        $slug = $this->slug((string) ($data['name'] ?? 'template'));
        $module = $moduleKey === null ? 'shared' : $this->slug($moduleKey);

        $base = mb_substr("{$domain}.{$module}.{$slug}", 0, 110);
        $candidate = $base;
        $suffix = 2;

        // A duplicate key would collide on `ai_templates_key_ver_tenant_unique` at a
        // depth where the error means nothing to the person who typed a name.
        while ($this->keyExists($candidate, $institute)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function keyExists(string $templateKey, int|string|null $institute): bool
    {
        return DB::table('ai_templates')
            ->where('template_key', $templateKey)
            ->where(function ($inner) use ($institute) {
                $inner->whereNull('sub_institute_id');

                if ($institute !== null && $institute !== '') {
                    $inner->orWhere('sub_institute_id', $institute);
                }
            })
            ->exists();
    }

    private function nextVersion(string $templateKey, int|string|null $institute): int
    {
        $scope = $institute === '' ? null : $institute;

        $current = DB::table('ai_templates')
            ->where('template_key', $templateKey)
            ->where(function ($inner) use ($scope) {
                $scope === null ? $inner->whereNull('sub_institute_id') : $inner->where('sub_institute_id', $scope);
            })
            ->max('version');

        return ((int) $current) + 1;
    }

    private function nextSortOrder(string $moduleKey): int
    {
        $current = DB::table('ai_suggestions')
            ->where('module_key', $moduleKey)
            ->where('capability', 'generative')
            ->max('sort_order');

        return ((int) $current) + 10;
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';

        return trim($slug, '_') ?: 'template';
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
