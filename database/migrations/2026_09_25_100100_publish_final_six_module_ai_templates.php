<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prompts and report layouts for the six modules registered by 2026_09_25_100000.
 *
 * Built exactly like its three predecessors: one declaration, one loop, two cohort prompts
 * and one report layout per module. See 2026_09_22_100100 for why the shape is shared and
 * why the refusals are the part that genuinely differs.
 *
 * EVERY PLACEHOLDER IS A FIELD THE BOUND TOOL REALLY RETURNS
 *
 * The column lists below were taken from the services, not from the tables:
 * `InventoryService::items()` maps `title, category, recorded_stock, minimum_stock,
 * at_or_below_minimum, approved_for_issue`; `TaskService::list()` maps `title, task_date,
 * status, status_normalised, assigned_to, days_past_date`; and so on. Every scalar in a
 * footer is counted over the whole matching set rather than the page.
 *
 * THREE OF THESE MODULES ARE DEFINED BY A COLUMN THAT IS NOT WHAT ITS NAME SAYS
 *
 * That is what most of the refusals below are for:
 *
 *   Inventory        - `opening_stock` is INCREASED by a purchase and never decreased when
 *                      stock is issued, so it overstates the shelf. Nothing may say an item
 *                      is in stock, out of stock or low.
 *   Complaint        - `COMPLAINT_SOLUTION` holds the STATUS word, not a resolution. No
 *                      resolution text exists, and there is no priority, SLA or escalation.
 *   Utility          - the module is bulk data operations, not electricity and water. No
 *                      utilities data exists anywhere in this estate, and no operation
 *                      history is recorded either.
 *
 * And two more absences shape the rest:
 *
 *   Front Desk       - this register is not the school's whole visitor log, so an empty
 *                      answer never means nobody visited.
 *   Task Management  - the status column holds TWO SPELLINGS of "complete", so a count
 *                      that matches one of them is wrong.
 *   Document Temp.   - the tables are empty across the estate. An empty list is the
 *                      correct answer and not a prompt to invent one.
 *
 * WHY INVENTORY IS THE ONE THAT NEEDS REVIEWING
 *
 * `requires_review = 1` is set for Inventory alone. The others produce internal summaries
 * where a mistake is a mistake. An inventory summary is acted on by somebody ordering
 * stock or deciding not to, against a figure this schema cannot make accurate — so it is
 * the output that should not go out unread.
 *
 * NO RECORD APPEARS IN THIS FILE. Every value is a placeholder filled at runtime from the
 * caller's own institute and year.
 *
 * NOTHING BELONGING TO AN EXISTING MODULE IS TOUCHED. Every template key begins
 * `k12.inventory.`, `k12.front_desk.`, `k12.task_management.`, `k12.complaint.`,
 * `k12.migration-modules.` or `k12.document-templates.`, none of which exists yet —
 * including for the four modules whose `ai_modules` rows predate this work and which have
 * never had a template of their own. The last two carry a HYPHEN because that is how
 * `ai_modules` spells those keys.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_25_100100_publish_final_six_module_ai_templates.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasColumn('ai_templates', 'kind')) {
            return;
        }

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

        foreach (array_keys($this->modules()) as $moduleKey) {
            $this->setModuleCapabilities($moduleKey, ['generative' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        if (Schema::hasTable('ai_suggestions')) {
            foreach ($this->modules() as $moduleKey => $module) {
                DB::table('ai_suggestions')
                    ->where('module_key', $moduleKey)
                    ->where('capability', 'generative')
                    ->whereIn('label', array_column($this->suggestionsFor($moduleKey, $module), 2))
                    ->whereNull('sub_institute_id')
                    ->delete();
            }
        }

        // Retired, not deleted: a generation request row references the template it ran
        // against, and deleting it would orphan the audit trail.
        DB::table('ai_templates')
            ->whereIn('template_key', array_column($this->rows(), 'template_key'))
            ->whereNull('sub_institute_id')
            ->update(['status' => 'retired', 'updated_at' => now()]);

        foreach (array_keys($this->modules()) as $moduleKey) {
            $this->setModuleCapabilities($moduleKey, ['generative' => false]);
        }
    }

    // ------------------------------------------------------------------ declaration

    /**
     * What differs between the six modules, and nothing else.
     *
     * @return array<string, array<string, mixed>>
     */
    private function modules(): array
    {
        return [
            'inventory' => [
                'label' => 'Inventory',
                'noun' => 'inventory item',
                'plural' => 'inventory items',
                'data_source' => 'inventory.items',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Inventory Item Report',
                'report_description' => 'Items on the item master with the stock figure recorded against '
                    .'each and its reorder level.',
                'summary_focus' => [
                    'How many items are listed and which categories they sit in.',
                    'How many are at or below their recorded reorder level.',
                    'What quantity requisitions have approved for issue against them.',
                ],
                'analysis_focus' => [
                    'Which categories hold the most items at or below their reorder level.',
                    'Where a reorder level has not been set at all, so nothing can be compared.',
                    'What the item master cannot tell you — above all, what is actually on the shelf.',
                    'One or two concrete next steps the store could take.',
                ],
                'system_role' => 'You summarise an inventory item master for a school store.',
                'refusals' => [
                    'Do not invent an item, a category, a quantity or a count.',
                    'THIS SYSTEM KEEPS NO RUNNING STOCK BALANCE. The recorded stock figure is increased by a purchase and NEVER decreased when stock is issued, so it overstates what is on the shelf. Never say an item is in stock, out of stock, low on stock, sufficient or running out, and never recommend an order quantity.',
                    '`at_or_below_minimum` compares that same unreconciled figure to the recorded reorder level and means only that. Do not present it as a shortage.',
                    'Quantity approved for issue has never been reconciled against the stock figure. Never subtract one from the other and never present the result as a stock count.',
                    'A null category means this institute has no category record for that id. It is a record to correct, not a name to guess at.',
                ],
                'columns' => [
                    ['Item', 'title'],
                    ['Category', 'category'],
                    ['Recorded stock', 'recorded_stock'],
                    ['Reorder level', 'minimum_stock'],
                    ['At or below', 'at_or_below_minimum'],
                    ['Approved for issue', 'approved_for_issue'],
                ],
                'footer' => 'Items matched: <<count>> &mdash; of which <<at_or_below_minimum>> are at or '
                    .'below their recorded reorder level, over every matching item rather than only the '
                    .'rows listed. THE RECORDED STOCK FIGURE IS NOT A SHELF COUNT: it is increased by '
                    .'purchases and never decreased when stock is issued, so it overstates what is there.',
            ],

            'front_desk' => [
                'label' => 'Front Desk',
                'noun' => 'front desk visit',
                'plural' => 'front desk visits',
                'data_source' => 'front_desk.visits',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Front Desk Register',
                'report_description' => 'People who came in to meet a member of staff about a student, with '
                    .'the times recorded.',
                'summary_focus' => [
                    'How many visits there were and over what span of dates.',
                    'Which members of staff people came to meet.',
                    'How many have an exit time recorded and how many do not.',
                ],
                'analysis_focus' => [
                    'Which visitor types and reasons come up most.',
                    'How consistently exit times are being recorded.',
                    'What this register cannot tell you, including that it is not the whole visitor log.',
                    'One or two concrete next steps the desk could take.',
                ],
                'system_role' => 'You summarise a school front desk register for the reception team.',
                'refusals' => [
                    'Do not invent a visitor, a student, a member of staff, a time or a count.',
                    'A missing exit time means NO EXIT WAS RECORDED. The person may still be on site or may have left without signing out. NEVER state that a named person is currently in the building.',
                    'THIS IS NOT THE SCHOOL\'S WHOLE VISITOR LOG. A separate and much larger register belongs to the Visitor Management module and you have not been given it. Never state a total for visitors to the school, and never say nobody visited — say this register holds no matching row.',
                    'A non-admin reader has been shown only the visits they were the subject of. Never describe a restricted list as the whole day.',
                    'Do not say anything about the student a visit concerns beyond the row you were given.',
                ],
                'columns' => [
                    ['Visitor type', 'visitor_type'],
                    ['About', 'student_name'],
                    ['To meet', 'to_meet'],
                    ['Date', 'date'],
                    ['In', 'in_time'],
                    ['Out', 'out_time'],
                    ['Status', 'presence_state'],
                ],
                'footer' => 'Visits matched: <<count>> &mdash; <<exit_recorded>> with an exit time recorded '
                    .'and <<no_exit_recorded>> with none. This register is NOT the school\'s whole visitor '
                    .'log; a separate one belongs to the Visitor Management module and is not included '
                    .'here.',
            ],

            'task_management' => [
                'label' => 'Task Management',
                'noun' => 'task',
                'plural' => 'tasks',
                'data_source' => 'tasks.list',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Task Report',
                'report_description' => 'Tasks with their dates, assignees and status, counted across both '
                    .'spellings of complete.',
                'summary_focus' => [
                    'How many tasks are finished and how many are still open.',
                    'How many are past their date, and by how long.',
                    'Who the listed tasks are assigned to.',
                ],
                'analysis_focus' => [
                    'Where the overdue tasks are concentrated.',
                    'Whether any overdue task has no assignee recorded.',
                    'What the task list cannot tell you about why anything is late.',
                    'One or two concrete next steps a manager could take.',
                ],
                'system_role' => 'You summarise a task list for a school administrator.',
                'refusals' => [
                    'Do not invent a task, an assignee, a date or a count.',
                    'THE STATUS COLUMN HOLDS TWO SPELLINGS OF THE SAME STATE: COMPLETE and COMPLETED both mean finished. Judge only on the normalised value, and never report a count that matches one spelling.',
                    'A task with no date is undated, not overdue. Never include one in an overdue list or count.',
                    'Nothing records why a task is late, whether its date was ever agreed, or who is responsible for the delay. Report the dates and the states and attribute nothing to anybody. Never describe a named person as slow, behind or underperforming.',
                    'Do not rank people by task count. The list records what was allocated, not how much work anything took.',
                ],
                'columns' => [
                    ['Task', 'title'],
                    ['Due', 'task_date'],
                    ['Status', 'status'],
                    ['State', 'status_normalised'],
                    ['Assigned to', 'assigned_to'],
                    ['Days past date', 'days_past_date'],
                ],
                'footer' => 'Tasks matched: <<count>> &mdash; <<complete>> complete, <<open>> open and '
                    .'<<overdue>> past their date, over every matching task rather than only the rows '
                    .'listed. The completed figure counts BOTH spellings the status column holds.',
            ],

            'complaint' => [
                'label' => 'Complaint',
                'noun' => 'complaint',
                'plural' => 'complaints',
                'data_source' => 'complaints.list',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Complaint Register',
                'report_description' => 'Complaints raised, the group each was assigned to and whether it '
                    .'is closed.',
                'summary_focus' => [
                    'How many complaints were raised and over what span of dates.',
                    'How many are closed and how many are still open.',
                    'Which groups they were assigned to.',
                ],
                'analysis_focus' => [
                    'Where the open complaints are concentrated.',
                    'How long the open ones have been open, as arithmetic on the date.',
                    'What the register cannot tell you — including how anything was resolved.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise a complaint register for a school office.',
                'refusals' => [
                    'Do not invent a complaint, a person, a group, a date or a count.',
                    'THE COLUMN NAMED `COMPLAINT_SOLUTION` IS THE STATUS FIELD. There is NO resolution text anywhere on this table, so never state how a complaint was resolved, what was done about it, or what was said to the person who raised it.',
                    'This table records NO priority, category, severity, due date, SLA or escalation. Never call a complaint high priority, urgent, critical, breaching or needing escalation, and never rank complaints against each other.',
                    'Days since raised is arithmetic on a date and is not evidence that anything was promised or missed.',
                    'A complaint names the person who made it. Do not repeat their name into a summary others will read, and never comment on them.',
                ],
                'columns' => [
                    ['Complaint', 'title'],
                    ['Raised', 'complaint_date'],
                    ['Status', 'status'],
                    ['State', 'status_normalised'],
                    ['Assigned group', 'assigned_user_group_id'],
                    ['Days open', 'days_since_raised'],
                ],
                'footer' => 'Complaints matched: <<count>> &mdash; <<closed>> closed and <<open>> open, over '
                    .'every matching complaint rather than only the rows listed. The status column is the '
                    .'only outcome this register records; no resolution text exists, and there is no '
                    .'priority, due date or escalation on this table at all.',
            ],

            'migration-modules' => [
                'label' => 'Utility',
                'noun' => 'custom module',
                'plural' => 'custom modules',
                'data_source' => 'utility.custom_modules',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Custom Module Register',
                'report_description' => 'Tables defined from the custom-module screen, with the columns '
                    .'each defines.',
                'summary_focus' => [
                    'How many custom modules are defined and of what types.',
                    'How many columns each defines, and which define none.',
                    'Where each is set to appear in the navigation.',
                ],
                'analysis_focus' => [
                    'Which definitions look incomplete — no columns, or no access link.',
                    'Whether any two definitions look like duplicates of each other.',
                    'What this module cannot tell you, including whether anything has ever been run.',
                    'One or two concrete next steps an administrator could take.',
                ],
                'system_role' => 'You summarise custom module definitions for a school administrator.',
                'refusals' => [
                    'Do not invent a module, a table, a column or a count.',
                    'THE UTILITY MODULE IN THIS SYSTEM IS BULK DATA OPERATIONS — rollover, student transfer, bulk update and the custom-module builder. It is NOT electricity, water, gas, meter readings, consumption or utility bills, and this system records NONE of those anywhere. If asked about any of them, say plainly that no such data is held. Never estimate a bill, a reading or a consumption figure.',
                    'NO OPERATION HISTORY IS RECORDED. There is no rollover log, no transfer log and no bulk-update audit. Never say that a rollover has or has not been run, when anything last happened, how many students were moved, or whether the next year is ready.',
                    'These rows are DEFINITIONS, not data. Never say how many records a defined table holds or whether anybody uses it.',
                    'An academic year appearing in the list means enrolments exist against it. It does not mean the year has been rolled over.',
                ],
                'columns' => [
                    ['Module', 'module_name'],
                    ['Table', 'table_name'],
                    ['Type', 'module_type'],
                    ['Shown under', 'display_under'],
                    ['Columns', 'column_count'],
                    ['Defined on', 'defined_on'],
                ],
                'footer' => 'Custom modules matched: <<count>>. These are table DEFINITIONS, not data, and '
                    .'this module records no history of any operation it performs &mdash; no rollover, '
                    .'transfer or bulk update is logged anywhere.',
            ],

            'document-templates' => [
                'label' => 'Document Templates',
                'noun' => 'document template',
                'plural' => 'document templates',
                'data_source' => 'doc_templates.list',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Document Template Register',
                'report_description' => 'Templates a document can be generated from, with their status, '
                    .'version and merge fields.',
                'summary_focus' => [
                    'How many templates exist and how many are published rather than draft.',
                    'Which categories they fall into.',
                    'How many have no content saved yet.',
                ],
                'analysis_focus' => [
                    'Which templates look incomplete — no content, or no merge fields.',
                    'Which merge fields recur across templates.',
                    'What the register cannot tell you, including who approved anything.',
                    'One or two concrete next steps before a template is published.',
                ],
                'system_role' => 'You summarise a document template register for a school office.',
                'refusals' => [
                    'Do not invent a template, a category, a version or a merge field. The merge fields you were given were parsed from the stored content; a field not in that list is not in the template.',
                    'You have NOT been given the document body. Never quote it, paraphrase it, or describe what a template says.',
                    'IF NO TEMPLATES WERE PROVIDED, say exactly that. It is a complete and correct answer. Never describe templates the school might want, suggest a library to create, or list what a school "usually" has.',
                    'This table records no reviewer, no approval and no publish history. Never say a template was approved, by whom, or when it went live. A status is a field on the row and not a decision you have been shown.',
                    'Never state that a template has been used to generate anything. No usage is recorded here.',
                ],
                'columns' => [
                    ['Template', 'name'],
                    ['Category', 'category'],
                    ['Status', 'status'],
                    ['Version', 'version'],
                    ['Content saved', 'content_present'],
                    ['Last changed', 'last_changed_on'],
                ],
                'footer' => 'Templates matched: <<count>> &mdash; of which <<without_content>> have no '
                    .'content saved yet. The document body is never reproduced here; open the template on '
                    .'the Document Templates screen to read it. A count of zero means this school has '
                    .'created no templates.',
            ],
        ];
    }

    /**
     * Every `ai_templates` row this migration writes, built from the declaration above.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->modules() as $moduleKey => $module) {
            $rules = json_encode($module['refusals']);

            $rows[] = $this->prompt(
                $moduleKey,
                $module,
                'summary',
                $module['label'].' summary',
                'A short summary of the '.$module['plural'].' on the screen.',
                'Summarise the '.$module['plural'].' on this page.',
                $module['summary_focus'],
                $rules,
            );

            $rows[] = $this->prompt(
                $moduleKey,
                $module,
                'analysis',
                $module['label'].' analysis',
                'What the '.$module['plural'].' show, and what the records do not support saying.',
                'Analyse the '.$module['plural'].' in these records.',
                $module['analysis_focus'],
                $rules,
            );

            $rows[] = $this->report($moduleKey, $module);
        }

        return $rows;
    }

    /**
     * One cohort prompt.
     *
     * @param  array<string, mixed>  $module
     * @param  array<int, string>  $focus
     * @return array<string, mixed>
     */
    private function prompt(
        string $moduleKey,
        array $module,
        string $slug,
        string $name,
        string $description,
        string $instruction,
        array $focus,
        string $rules,
    ): array {
        $cover = '';

        foreach ($focus as $index => $line) {
            $cover .= ($index + 1).'. '.$line."\n";
        }

        // Inventory is the one module here whose output a person must read before it is
        // used anywhere. Somebody orders stock on it, or decides not to, against a figure
        // this schema cannot make accurate — the stock column is never decreased when
        // stock is issued. The other five produce internal administrative summaries.
        $needsReview = $moduleKey === 'inventory';

        return [
            'template_key' => 'k12.'.$moduleKey.'.'.$slug,
            'name' => $name,
            'description' => $description,
            'module_key' => $moduleKey,
            'domain' => 'k12',
            'category' => 'report',
            'kind' => 'prompt',
            'version' => 1,
            'status' => 'published',
            'output_format' => 'text',
            'system_prompt' => $module['system_role'].' Work only from the records given below. '
                .'Never state a name, a date, a figure or a count that is not in them, and never '
                .'estimate one. '.implode(' ', $module['refusals']),
            'user_prompt' => $instruction."\n\n"
                ."Page: {{page_title}}\n"
                ."Active filters: {{filters}}\n"
                ."Search: {{search_query}}\n"
                ."Figures on screen: {{metrics}}\n"
                ."Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                ."Rows:\n{{records}}\n\n"
                ."Cover:\n".$cover."\n"
                .'If no '.$module['noun'].' records were reported, say that none were provided rather '
                .'than describing the module as empty.',
            'variables' => json_encode([
                ['key' => 'records', 'label' => ucfirst($module['noun']).' records', 'required' => true, 'type' => 'text', 'grounding' => true],
                ['key' => 'metrics', 'label' => $module['label'].' figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                ['key' => 'search_query', 'label' => 'Search', 'required' => false, 'type' => 'string'],
                ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
            ]),
            'safety_rules' => $rules,
            'requires_review' => $needsReview ? 1 : 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];
    }

    /**
     * One report layout, bound to the module's own read tool.
     *
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    private function report(string $moduleKey, array $module): array
    {
        return [
            'template_key' => 'k12.'.$moduleKey.'.report',
            'name' => $module['report_name'],
            'description' => $module['report_description'],
            'module_key' => $moduleKey,
            'domain' => 'k12',
            'category' => 'report',
            'kind' => 'report',
            'version' => 1,
            'status' => 'published',
            // NOT NULL on this table, and a report has no prompt.
            'user_prompt' => '',
            'html_layout' => $this->layout($module),
            'data_source' => $module['data_source'],
            'data_arguments' => json_encode($module['data_arguments']),
            'output_format' => 'text',
            'requires_review' => 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];
    }

    /**
     * The HTML layout for one module's report.
     *
     * Placeholders come in three kinds and this uses all three: `<<report_title>>` at
     * report level, `<<count>>` from the data source's own scalars, and
     * `<<#rows>> … <</rows>>` repeated per record.
     *
     * @param  array<string, mixed>  $module
     */
    private function layout(array $module): string
    {
        $cell = 'style="border:1px solid #cbd5e1;padding:6px;text-align:left"';

        $headings = '<th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>';
        $cells = '<td '.$cell.'><<row_number>></td>';

        foreach ($module['columns'] as [$heading, $field]) {
            $headings .= "\n        <th ".$cell.'>'.$heading.'</th>';
            $cells .= "\n        <td ".$cell.'><<'.$field.'>></td>';
        }

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
    <strong>{$module['footer']}</strong>
  </p>
  <p style="margin:6px 0 0 0;color:#475569;font-size:12px">
    A blank cell is a detail the school has not recorded, not a detail that does not exist.
  </p>

</div>
HTML;
    }

    // ------------------------------------------------------------------ suggestions

    /**
     * @param  array<string, mixed>  $module
     * @return array<int, array{0:string,1:?string,2:string,3:string}>
     */
    private function suggestionsFor(string $moduleKey, array $module): array
    {
        return [
            [
                'generate',
                'k12.'.$moduleKey.'.summary',
                'Summarise '.$module['plural'],
                'Summarise the '.$module['plural'].' on this page from the records themselves.',
            ],
            [
                'generate',
                'k12.'.$moduleKey.'.analysis',
                'Analyse '.$module['plural'],
                'What the '.$module['plural'].' show, and what the records do not support saying.',
            ],
            [
                'report',
                null,
                $module['report_name'],
                'Build the '.strtolower($module['report_name']).' document from the '.$module['noun'].' records.',
            ],
        ];
    }

    private function bindSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        foreach ($this->modules() as $moduleKey => $module) {
            foreach ($this->suggestionsFor($moduleKey, $module) as $index => [$actionType, $ref, $label, $description]) {
                $exists = DB::table('ai_suggestions')
                    ->where('module_key', $moduleKey)
                    ->where('capability', 'generative')
                    ->where('label', $label)
                    ->whereNull('sub_institute_id')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('ai_suggestions')->insert([
                    'module_key' => $moduleKey,
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
    }

    /**
     * Flip only the named flags, merging into whatever the row already holds.
     *
     * @param  array<string, bool>  $flags
     */
    private function setModuleCapabilities(string $moduleKey, array $flags): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', $moduleKey)->get(['id', 'capabilities']);

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
