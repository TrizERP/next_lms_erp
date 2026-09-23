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
                // Which module's sample values to render with. Optional, and omitting it
                // gives exactly the set this endpoint has always returned — so Fees,
                // Attendance and the central console are unaffected by its arrival.
                'module_key' => 'nullable|string|max:60',
            ]);

            $values = array_merge(
                $this->sampleValues($validated['module_key'] ?? null),
                $validated['values'] ?? []
            );

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
     * Deliberately concrete and obviously fake. A preview filled with "lorem ipsum" does
     * not show an author whether their prompt reads well around real rows, and a preview
     * filled with this school's actual records would put live data on a settings screen
     * for no reason.
     *
     * WHY THE SET IS PER MODULE
     *
     * There was one set, and it was fee rows: rupee amounts, fee heads, due dates. An
     * author writing an Admission prompt previewed it against "Aarav Sharma — outstanding
     * ₹12,500" and could not tell whether their placeholders had landed, because none of
     * the fields their prompt was about were in the sample. Worse, on a screen whose whole
     * claim is that Admissions shows only Admission data, the one visible row of data was
     * a fee.
     *
     * An unknown key, and `null`, return the original fee set unchanged — which is what
     * every existing caller sends. The central console, the Fees screen and the Attendance
     * screen all omit the key, and all three see exactly what they saw before.
     *
     * NONE OF THIS IS RUNTIME DATA. It is rendered into a preview that calls no model and
     * stores nothing, under a heading that says so. The alternative is not "no sample" —
     * it is fee rows in a hostel preview, which is what this replaced.
     *
     * @return array<string, string>
     */
    private function sampleValues(?string $moduleKey = null): array
    {
        $module = trim((string) $moduleKey);

        // Every set below carries the same keys, because `ModuleToolData` fills the same
        // variables whatever the module is. Only `records`, `metrics`, `page_title`,
        // `filters` and `module` differ — which is the whole point: the author has to see
        // their own module's fields to tell whether their placeholders landed.
        $sets = [
            'admissions' => [
                'records' => "- Meera Rao (enquiry: ENQ/2026/014, standard: 6, status: new, follow-up: 2026-08-02)\n"
                    . "- Arjun Desai (enquiry: ENQ/2026/015, standard: 1, status: contacted, follow-up: not recorded)\n"
                    . '- Nisha Kulkarni (enquiry: ENQ/2026/016, standard: 9, status: new, follow-up: 2026-09-09)',
                'metrics' => 'Enquiries listed: 3; Still open: 3; With a follow-up date: 2',
                'record_count' => '12',
                'page_title' => 'Admission enquiries',
                'filters' => 'Status: open',
                'module' => 'Admissions',
            ],

            // An absence carries no score, and the sample says so rather than showing a
            // zero — an author reading "0" would write a prompt that treats it as a mark.
            'exam' => [
                'records' => "- Aarav Sharma (exam: Term 1, subject: Mathematics, marks: 68/100, 68%, grade: B)\n"
                    . "- Diya Patel (exam: Term 1, subject: Mathematics, marks: 81/100, 81%, grade: A)\n"
                    . '- Kabir Nair (exam: Term 1, subject: Mathematics, absent — no marks recorded)',
                'metrics' => 'Entries listed: 3; Scored: 2; Absences: 1; Average across scored entries: 74.5%',
                'record_count' => '96',
                'page_title' => 'Exam results',
                'filters' => 'Exam: Term 1, Standard: 8',
                'module' => 'Exam',
            ],

            // One meeting with an unsaved register, because that is the state this
            // module's prompts most often get wrong.
            'ptm' => [
                'records' => "- PTM-II, 2026-08-14, 09:00–10:30, Standard 6 / A (booked 24, attended 19, did not attend 3, not recorded 2)\n"
                    . "- PTM-II, 2026-08-14, 11:00–12:30, Standard 6 / B (booked 21, attended 17, did not attend 4, not recorded 0)\n"
                    . '- PTM-II, 2026-08-15, 09:00–10:30, Standard 7 / A (booked 18, attendance not recorded for any)',
                'metrics' => 'Meetings listed: 3; Families booked: 63; Attendance recorded for: 43',
                'record_count' => '11',
                'page_title' => 'Parent-teacher meetings',
                'filters' => 'Standard: 6, Date: August 2026',
                'module' => 'PTM',
            ],

            // No capacity figure anywhere, because the schema holds none.
            'hostel' => [
                'records' => "- Rohan Iyer (student, enrolment 4417) — Senior Boys / Block A / 1st floor / room 104, bed B-2\n"
                    . "- Ishaan Bose (student, enrolment 4462) — Senior Boys / Block A / 1st floor / room 104, bed B-3\n"
                    . '- S. Menon (staff, warden) — Senior Boys / Block A / ground floor / room 002, bed B-1',
                'metrics' => 'Allocations listed: 3; Rooms occupied: 2; Occupants: 3 (2 students, 1 staff)',
                'record_count' => '58',
                'page_title' => 'Hostel allocations',
                'filters' => 'Hostel: Senior Boys',
                'module' => 'Hostel',
            ],

            // One pending, one approved, one waiting on a document — the three states an
            // author has to write for.
            'student_request' => [
                'records' => "- Ananya Reddy (7-B, enrolment 3120) — Name correction, raised 2026-07-04, Pending\n"
                    . "- Vivaan Shah (5-A, enrolment 2988) — Transfer certificate, raised 2026-06-28, Approved 2026-07-01\n"
                    . '- Sara Qureshi (9-C, enrolment 2741) — Address change, raised 2026-07-11, Pending (proof of address not supplied)',
                'metrics' => 'Requests listed: 3; Pending: 2; Approved: 1; Rejected: 0',
                'record_count' => '24',
                'page_title' => 'Student requests',
                'filters' => 'Status: all',
                'module' => 'Student requests',
            ],

            // Two rows of one notice, so the difference between rows and circulars is
            // visible in the sample rather than only in the rule.
            'circular' => [
                'records' => "- Annual sports day (Event, 2026-08-13) — Standard 7 / A, attachment: sports-day.pdf\n"
                    . "- Annual sports day (Event, 2026-08-13) — Standard 7 / B, attachment: sports-day.pdf\n"
                    . '- Half-yearly exam timetable (Circular, 2026-09-02) — Standard 9 / A, no attachment',
                'metrics' => 'Rows listed: 3; Separate circulars: 2; With an attachment: 2',
                'record_count' => '36',
                'page_title' => 'Circulars',
                'filters' => 'Date: this academic year',
                'module' => 'Circular',
            ],

            /*
            | The twelve modules whose AI Stacks were added after this method was first
            | written.
            |
            | They were falling through to the Fees default, which meant somebody writing a
            | Transport prompt pressed Preview and saw three children's fee arrears. Nothing
            | leaked — every string in this method is invented for the preview pane and none
            | of it is read from the school — but the pane exists so an author can tell
            | whether their placeholders landed on their own module's fields, and fee rows
            | cannot answer that for a timetable.
            |
            | The six from 2026-09-23 are here for the same reason as the six from
            | 2026-09-24: this is one shared screen, and fixing half of it would leave the
            | same defect under a different module name.
            */
            'mobile_apps' => [
                'records' => "- Attendance (section: Academics, profile: Parent, opens: attendance, enabled: yes)\n"
                    . "- Fee payment (section: Accounts, profile: Parent, opens: fees, enabled: yes)\n"
                    . '- Mark attendance (section: Academics, profile: Teacher, opens: mark-attendance, enabled: no)',
                'metrics' => 'Tiles listed: 3; Switched on: 2; Sections: 2',
                'record_count' => '41',
                'page_title' => 'Mobile app home screen',
                'filters' => 'Profile: Parent',
                'module' => 'Users Mobile Apps',
            ],
            'student_icard' => [
                'records' => "- Aarav Sharma (roll: 12, class: 5 / A, photo: yes, bus: Route 4, ready: yes)\n"
                    . "- Diya Patel (roll: 13, class: 5 / A, photo: no, bus: not recorded, ready: no)\n"
                    . '- Kabir Nair (roll: 4, class: 6 / B, photo: yes, bus: not recorded, ready: yes)',
                'metrics' => 'Students listed: 3; Ready to print: 2; Missing something: 1',
                'record_count' => '212',
                'page_title' => 'Student I-Card',
                'filters' => 'Class: 5-A',
                'module' => 'Student I-Card',
            ],
            'certificate' => [
                'records' => "- Aarav Sharma — Bonafide certificate (no. BON/2026/018, issued 2026-07-04, document stored)\n"
                    . "- Diya Patel — Transfer certificate (no. TC/2026/007, issued 2026-08-19, document stored)\n"
                    . '- Kabir Nair — Character certificate (no. not recorded, issued 2026-08-30, no document stored)',
                'metrics' => 'Certificates listed: 3; Types: 3; Missing a number: 1',
                'record_count' => '58',
                'page_title' => 'Certificates issued',
                'filters' => 'Year: this academic year',
                'module' => 'Certificate',
            ],
            'easy_com' => [
                'records' => "- SMS to parents — \"Half-yearly exams begin 12 Sept\" (2026-09-01, delivery: not recorded)\n"
                    . "- WhatsApp to parents — \"Fee due 15 Sept\" (2026-09-05, delivery: delivered)\n"
                    . '- App notification to staff — "Staff meeting 4pm" (2026-09-06, delivery: not recorded)',
                'metrics' => 'Messages listed: 3; Channels: 3; With a delivery outcome: 1',
                'record_count' => '1,204',
                'page_title' => 'Communication',
                'filters' => 'Channel: all',
                'module' => 'Communication',
            ],
            'timetable' => [
                'records' => "- Monday, Period 1 (08:00–08:45) — 7 / A — Mathematics — R Iyer\n"
                    . "- Monday, Period 2 (08:45–09:30) — 7 / A — English — S Menon\n"
                    . '- Monday, Period 3 (09:45–10:30) — 7 / A — Science — teacher not recorded',
                'metrics' => 'Entries listed: 3; Weekdays covered: 1; Missing a teacher: 1',
                'record_count' => '1,860',
                'page_title' => 'Class timetable',
                'filters' => 'Class: 7-A',
                'module' => 'Time Table',
            ],
            'student_medical' => [
                // Operational only — a date, a case number, whether it is open. No
                // complaint, symptom or treatment appears even in a preview fixture,
                // because the layout being authored must not learn that such a placeholder
                // would resolve for a cohort. It does not; see StudentMedicalService.
                'records' => "- Aarav Sharma — infirmary visit 2026-08-12 (case INF/2026/041, seen by Dr Rao, open: no)\n"
                    . "- Diya Patel — infirmary visit 2026-09-02 (case INF/2026/052, seen by Dr Rao, open: yes)\n"
                    . '- Kabir Nair — infirmary visit 2026-09-03 (case INF/2026/053, doctor not recorded, open: yes)',
                'metrics' => 'Visits listed: 3; Still open: 2; With a doctor recorded: 2',
                'record_count' => '96',
                'page_title' => 'Infirmary',
                'filters' => 'Open cases only',
                'module' => 'Student Medical',
            ],

            'inward_outward' => [
                'records' => "- 277 — Sadguru Flowers invoice (received 2026-05-04, from: Accounts / Bank, file: 56564, scan: yes)\n"
                    . "- 276 — Bassein Petrol invoice (received 2026-05-04, from: Accounts / Bank, file: not recorded, scan: yes)\n"
                    . '- 275 — Education board circular (received 2026-05-02, from: Board, file: 21140, scan: no)',
                'metrics' => 'Records listed: 3; With a scan: 2; No file location: 1',
                'record_count' => '277',
                'page_title' => 'Inward register',
                'filters' => 'Year: this academic year',
                'module' => 'Inward',
            ],
            'user_icard' => [
                'records' => "- R Iyer (employee no. EMP/041, profile: Teacher, department: Science, photo: yes, ready: yes)\n"
                    . "- S Menon (employee no. EMP/052, profile: Teacher, department: Languages, photo: no, ready: no)\n"
                    . '- P Joshi (employee no. not recorded, profile: Office staff, department: Accounts, photo: yes, ready: no)',
                'metrics' => 'Staff listed: 3; Cards ready: 1; Missing something: 2',
                'record_count' => '84',
                'page_title' => 'User I-Card',
                'filters' => 'Profile: Teacher',
                'module' => 'User I-Card',
            ],
            'petty_cash' => [
                'records' => "- Canteen expenses — milk and tea for the staff room — ₹157 (2026-09-06, bill: no)\n"
                    . "- Stationery — register and marker pens — ₹476 (2026-09-05, bill: yes)\n"
                    . '- Travel — auto fare to the education office — ₹180 (2026-09-04, bill: no)',
                'metrics' => 'Transactions listed: 3; Total spent: ₹813; Without a bill: 2',
                'record_count' => '124',
                'page_title' => 'Petty cash',
                'filters' => 'Month: September',
                'module' => 'Petty Cash',
            ],
            'consent' => [
                'records' => "- Picnic day at Vadodara — Greeva Rafaliya (5 / A, 2026-08-12, accountable, no decision recorded)\n"
                    . "- Picnic day at Vadodara — Aarav Sharma (5 / A, 2026-08-12, accountable, no decision recorded)\n"
                    . '- Science fair visit — Kabir Nair (6 / B, 2026-08-20, non-accountable, no decision recorded)',
                'metrics' => 'Consents listed: 3; Awaiting a decision: 3; Decision recorded: 0',
                'record_count' => '16',
                'page_title' => 'Consents',
                'filters' => 'Class: 5-A',
                'module' => 'Consent',
            ],
            'visitor_management' => [
                'records' => "- H Shah (parent, to meet: Principal, purpose: admission, 2026-09-02, in 10:59, out: not recorded)\n"
                    . "- D Chandak (official, to meet: N Patel, purpose: loan verification, 2026-09-02, in 11:45, out 12:27)\n"
                    . '- M Kulkarni (vendor, to meet: Accounts, purpose: delivery, 2026-09-01, in 09:20, out: not recorded)',
                'metrics' => 'Visits listed: 3; Exit recorded: 1; No exit recorded: 2',
                'record_count' => '463',
                'page_title' => 'Visitor report',
                'filters' => 'Date: this month',
                'module' => 'Visitor Management',
            ],
            'transportation' => [
                'records' => "- Route 4 — Althan, Vesu, Piplod (08:00–08:45) — bus GJ-05-AB-1234, 20 seats, 18 assigned\n"
                    . "- Route 7 — Adajan, Rander (08:10–08:50) — bus GJ-05-CD-5678, 24 seats, 26 assigned\n"
                    . '- Route 9 — Katargam (08:15–08:45) — no vehicle assigned',
                'metrics' => 'Routes listed: 3; Over capacity on one leg: 1; Without a vehicle: 1',
                'record_count' => '105',
                'page_title' => 'Transport routes',
                'filters' => 'Year: this academic year',
                'module' => 'Transport',
            ],

            /*
            | The six added in 2026-09-25.
            |
            | Three of them show what the module CANNOT say as much as what it can, because
            | an author writing a prompt needs to see the shape of the honest answer: the
            | inventory figure is a recorded stock column that is never decreased, the
            | complaint "solution" column holds a status word, and Utility here is bulk
            | data operations rather than electricity and water.
            */
            'inventory' => [
                'records' => "- 128GB SSD Drive (category: IT hardware, recorded stock: 2, reorder level: 5, approved for issue: 0)\n"
                    . "- Keyboard Mouse Combo (category: IT hardware, recorded stock: 11, reorder level: 4, approved for issue: 3)\n"
                    . '- A4 Paper Ream (category: Stationery, recorded stock: 0, reorder level: 20, approved for issue: 12)',
                'metrics' => 'Items listed: 3; At or below the recorded reorder level: 2; Recorded stock is NOT a shelf count',
                'record_count' => '284',
                'page_title' => 'Inventory items',
                'filters' => 'Category: IT hardware',
                'module' => 'Inventory',
            ],
            'front_desk' => [
                'records' => "- Parent visit about Abhi D. Raval — to meet A. Teotia (2026-07-25, in 14:16, out 14:29)\n"
                    . "- Vendor visit about (no student recorded) — to meet Accounts (2026-07-24, in 10:02, out: not recorded)\n"
                    . '- Parent visit about Diya Patel — to meet the Principal (2026-07-24, in 09:15, out 09:40)',
                'metrics' => 'Visits listed: 3; Exit recorded: 2; No exit recorded: 1',
                'record_count' => '3',
                'page_title' => 'Front desk',
                'filters' => 'Date: this week',
                'module' => 'Front Desk',
            ],
            'task_management' => [
                'records' => "- Action the handover to fee demand (due 2026-09-22, PENDING, assigned to K. Heda, 182 days past)\n"
                    . "- Collect hall ticket signatures (due 2026-09-30, COMPLETE, assigned to S. Menon)\n"
                    . '- File the inspection returns (due 2026-08-01, COMPLETED, assigned to P. Joshi)',
                'metrics' => 'Tasks listed: 3; Complete: 2 (across BOTH spellings); Open: 1; Overdue: 1',
                'record_count' => '795',
                'page_title' => 'My tasks',
                'filters' => 'State: any',
                'module' => 'Task Management',
            ],
            'complaint' => [
                'records' => "- RO water machine not working (raised 2026-08-11 by K. Sheth, status COMPLETE, closed by H. Rafaliya)\n"
                    . "- Projector bulb needs replacing (raised 2026-09-02 by S. Macwan, status PENDING)\n"
                    . '- Staff room fan noisy (raised 2026-09-05 by A. Pandey, status PENDING)',
                'metrics' => 'Complaints listed: 3; Closed: 1; Open: 2; No priority or SLA is recorded',
                'record_count' => '30',
                'page_title' => 'Complaints',
                'filters' => 'State: any',
                'module' => 'Complaint',
            ],
            'migration-modules' => [
                'records' => "- Book Lists (custom module, table Z_Book_Lists, type MASTER, 0 columns defined)\n"
                    . "- testCustomModule1 (custom module, table testCustomModule1, type MASTER, 4 columns defined)\n"
                    . '- Academic years with enrolments: 2025 (147), 2023 (7), 2022 (30), 2021 (3,262)',
                'metrics' => 'Custom modules: 6; Transfer targets in the same client: 0; Operation history: NOT recorded',
                'record_count' => '6',
                'page_title' => 'Utility',
                'filters' => 'Type: MASTER',
                'module' => 'Utility',
            ],
            'document-templates' => [
                // Deliberately shows what a filled register would look like while the real
                // tables are empty, so an author can tell whether their placeholders land.
                // The prompts themselves forbid describing templates a school might want.
                'records' => "- Bonafide letter (category: Letters, status: published, v3, fields: student_name, class, date)\n"
                    . "- Fee reminder (category: Letters, status: draft, v1, fields: student_name, amount, due_date)\n"
                    . '- Leaving certificate (category: Certificates, status: draft, v2, no content saved yet)',
                'metrics' => 'Templates listed: 3; Published: 1; Draft: 2; Without content: 1',
                'record_count' => '0',
                'page_title' => 'Document templates',
                'filters' => 'Status: all',
                'module' => 'Document Templates',
            ],
        ];

        $chosen = $sets[$module] ?? [
            'records' => "- Aarav Sharma (class: 5-A, head: Tuition, outstanding: ₹12,500, due: 2026-08-31)\n"
                . "- Diya Patel (class: 5-A, head: Transport, outstanding: ₹3,200, due: 2026-08-31)\n"
                . '- Kabir Nair (class: 6-B, head: Tuition, outstanding: ₹12,500, due: 2026-09-15)',
            'metrics' => 'Total outstanding: ₹28,200; Students with dues: 3; Collected this month: ₹1,45,000',
            'record_count' => '47',
            'page_title' => 'Pending fees',
            'filters' => 'Class: 5-A, Term: Term 2',
            'module' => 'Fees',
        ];

        // The keys no module varies, merged under the chosen set so a set cannot forget
        // one and leave an author looking at an unresolved placeholder.
        return $chosen + [
            'rows_shown' => '3',
            'is_partial' => 'yes',
            'page_type' => 'list',
            'search_query' => '',
            'data_source' => 'the page',
            'entity_label' => '',
        ];
    }
}
