<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Alumni Management as an AI module and publishes its prompts and report layout.
 *
 * Alumni already had its read tools (`alumni.directory`, `alumni.events`, `alumni.overview`)
 * and a config entry in `config/ai.php`, but no `ai_modules` row — and without one the
 * Prompts and Templates tabs answer "that module is not one this school has", the Policies
 * tab cannot resolve a scope, and usage reports the module unregistered. This adds the row,
 * then the same three templates every other module in this family carries: two cohort
 * prompts and one report layout, built exactly like 2026_09_24_100100.
 *
 * EVERY PLACEHOLDER IS A FIELD THE BOUND TOOL REALLY RETURNS
 *
 * `AlumniOverviewService::directory()` maps `name, batch, passing_year, status,
 * employment_status, employer, job_title` and carries the scalars `count` and
 * `with_employer_recorded` over every matching alumnus, not only the page.
 *
 * WHAT THE MODULE MUST REFUSE TO SAY
 *
 * Contact details, dates of birth, addresses and photographs are never returned, so nothing
 * may state or guess them. An empty employment status or employer means NOT RECORDED, never
 * "unemployed". `status` is the register's own verification state, not a judgement of the
 * person. Donations are totals only and are never attributed to a named alumnus.
 *
 * NO RECORD APPEARS IN THIS FILE. Every value is a placeholder filled at runtime from the
 * caller's own institute.
 *
 * NOTHING BELONGING TO AN EXISTING MODULE IS TOUCHED. Every write is keyed on `alumni` or a
 * `k12.alumni.` template key.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_11_02_290000_register_alumni_ai_stack.php
 */
