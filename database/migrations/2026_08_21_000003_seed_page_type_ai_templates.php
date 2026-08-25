<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shared page-type templates: Analysis and Create, for the whole application.
 *
 * There are six of these and there will not be one per module. A template here is
 * rendered against the *page snapshot* — the filters, figures and rows the page
 * reported — so `k12.analyse.list` analyses fee defaulters on one screen and library
 * loans on the next without knowing either module exists. That is what stops "Analysis
 * everywhere" from becoming ninety bespoke implementations.
 *
 * Every one of them is written to refuse rather than invent. The estate's grounding
 * rule is that no figure may be stated unless it came from real data, and an analysis
 * template is the easiest place in the system to break it: give a model a page title
 * and no rows and it will happily produce plausible numbers. So each prompt states the
 * inputs it was given, tells the model to work only from them, and — through
 * `{{is_partial}}` — to say so when it is looking at a window rather than the whole set.
 *
 * `allow_as_evidence` is false throughout. These produce interpretation, not fact, and
 * the governance kernel must keep refusing to cite them.
 */
return new class extends Migration
{
    private function templates(): array
    {
        $grounding = 'Work only from the data given to you below. Never state a number, name, date or '
            . 'total that is not in it. Never estimate, extrapolate or fill a gap from general knowledge. '
            . 'If the data does not support a conclusion, say plainly what is missing instead of guessing. '
            . 'Write for a school administrator in plain language: no jargon, no tool names, no raw data '
            . 'structures. Format money in Indian rupees with Indian digit grouping.';

        $inputs = "Page: {{page_title}}\n"
            . "Active filters: {{filters}}\n"
            . "Search: {{search_query}}\n"
            . "Figures on screen: {{metrics}}\n"
            . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
            . "Rows:\n{{records}}\n";

        return [
            [
                'key' => 'k12.analyse.dashboard',
                'name' => 'Dashboard analysis',
                'description' => 'Insights, trends and what needs attention, from the figures on the dashboard.',
                'category' => 'report',
                'system' => 'You analyse school dashboards for administrators. ' . $grounding,
                'user' => "Analyse this dashboard.\n\n{$inputs}\n"
                    . "Cover, in this order and only where the data supports it:\n"
                    . "1. What the figures say overall, in two sentences.\n"
                    . "2. Which figure most needs attention, and why — quote the number.\n"
                    . "3. Anything that looks unusual against the others.\n"
                    . "4. One or two concrete next steps.\n\n"
                    . 'If no figures were reported, say that the dashboard did not provide any data to analyse '
                    . 'and suggest what would help. Keep the whole answer under 200 words.',
            ],
            [
                'key' => 'k12.analyse.report',
                'name' => 'Report analysis',
                'description' => 'Key findings from a report, respecting the filters currently applied.',
                'category' => 'report',
                'system' => 'You analyse school reports for administrators. ' . $grounding
                    . ' The filters given to you define the scope of the report: your findings describe only '
                    . 'that filtered view, and you say so explicitly.',
                'user' => "Analyse this report.\n\n{$inputs}\n"
                    . "Cover:\n"
                    . "1. What this report covers, naming the filters in use.\n"
                    . "2. The key findings, quoting the figures they rest on.\n"
                    . "3. Anything unusual or worth questioning.\n"
                    . "4. What action the findings suggest.\n\n"
                    . 'Separate what the data measures from your interpretation of it. Under 220 words.',
            ],
            [
                'key' => 'k12.analyse.list',
                'name' => 'Record list analysis',
                'description' => 'A summary of the records on screen and which of them need attention.',
                'category' => 'report',
                'system' => 'You analyse lists of school records for administrators. ' . $grounding,
                'user' => "Analyse the records on this page.\n\n{$inputs}\n"
                    . "Cover:\n"
                    . "1. What these records have in common, and how they are spread.\n"
                    . "2. Which of them need attention first, named individually, and why.\n"
                    . "3. Anything that looks out of place.\n\n"
                    . 'If the partial-view flag is "yes", state clearly that you are describing only the rows '
                    . 'shown and not the full set. Under 200 words.',
            ],
            [
                'key' => 'k12.analyse.detail',
                'name' => 'Record analysis',
                'description' => 'What matters about the record on screen, and what to do next.',
                'category' => 'report',
                'system' => 'You analyse a single school record for the staff member looking at it. ' . $grounding,
                'user' => "Analyse this record.\n\n"
                    . "Record: {{entity_label}}\n"
                    . "Module: {{module}}\n"
                    . "{$inputs}\n"
                    . "Cover:\n"
                    . "1. What the record shows, in two sentences.\n"
                    . "2. Anything that needs attention, quoting the figures.\n"
                    . "3. What the staff member should consider doing next.\n\n"
                    . 'Do not claim any change has been made. Under 180 words.',
            ],
            [
                'key' => 'k12.assist.form',
                'name' => 'Form completion help',
                'description' => 'Explains the fields and suggests values from what is already filled in.',
                'category' => 'feedback',
                'system' => 'You help school staff complete data-entry forms. ' . $grounding
                    . ' You suggest values; you never claim to have entered or saved anything. The person '
                    . 'always types the value themselves.',
                'user' => "Help me complete this form.\n\n{$inputs}\n"
                    . "Cover:\n"
                    . "1. What this form is for.\n"
                    . "2. What each field it reported means, in plain language.\n"
                    . "3. Suggested values for anything still empty, with a one-line reason each.\n\n"
                    . 'Mark every suggestion clearly as a suggestion. If the form reported no fields, ask '
                    . 'which part the person needs help with. Under 200 words.',
            ],
            [
                'key' => 'k12.summarise.record',
                'name' => 'Record summary',
                'description' => 'A short written summary of the record, suitable for a note or an email.',
                'category' => 'report',
                'system' => 'You write short factual summaries of school records. ' . $grounding,
                'user' => "Write a short summary of this record.\n\n"
                    . "Record: {{entity_label}}\n"
                    . "Module: {{module}}\n"
                    . "{$inputs}\n"
                    . 'Write it as a paragraph someone could paste into a note or an email. State only what '
                    . 'the data shows. Under 120 words.',
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $now = now();

        foreach ($this->templates() as $template) {
            $exists = DB::table('ai_templates')
                ->where('template_key', $template['key'])
                ->where('version', 1)
                ->whereNull('sub_institute_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('ai_templates')->insert([
                'template_key' => $template['key'],
                'version' => 1,
                'name' => $template['name'],
                'description' => $template['description'],
                'domain' => 'k12',
                'category' => $template['category'],
                'system_prompt' => $template['system'],
                'user_prompt' => $template['user'],
                'variables' => null,
                'output_schema' => null,
                'output_format' => 'text',
                'status' => 'published',
                // Interpretation, not fact. GroundedClaims must keep refusing to cite it.
                'allow_as_evidence' => false,
                'requires_review' => false,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        DB::table('ai_templates')
            ->whereIn('template_key', array_column($this->templates(), 'key'))
            ->whereNull('sub_institute_id')
            ->delete();
    }
};
