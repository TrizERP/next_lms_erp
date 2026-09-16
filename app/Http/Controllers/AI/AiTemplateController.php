<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\AI\Templates\InstituteBranding;
use App\Domain\AI\Templates\ReportDataSourceCatalog;
use App\Domain\AI\Templates\ReportLayoutRenderer;
use App\Domain\AI\Templates\TemplateCatalog;
use App\Domain\AI\Templates\TemplateModuleCatalog;
use App\Domain\AI\Templates\TemplateVariableCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Template Management — one screen for every module's AI templates.
 *
 * WHAT THIS REPLACES
 *
 * Shipping a Fees template used to mean writing a migration: one insert into
 * `ai_templates` for the prompt, another into `ai_suggestions` so the Fees panel would
 * offer it, and a deploy to get both onto the estate. Doing the same for Attendance
 * meant a second pair of migrations that differed only in their strings. That is the
 * pattern this controller ends — the module is a field on a form, not a file.
 *
 * ONE SHAPE FOR EVERY MODULE
 *
 * There is no per-module endpoint and no per-module payload. `index` takes a
 * `module_key` and filters; every other route is module-agnostic and reads the module
 * off the record. The modules themselves come from `ai_modules` via
 * `TemplateModuleCatalog`, so a module added to that table appears in the selector
 * with no change here and no change in the UI. That is the requirement that a new
 * module must not need a new screen, expressed as an absence of code rather than a
 * promise.
 *
 * TENANT SCOPE COMES FROM THE TOKEN
 *
 * Like `AiConfigurationController`, every read is filtered by
 * `$this->scope($request)->selectedInstituteId` and every write is stamped with it.
 * The institute is never read from input, so a caller cannot write a template into
 * another school by naming one. Platform templates — the shared baseline every school
 * resolves — are visible to all and editable by none: an edit writes that school its
 * own copy instead. See `TemplateCatalog::update()`.
 */
class AiTemplateController extends AiController
{
    public function __construct(
        private readonly TemplateCatalog $templates,
        private readonly TemplateModuleCatalog $modules,
        private readonly TemplateVariableCatalog $variables,
        private readonly ReportDataSourceCatalog $dataSources,
        private readonly ReportLayoutRenderer $layoutRenderer,
        private readonly InstituteBranding $branding,
        private readonly AiAuditLogger $audit,
    ) {
    }

