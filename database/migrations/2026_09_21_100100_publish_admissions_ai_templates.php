<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Admissions module's prompts and report layouts.
 *
 * WHAT A PROMPT IS AND WHAT A REPORT IS, AND WHY THEY ARE BOTH HERE
 *
 * Both are `ai_templates` rows with `module_key = 'admissions'`, separated by `kind`.
 * A prompt is text sent to a model, which writes prose and may not state a figure on its
 * own authority. A report is an HTML layout whose placeholders are filled by substitution
 * from rows a tool fetched, with no model anywhere near the numbers. That is why the
 * reports below can print an enquiry number and the prompts below cannot.
 *
 * NO FAMILY, NO ENQUIRY NUMBER AND NO DATE APPEARS IN THIS FILE
 *
 * Every value is a placeholder. The cohort prompts are filled by `ModuleToolData`, which
 * reads the module's bound read tools — `admissions.today` and `admissions.listEnquiries`
 * — as the person who asked, scoped to their institute and academic year. The per-enquiry
 * prompts are filled by the screen that opened them from the record in front of the
 * operator. The reports are filled by `AiReportGenerator` from whatever the bound tool
 * returns. There is no sample enquiry and no seeded child anywhere in the AI stack, and
 * there must not be.
 *
 * EVERY PLACEHOLDER IN A LAYOUT IS A FIELD THE TOOL REALLY RETURNS
 *
 * `AdmissionMcpService::listEnquiries()` maps each row to exactly
 * `enquiry_id, enquiry_no, student_name, mobile, standard_id, standard_name, status,
 * followup_date` and reports `count` beside them; `AdmissionsTodayService` maps
 * `id, enquiry_id, enquiry_no, enrollment_no, mother_name, mother_mobile_number,
 * payment_mode, amount, admission_date, admission_status, created_by, created_on, remarks`
 * and reports `date` and `count`. The two layouts below use those names and nothing else,
 * so no cell can render blank because somebody guessed at a column.
 *
 * GROUNDING IS DECLARED, NOT ASSUMED
 *
 * `GroundingCheck` refuses a generation when every variable marked `grounding` is empty —
 * which is what stops a model writing "admissions are going well this year" from an empty
 * prompt. So the cohort prompts mark `records` and `metrics`, which are what
 * `ModuleToolData` fills, and the per-enquiry prompts mark the fields their screen
 * genuinely supplies. Getting this wrong is not cosmetic: publishing a summary prompt and
 * then asking it to write one family's follow-up is exactly the mismatch that made every
 * Fees remark refuse with a 422, and it is avoided here by publishing both kinds up front.
 *
 * THE SAFETY RULES ARE ABOUT FAMILIES WHO HAVE NOT YET BEEN ADMITTED
 *
 * An enquiry is a family that has asked about a place. Nothing in the record says whether
 * they will get one, and a message that implies a seat has been held — or that one has
 * been refused — is a promise the school has not made. So the rules below forbid stating
 * an admission outcome as firmly as they forbid inventing a date, and every family-facing
 * prompt requires a person to read it before it is sent.
 *
 * FEES AND ATTENDANCE ARE NOT TOUCHED. Every write is keyed on a template key beginning
 * `k12.admissions.`, which does not exist yet, and every suggestion row is written with
 * `module_key = 'admissions'`.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_21_100100_publish_admissions_ai_templates.php
 */