return new class extends Migration
{
    private const MODULE_KEY = 'alumni';

    private const PATTERNS = [
        '/modules/alumni-management',
        '/modules/alumni-management/**',
        '/alumni',
        '/alumni/**',
    ];

    private const REFUSALS = [
        'Do not invent an alumnus, a batch, a passing year, an employer, a job title, a city or a count.',
        'Contact details, dates of birth, addresses and photographs are never provided. Never state, guess or ask for one.',
        'A blank employment status or employer means it is NOT RECORDED. Never say or imply that an alumnus is unemployed, between jobs or doing nothing.',
        'The status column is the register\'s verification state (registered, pending verification, verified, active). It is not a judgement of the person. Repeat the word the register holds rather than translating it.',
        'Never rank alumni by success, income or employer, and never infer anything about a person beyond the row you were given.',
        'Donations are never attributed to a named alumnus, and nothing here records whether an alumnus attended an event.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ai_modules') || ! Schema::hasTable('ai_templates')
            || ! Schema::hasColumn('ai_templates', 'kind')) {
            return;
        }

        $this->createModule();

        foreach ($this->rows() as $template) {
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
        $this->setCapabilities(['conversational' => true, 'generative' => true]);
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->where('module_key', self::MODULE_KEY)
                ->whereNull('sub_institute_id')
                ->delete();
        }

        if (Schema::hasTable('ai_templates')) {
            // Retired, not deleted: a generation request row references the template it ran
            // against, and deleting it would orphan the audit trail.
            DB::table('ai_templates')
                ->whereIn('template_key', array_column($this->rows(), 'template_key'))
                ->whereNull('sub_institute_id')
                ->update(['status' => 'retired', 'updated_at' => now()]);
        }

        if (Schema::hasTable('ai_modules')) {
            DB::table('ai_modules')
                ->where('module_key', self::MODULE_KEY)
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }

    private function createModule(): void
    {
        $exists = DB::table('ai_modules')
            ->where('module_key', self::MODULE_KEY)
            ->whereNull('sub_institute_id')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('ai_modules')->insert([
            'module_key' => self::MODULE_KEY,
            'label' => 'Alumni Management',
            'domain' => 'k12',
            'description' => 'Alumni, their events, mentorship, opportunities, surveys and giving totals.',
            'route_patterns' => json_encode(self::PATTERNS),
            'entity_key' => null,
            'entity_param' => null,
            'capabilities' => json_encode([
                'conversational' => true,
                'generative' => false,
                'agent' => false,
                'workflow' => false,
                'ontology' => false,
            ]),
            'allowed_roles' => null,
            'icon' => 'graduation-cap',
            'sort_order' => 330,
            'match_priority' => 80,
            'status' => 1,
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        $rules = json_encode(self::REFUSALS);

        return [
            $this->prompt(
                'summary',
                'Alumni summary',
                'A short summary of the alumni on the screen.',
                'Summarise the alumni on this page.',
                [
                    'How many alumni there are, across which batches.',
                    'How many have a status of verified or active, and how many are still waiting for verification.',
                    'How many have an employer recorded.',
                ],
                $rules,
            ),
            $this->prompt(
                'analysis',
                'Alumni analysis',
                'What the alumni show, and what the records do not support saying.',
                'Analyse the alumni in these records.',
                [
                    'Which batches are largest, and how verified each looks.',
                    'What employment and industries are recorded, and how many alumni have none recorded.',
                    'What the register cannot tell you, including how any one person is doing.',
                    'One or two concrete next steps the alumni coordinator could take.',
                ],
                $rules,
            ),
            $this->report(),
        ];
    }

    /**
     * @param  array<int, string>  $focus
     * @return array<string, mixed>
     */
    private function prompt(string $slug, string $name, string $description, string $instruction, array $focus, string $rules): array
    {
        $cover = '';
        foreach ($focus as $index => $line) {
            $cover .= ($index + 1).'. '.$line."\n";
        }

        return [
            'template_key' => 'k12.'.self::MODULE_KEY.'.'.$slug,
            'name' => $name,
            'description' => $description,
            'module_key' => self::MODULE_KEY,
            'domain' => 'k12',
            'category' => 'report',
            'kind' => 'prompt',
            'version' => 1,
            'status' => 'published',
            'output_format' => 'text',
            'system_prompt' => 'You summarise an alumni register for a school\'s alumni coordinator. Work only from the records given below. '
                .'Never state a name, a date, a figure or a count that is not in them, and never '
                .'estimate one. '.implode(' ', self::REFUSALS),
            'user_prompt' => $instruction."\n\n"
                ."Page: {{page_title}}\n"
                ."Active filters: {{filters}}\n"
                ."Search: {{search_query}}\n"
                ."Figures on screen: {{metrics}}\n"
                ."Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                ."Rows:\n{{records}}\n\n"
                ."Cover:\n".$cover."\n"
                .'If no alumni records were reported, say that none were provided rather '
                .'than describing the register as empty.',
            'variables' => json_encode([
                ['key' => 'records', 'label' => 'Alumni records', 'required' => true, 'type' => 'text', 'grounding' => true],
                ['key' => 'metrics', 'label' => 'Alumni figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                ['key' => 'search_query', 'label' => 'Search', 'required' => false, 'type' => 'string'],
                ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
            ]),
            'safety_rules' => $rules,
            'requires_review' => 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return [
            'template_key' => 'k12.'.self::MODULE_KEY.'.report',
            'name' => 'Alumni Register',
            'description' => 'Alumni with batch, passing year, verification status and recorded employment.',
            'module_key' => self::MODULE_KEY,
            'domain' => 'k12',
            'category' => 'report',
            'kind' => 'report',
            'version' => 1,
            'status' => 'published',
            // NOT NULL on this table, and a report has no prompt.
            'user_prompt' => '',
            'html_layout' => $this->layout(),
            'data_source' => 'alumni.directory',
            'data_arguments' => json_encode(['limit' => 200]),
            'output_format' => 'text',
            'requires_review' => 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];
    }

    private function layout(): string
    {
        $cell = 'style="border:1px solid #cbd5e1;padding:6px;text-align:left"';
        $columns = [
            ['Name', 'name'], ['Batch', 'batch'], ['Passing year', 'passing_year'], ['Status', 'status'],
            ['Employment', 'employment_status'], ['Employer', 'employer'], ['Job title', 'job_title'],
        ];

        $headings = '<th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>';
        $cells = '<td '.$cell.'><<row_number>></td>';

        foreach ($columns as [$heading, $field]) {
            $headings .= "\n        <th ".$cell.'>'.$heading.'</th>';
            $cells .= "\n        <td ".$cell.'><<'.$field.'>></td>';
        }

        $footer = 'Alumni matched: <<count>> &mdash; of which <<with_employer_recorded>> have an employer '
            .'recorded, over every matching alumnus rather than only the rows listed.';

        return <<<HTML
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> row(s) listed &middot; academic year <<academic_year>> &middot; generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        {$headings}
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        {$cells}
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>{$footer}</strong>
  </p>
  <p style="margin:6px 0 0 0;color:#475569;font-size:12px">
    A blank cell is a detail the school has not recorded, not a detail that does not exist.
  </p>

</div>
HTML;
    }

    private function bindSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $suggestions = [
            ['generate', 'k12.alumni.summary', 'Summarise alumni', 'Summarise the alumni on this page from the records themselves.'],
            ['generate', 'k12.alumni.analysis', 'Analyse alumni', 'What the alumni show, and what the records do not support saying.'],
            ['report', null, 'Alumni Register', 'Build the alumni register document from the alumni records.'],
        ];

        foreach ($suggestions as $index => [$actionType, $ref, $label, $description]) {
            $exists = DB::table('ai_suggestions')
                ->where('module_key', self::MODULE_KEY)
                ->where('capability', 'generative')
                ->where('label', $label)
                ->whereNull('sub_institute_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('ai_suggestions')->insert([
                'module_key' => self::MODULE_KEY,
                'capability' => 'generative',
                'label' => $label,
                'description' => $description,
                'icon' => null,
                'action_type' => $actionType,
                'action_ref' => $ref,
                'prompt' => null,
                'payload' => null,
                'requires_entity' => 0,
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

    /** @param array<string, bool> $flags */
    private function setCapabilities(array $flags): void
    {
        $rows = DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->get(['id', 'capabilities']);

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
};