    /**
     * Everything the screen needs to render before a module is chosen.
     *
     * One call rather than four. The module list, the variable catalogue, the statuses
     * and the category suggestions are all useless individually — the form cannot be
     * drawn until it has all of them — and four round trips is four chances to render
     * a form with an empty dropdown.
     */
    public function options(Request $request)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            return $this->success('Template options resolved.', [
                'modules' => $this->modules->all($institute),
                'shared_key' => TemplateModuleCatalog::SHARED,
                'variables' => $this->variables->all(),
                'grounding_variables' => $this->variables->groundingKeys(),
                'statuses' => TemplateCatalog::STATUSES,
                'kinds' => TemplateCatalog::KINDS,
                'output_formats' => TemplateCatalog::OUTPUT_FORMATS,
                'categories' => TemplateCatalog::SUGGESTED_CATEGORIES,

                // For report templates: which MCP tools can feed a layout, and the
                // placeholders a layout may use. Both come from the live registry, so a
                // tool added to the platform is bindable here without a change to this
                // controller or to the screen.
                // The signed-in school's own name and logo, read from its fee receipt
                // letterhead. Lets a template screen show whose templates these are
                // without any caller hardcoding a name or an image path.
                'branding' => $this->branding->forInstitute($institute),

                'data_sources' => $this->dataSources->all(),
                'report_placeholders' => $this->layoutRenderer->placeholders(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The templates for the selected module.
     *
     * `module_key` is optional on purpose: without it the screen lists every template
     * the school can see, which is the view an administrator wants when the question is
     * "what exists at all" rather than "what does Fees have".
     */
    public function index(Request $request)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            $validated = $request->validate([
                'module_key' => 'nullable|string|max:60',
            ]);

            $moduleKey = $validated['module_key'] ?? null;

            if ($moduleKey !== null && $moduleKey !== '' && ! $this->modules->exists($moduleKey, $institute)) {
                return $this->failure('That module is not one this school has.', 404);
            }

            $templates = $this->templates->forModule($moduleKey, $institute);

            return $this->success('Templates resolved.', [
                'sub_institute_id' => $institute,
                'module_key' => $moduleKey,
                'module_label' => $moduleKey === null ? 'All modules' : $this->modules->label($moduleKey, $institute),
                'templates' => $templates,
                // So the screen can say "3 of 14 are live in this module" without
                // counting client-side and disagreeing with the next page of results.
                'counts' => [
                    'total' => count($templates),
                    'published' => count(array_filter($templates, fn ($row) => $row['status'] === 'published')),
                    'offered' => count(array_filter($templates, fn ($row) => $row['offered_in_module'])),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One template in full, for the view and edit screens. */
    public function show(Request $request, int $id)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;
            $template = $this->templates->find($id, $institute);

            if ($template === null) {
                return $this->failure('That template could not be found.', 404);
            }

            return $this->success('Template resolved.', ['template' => $template]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Create a template and, when it is published against a module, offer it there. */
    public function store(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $data = $this->validated($request, $institute);
            $data['created_by'] = $scope->userId;

            $id = $this->templates->create($data, $institute, $scope->clientId);

            $this->audit->record('ai.template.created', $scope, [
                'related_type' => 'ai_templates',
                'related_id' => $id,
                'message' => sprintf(
                    'Template "%s" created for %s.',
                    $data['name'],
                    $this->modules->label($data['module_key'] ?? null, $institute)
                ),
            ]);

            return $this->success('Template saved.', [
                'template' => $this->templates->find($id, $institute),
            ], 201);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Change a template.
     *
     * `new_version` asks for the change to land as a new version with the previous one
     * archived, rather than as an edit in place. Worth offering on a published template
     * an estate is already using: the old text stays recoverable, so a prompt that
     * turns out worse can be rolled back by republishing the version before it.
     */
    public function update(Request $request, int $id)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $existing = $this->templates->find($id, $institute);

            if ($existing === null) {
                return $this->failure('That template could not be found.', 404);
            }

            $data = $this->validated($request, $institute, $id);
            $data['created_by'] = $scope->userId;

            $result = $this->templates->update(
                $id,
                $data,
                $institute,
                $scope->clientId,
                (bool) $request->boolean('new_version')
            );

            $this->audit->record('ai.template.' . $result['action'], $scope, [
                'related_type' => 'ai_templates',
                'related_id' => $result['id'],
                'message' => sprintf('Template "%s" %s.', $data['name'], $result['action']),
            ]);

            return $this->success(match ($result['action']) {
                'overridden' => 'This school now has its own version of the platform template. '
                    . 'The shared one is unchanged for every other school.',
                'versioned' => 'A new version was published. The previous one is archived and can be restored.',
                default => 'Template updated.',
            }, [
                'template' => $this->templates->find($result['id'], $institute),
                'action' => $result['action'],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Retire a template and withdraw it from its module's panel. */
    public function destroy(Request $request, int $id)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $this->templates->archive($id, $institute);

            $this->audit->record('ai.template.archived', $scope, [
                'related_type' => 'ai_templates',
                'related_id' => $id,
                'message' => 'Template retired.',
            ]);

            return $this->success('Template retired.', ['id' => $id]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Render the prompts with sample values, without calling a model.
     *
     * The question an author has at this point is about their own prompt — did the
     * placeholder land where I meant it to, is anything still unresolved — and that is
     * answerable with string substitution. Running a generation to answer it would cost
     * a call, take seconds, and fail entirely on an estate where no provider is
     * configured, which is a poor way to check a typo.
     */
    public function preview(Request $request)
    {
        try {
            $validated = $request->validate([
                'system_prompt' => 'nullable|string|max:20000',
                'user_prompt' => 'required|string|max:20000',
                'values' => 'nullable|array',
            ]);

            $values = array_merge($this->sampleValues(), $validated['values'] ?? []);

            $rendered = $this->templates->preview(
                (string) ($validated['system_prompt'] ?? ''),
                (string) $validated['user_prompt'],
                $values
            );

            return $this->success('Prompt rendered.', $rendered + ['values' => $values]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Validation shared by create and update.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, int|string|null $institute, ?int $ignoreId = null): array
    {
        $moduleKeys = array_merge([TemplateModuleCatalog::SHARED], $this->modules->keys($institute));

        // A report needs a layout and a data source; a prompt needs a user prompt.
        // Read before the rules are built so each kind is asked only for what it uses —
        // requiring a user prompt on a layout would make the screen demand a field it
        // does not show.
        $kind = (string) $request->input('kind', 'prompt');
        $isReport = $kind === TemplateCatalog::KINDS[1];

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'kind' => ['nullable', Rule::in(TemplateCatalog::KINDS)],
            // Write the template for the whole estate rather than only this school.
            // The Fees template screen sets it, because Fees templates are meant to be
            // usable by anyone who opens the Fees module whatever their sub_institute_id.
            'shared' => 'nullable|boolean',

            // Report-only. `data_source` must name a tool that is registered *and*
            // read-only — `ReportDataSourceCatalog::isBindable()` answers both, so a
            // layout can never be bound to something that writes. Generating or
            // refreshing a report re-runs this call, and a report that changed the
            // school's records every time somebody opened it would be a serious bug.
            'html_layout' => [$isReport ? 'required' : 'nullable', 'string', 'max:200000'],
            'data_source' => [
                $isReport ? 'required' : 'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, callable $fail) {
                    if ($value !== null && $value !== '' && ! $this->dataSources->isBindable((string) $value)) {
                        $fail('That data source is not an available read-only tool.');
                    }
                },
            ],
            'data_arguments' => 'nullable|array',
            // Optional: generated from the module and name when absent, so an
            // administrator writing their first template never has to invent a
            // dotted key or know the convention behind one.
            'template_key' => 'nullable|string|max:120|regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
            'module_key' => ['required', 'string', Rule::in($moduleKeys)],
            'domain' => 'nullable|string|max:40',
            'category' => 'nullable|string|max:60',
            'status' => ['required', Rule::in(TemplateCatalog::STATUSES)],

            'system_prompt' => 'nullable|string|max:20000',
            'user_prompt' => [$isReport ? 'nullable' : 'required', 'string', 'max:20000'],

            'variables' => 'nullable|array',
            'variables.*.key' => 'required|string|max:60|regex:/^[a-zA-Z0-9_.]+$/',
            'variables.*.label' => 'nullable|string|max:150',
            'variables.*.required' => 'nullable|boolean',
            'variables.*.type' => 'nullable|string|max:30',

            'output_format' => ['nullable', Rule::in(TemplateCatalog::OUTPUT_FORMATS)],
            'output_schema' => 'nullable|array',

            'provider' => 'nullable|string|max:40',
            'model' => 'nullable|string|max:120',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:200000',

            'safety_rules' => 'nullable|array',
            'safety_rules.*' => 'string|max:500',

            'allow_as_evidence' => 'nullable|boolean',
            'requires_review' => 'nullable|boolean',

            // The module binding, which is what makes the template reachable from the
            // module's AI panel rather than only stored.
            'offer_in_module' => 'nullable|boolean',
            'suggestion_label' => 'nullable|string|max:150',
            'requires_entity' => 'nullable|boolean',
        ]);

        $validated['kind'] = $kind;

        // `user_prompt` is NOT NULL on this table and a layout has none, so an empty
        // string rather than null. It reads back as null through `present()`.
        if ($isReport) {
            $validated['user_prompt'] ??= '';
        }

        // A published template whose prompt carries no data variable will be answered
        // from the model's general knowledge, which for a question about this school's
        // fees means a confident, invented number. Refused rather than warned: a
        // warning on a screen is not read by the person who meets the answer.
        //
        // Reports are exempt: nothing in a report is written by a model. Its figures
        // are substituted from rows its data source returned, so a layout with no
        // `{{records}}` is not ungrounded — it is a document with a `<<rows_table>>`.
        if (! $isReport && ($validated['status'] ?? 'draft') === 'published') {
            $prompt = ($validated['user_prompt'] ?? '') . ' ' . ($validated['system_prompt'] ?? '');
            $used = $this->variables->used($prompt);
            $declared = array_column($validated['variables'] ?? [], 'key');

            if (array_intersect($this->variables->groundingKeys(), array_merge($used, $declared)) === []) {
                throw ValidationException::withMessages([
                    'user_prompt' => [
                        'A published template must include at least one data variable — '
                        . implode(' or ', array_map(fn ($key) => '{{' . $key . '}}', $this->variables->groundingKeys()))
                        . ' — or the model has nothing to work from and will answer from general knowledge. '
                        . 'Save it as a draft if it is not finished.',
                    ],
                ]);
            }
        }

        $validated['template_key'] = $validated['template_key'] ?? null;

        return $validated;
    }

    /**
     * Stand-in values for the preview, shaped like what the runtime really supplies.
     *
     * Deliberately concrete and obviously fake. A preview filled with "lorem ipsum"
     * does not show an author whether their prompt reads well around real rows, and a
     * preview filled with this school's actual fee records would put live data on a
     * settings screen for no reason.
     *
     * @return array<string, string>
     */
    private function sampleValues(): array
    {
        return [
            'records' => "- Aarav Sharma (class: 5-A, head: Tuition, outstanding: ₹12,500, due: 2026-08-31)\n"
                . "- Diya Patel (class: 5-A, head: Transport, outstanding: ₹3,200, due: 2026-08-31)\n"
                . '- Kabir Nair (class: 6-B, head: Tuition, outstanding: ₹12,500, due: 2026-09-15)',
            'metrics' => 'Total outstanding: ₹28,200; Students with dues: 3; Collected this month: ₹1,45,000',
            'record_count' => '47',
            'rows_shown' => '3',
            'is_partial' => 'yes',
            'page_title' => 'Pending fees',
            'page_type' => 'list',
            'filters' => 'Class: 5-A, Term: Term 2',
            'search_query' => '',
            'data_source' => 'the page',
            'module' => 'Fees',
            'entity_label' => '',
        ];
    }
}