return new class extends Migration
{
    private const MODULE = 'admissions';

    private const PIPELINE_REPORT_KEY = 'k12.admissions.enquiry_pipeline_report';

    private const TODAY_REPORT_KEY = 'k12.admissions.todays_registrations_report';

    private const DATA_SOURCE = 'admissions.listEnquiries';

    private const TODAY_DATA_SOURCE = 'admissions.today';

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasColumn('ai_templates', 'kind')) {
            return;
        }

        foreach ($this->templates() as $template) {
            $existing = DB::table('ai_templates')
                ->where('template_key', $template['template_key'])
                ->whereNull('sub_institute_id')
                ->first();

            if ($existing !== null) {
                // Never touch a version an institute has forked: those rows carry a
                // sub_institute_id and are excluded by the query above.
                DB::table('ai_templates')->where('id', $existing->id)->update($template + ['updated_at' => now()]);

                continue;
            }

            DB::table('ai_templates')->insert($template + ['created_at' => now(), 'updated_at' => now()]);
        }

        $this->bindSuggestions();

        // Admissions could not generate before this migration, because it had no template
        // to generate from. It can now, so the flag catches up with the fact.
        $this->setModuleCapabilities(['generative' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $keys = array_column($this->templates(), 'template_key');

        if (Schema::hasTable('ai_suggestions')) {
            // Matched on label, because the report action deliberately names no template
            // and so cannot be found by `action_ref`. The labels are the ones written by
            // bindSuggestions() and nothing else uses them.
            DB::table('ai_suggestions')
                ->where('module_key', self::MODULE)
                ->whereIn('label', array_column($this->suggestions(), 2))
                ->whereNull('sub_institute_id')
                ->delete();
        }

        // Retired, not deleted: a generation request row references the template it ran
        // against, and deleting the row would orphan the audit trail that explains what
        // was written and from which prompt.
        DB::table('ai_templates')
            ->whereIn('template_key', $keys)
            ->whereNull('sub_institute_id')
            ->update(['status' => 'retired', 'updated_at' => now()]);

        $this->setModuleCapabilities(['generative' => false]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $cohortRules = json_encode([
            'Do not invent an enquiry number, a family name, a date, a class or a count.',
            'Do not state or imply whether an admission will be granted or refused. The record holds an enquiry, not a decision.',
            'Do not describe the pipeline as empty when no records were provided; say only that none were.',
            'Say when the figures cover a sample rather than every enquiry.',
        ]);

        $familyRules = json_encode([
            'Do not invent an enquiry number, a family name, a date, a class or a fee.',
            'Do not promise a seat, a date of admission, a fee amount or an outcome of any kind.',
            'Do not pressure the family or imply that a place will be lost. This text may be read by a parent.',
            'Ask rather than assert: the school does not yet know whether the family intends to proceed.',
        ]);

        return [
            // ---- Cohort prompts, grounded by ModuleToolData ----------------------
            [
                'template_key' => 'k12.admissions.summary',
                'name' => 'Admissions summary',
                'description' => 'A short summary of the admission enquiries on the screen.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You summarise admission enquiries for an admissions officer. Work only from '
                    . 'the records given below. Never state an enquiry number, a family name, a class or a date '
                    . 'that is not in them, and never estimate a count. An enquiry is a family that has asked '
                    . 'about a place — it is not an admission, and you must never describe one as though it '
                    . 'were decided.',
                'user_prompt' => "Summarise the admission enquiries on this page.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Search: {{search_query}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. How many enquiries are listed and what status each is in.\n"
                    . "2. Which standards or classes the enquiries are for.\n"
                    . "3. Which enquiries carry a follow-up date, and which carry none at all.\n\n"
                    . 'If no enquiry records were reported, say that none were provided.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Enquiry records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Admission figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                    ['key' => 'search_query', 'label' => 'Search', 'required' => false, 'type' => 'string'],
                    ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                    ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                    ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $cohortRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => 'k12.admissions.pending_analysis',
                'name' => 'Pending admissions analysis',
                'description' => 'Which enquiries have stalled, and what the records do and do not support '
                    . 'saying about them.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You analyse an admissions pipeline for an admissions officer. Work only '
                    . 'from the records given. Never state an enquiry number, a family name, a class or a date '
                    . 'that is not in them. Distinguish clearly between an enquiry that has been followed up '
                    . 'and is waiting on the family, and one nobody has touched — the two look the same in a '
                    . 'status column and mean opposite things about the school.',
                'user_prompt' => "Analyse the pending admission enquiries in these records.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. Which enquiries are still open, grouped by the status each record carries.\n"
                    . "2. Which of them have a follow-up date recorded and which have none.\n"
                    . "3. Whether any standard is over-represented among the ones still open.\n"
                    . "4. One or two concrete next steps the admissions office could take this week.\n\n"
                    . 'If no enquiry records were reported, say that none were provided rather than that the '
                    . 'pipeline is clear.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Enquiry records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Admission figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                    ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                    ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                    ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $cohortRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => 'k12.admissions.intake_explanation',
                'name' => 'Admissions intake explanation',
                'description' => 'Explains what the admission figures on screen do and do not show.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You explain admission figures to a school leader. Work only from the '
                    . 'records given. A trend needs more than one period: if the data covers a single intake or '
                    . 'a single day, say that no trend can be read from it rather than describing one. Never '
                    . 'invent a comparison year, and never state a conversion rate the records do not contain.',
                'user_prompt' => "Explain what these admission figures show.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Window and filters: {{filters}}\n"
                    . "Figures: {{metrics}}\n"
                    . "Rows:\n{{records}}\n\n"
                    . 'Say what the figures support concluding, what they do not, and what would have to be '
                    . 'recorded for the remaining question to be answerable.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Enquiry records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Admission figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $cohortRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],

            // ---- Per-enquiry prompts, grounded by the screen that opens them ------
            [
                'template_key' => 'k12.admissions.enquiry_follow_up',
                'name' => 'Admission enquiry follow-up to a family',
                'description' => 'A short follow-up message to one family that enquired about a place.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'message',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write short messages from a school admissions office to a family that '
                    . 'has enquired about a place. Work only from the details given; never state an enquiry '
                    . 'number, a name, a class, a date or a fee that is not in them. The school has not decided '
                    . 'anything yet, so never promise a seat and never imply one is about to be lost. Warm, '
                    . 'brief and specific. Return the message only — no preamble, no quotes, no labels.',
                'user_prompt' => "Write the follow-up message.\n\n"
                    . "Applicant: {{student_name}}\n"
                    . "Enquiry number: {{enquiry_no}}\n"
                    . "Standard enquired for: {{standard_name}}\n"
                    . "Current status: {{enquiry_status}}\n"
                    . "Follow-up date recorded: {{followup_date}}\n"
                    . "What this message is about: {{purpose}}\n\n"
                    . 'Two or three sentences. Address the parent, not the applicant. Invite them to tell the '
                    . 'office if they would like to go ahead or if they have questions.',
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Applicant', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'enquiry_no', 'label' => 'Enquiry number', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'enquiry_status', 'label' => 'Status', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'standard_name', 'label' => 'Standard', 'required' => false, 'type' => 'string'],
                    ['key' => 'followup_date', 'label' => 'Follow-up date', 'required' => false, 'type' => 'string'],
                    ['key' => 'purpose', 'label' => 'What it is about', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $familyRules,
                // A message to a family about their child's place goes outside the school.
                // Somebody reads it before it is sent.
                'requires_review' => 1,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => 'k12.admissions.officer_note',
                'name' => 'Admission enquiry note for the office',
                'description' => 'An internal note on one enquiry: what the record holds, what is missing, '
                    . 'and the next step.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'note',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write short internal notes for an admissions office about one enquiry. '
                    . 'Work only from the details given; never state a number, a name, a class or a date that '
                    . 'is not in them. This is an internal note, so it may be direct — but it still may not '
                    . 'assume an outcome. Suggest what to find out, not what to conclude. Return the note only.',
                'user_prompt' => "Draft the note.\n\n"
                    . "Applicant: {{student_name}}\n"
                    . "Enquiry number: {{enquiry_no}}\n"
                    . "Standard enquired for: {{standard_name}}\n"
                    . "Current status: {{enquiry_status}}\n"
                    . "Follow-up date recorded: {{followup_date}}\n"
                    . "Details still missing from the record: {{missing_fields}}\n"
                    . "Anything already known: {{context}}\n\n"
                    . 'Three or four lines: what the record shows, what is not yet known, and the next step.',
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Applicant', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'enquiry_no', 'label' => 'Enquiry number', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'enquiry_status', 'label' => 'Status', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'standard_name', 'label' => 'Standard', 'required' => false, 'type' => 'string'],
                    ['key' => 'followup_date', 'label' => 'Follow-up date', 'required' => false, 'type' => 'string'],
                    ['key' => 'missing_fields', 'label' => 'Missing details', 'required' => false, 'type' => 'string'],
                    ['key' => 'context', 'label' => 'Already known', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $familyRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],

            // ---- Report layouts, filled by substitution from the admissions tools --
            [
                'template_key' => self::PIPELINE_REPORT_KEY,
                'name' => 'Admission Enquiry Report',
                'description' => 'The admission enquiries on file, newest first, with the status and '
                    . 'follow-up date each record carries.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'report',
                // Version 2, and the reason is `ReportTemplateResolver::find()`.
                //
                // It resolves ONE layout per module — school row first, then highest
                // version — so two published platform layouts at the same version would
                // leave the module's default report to whatever order MySQL happened to
                // return. The version is the only tiebreak the resolver offers, so it is
                // what decides: this is the layout an admissions report is built with, and
                // the day sheet below sits at version 1 as the alternative a school can
                // promote from the Templates tab.
                'version' => 2,
                'status' => 'published',
                // NOT NULL on this table, and a report has no prompt.
                'user_prompt' => '',
                'html_layout' => $this->enquiryPipelineLayout(),
                'data_source' => self::DATA_SOURCE,
                // The floor the question's own arguments are merged over. `only_pending`
                // is false here on purpose: a report of the pipeline that silently hid
                // everything already converted would be a report nobody could reconcile
                // with the enquiry screen.
                'data_arguments' => json_encode(['only_pending' => false, 'limit' => 100]),
                'output_format' => 'text',
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => self::TODAY_REPORT_KEY,
                'name' => 'Admission Registrations Day Sheet',
                'description' => 'The admission registrations recorded on one date, with the payment mode '
                    . 'and amount each row carries.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'report',
                'version' => 1,
                'status' => 'published',
                'user_prompt' => '',
                'html_layout' => $this->todaysRegistrationsLayout(),
                'data_source' => self::TODAY_DATA_SOURCE,
                'data_arguments' => json_encode(['limit' => 100]),
                'output_format' => 'text',
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
        ];
    }

    /**
     * The suggestion rows, as [action_type, action_ref, label, description].
     *
     * Declared once so `down()` can remove exactly what `up()` wrote.
     *
     * @return array<int, array{0:string,1:?string,2:string,3:string}>
     */
    private function suggestions(): array
    {
        return [
            ['generate', 'k12.admissions.summary', 'Summarise admissions', 'Summarise the admission enquiries on this page from the enquiry records.'],
            ['generate', 'k12.admissions.pending_analysis', 'Analyse pending admissions', 'Identify which enquiries are still open, and what the records support saying.'],
            ['generate', 'k12.admissions.intake_explanation', 'Explain admission figures', 'Explain what these admission figures do and do not show.'],
            ['report', null, 'Admission report', 'Build the admission report document from the enquiry records.'],
        ];
    }

    /**
     * Offer the module's actions in the Admissions AI panel.
     *
     * `ai_templates` stores a template; `ai_suggestions` is what makes the module's panel
     * offer it as a button. Both are needed, which is why they are written together.
     *
     * THREE PROSE ACTIONS AND ONE DOCUMENT ACTION, AND THE SPLIT IS DELIBERATE
     *
     * `action_type = 'generate'` renders a *prompt* and hands back text to read — right
     * for a summary, an analysis and an explanation. `action_type = 'report'` builds a
     * saved document that `/ai-reports/{id}` previews, edits, refreshes, prints and sends,
     * and it names no template: `AiReportGenerator` resolves the module's layout itself.
     * That is the same correction 2026_09_18_120000 made for Fees, applied here from the
     * start so admissions never ships the wrong one.
     */
    private function bindSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        foreach ($this->suggestions() as $index => [$actionType, $ref, $label, $description]) {
            $exists = DB::table('ai_suggestions')
                ->where('module_key', self::MODULE)
                ->where('capability', 'generative')
                ->where('label', $label)
                ->whereNull('sub_institute_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('ai_suggestions')->insert([
                'module_key' => self::MODULE,
                'capability' => 'generative',
                'label' => $label,
                'description' => $description,
                'icon' => null,
                'action_type' => $actionType,
                'action_ref' => $ref,
                'prompt' => null,
                'payload' => null,
                // Cohort actions, not per-record ones: offered on the admission list pages
                // rather than gated behind selecting one enquiry.
                'requires_entity' => false,
                'allowed_roles' => null,
                'required_permissions' => null,
                'sort_order' => ($index + 1) * 10,
                'status' => 1,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Flip only the named flags, merging into whatever the row already holds.
     *
     * Writing a freshly built object over `capabilities` would enable these by silently
     * deleting any other flag the estate carries.
     *
     * @param  array<string, bool>  $flags
     */
    private function setModuleCapabilities(array $flags): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', self::MODULE)->get(['id', 'capabilities']);

        foreach ($rows as $row) {
            $existing = json_decode((string) $row->capabilities, true);

            if (! is_array($existing)) {
                $existing = ['conversational' => true];
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'capabilities' => json_encode(array_merge($existing, $flags)),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Placeholders come in three kinds, and this uses all three:
     *
     *   `<<report_title>>`   report-level. One value per report.
     *   `<<count>>`          a figure the DATA SOURCE computed, carried through by
     *                        `AiReportGenerator::scalarsOf()`.
     *   `<<#rows>> … <</rows>>`  repeated once per enquiry the tool returned.
     *
     * Written with plain `<<token>>` delimiters. The HTML editor stores the same
     * placeholders HTML-encoded when a person edits this in the browser, and the renderer
     * accepts either spelling — so editing and re-saving does not break it.
     */
    private function enquiryPipelineLayout(): string
    {
        return <<<'HTML'
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> enquiry(ies) listed, newest first &middot; generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Enquiry no.</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Applicant</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Standard</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Mobile</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Status</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Follow-up</th>
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        <td style="border:1px solid #cbd5e1;padding:6px"><<row_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<enquiry_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<student_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<standard_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<mobile>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<status>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<followup_date>></td>
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>Enquiries matched: <<count>></strong>
    &mdash; every row is an enquiry on file for this institute and academic year. An enquiry
    is a family that has asked about a place; it records no decision either way.
  </p>

</div>
HTML;
    }

    private function todaysRegistrationsLayout(): string
    {
        return <<<'HTML'
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> registration(s) recorded on <<date>> &middot; generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Enquiry no.</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Enrollment no.</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Mother</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Mobile</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Mode</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Amount</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Status</th>
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        <td style="border:1px solid #cbd5e1;padding:6px"><<row_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<enquiry_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<enrollment_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<mother_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<mother_mobile_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<payment_mode>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<amount>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<admission_status>></td>
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>Registrations on <<date>>: <<count>></strong>
    &mdash; read from the admission registration records for this institute.
  </p>

</div>
HTML;
    }
};
