<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $now = now();

        $templates = [
            [
                'template_key' => 'k12.fees.pending_summary',
                'name' => 'Pending fees summary',
                'description' => 'A short summary of pending or unpaid fees for a student or cohort.',
                'domain' => 'k12',
                'category' => 'report',
                'system_prompt' => 'You summarise pending school fees for administrators. Work only from the data given below. Never state a fee amount, student name, date or head that is not in it. Never estimate or fill gaps from general knowledge. If the data does not support a conclusion, say what is missing. Format money in Indian rupees with Indian digit grouping.',
                'user_prompt' => "Summarise the pending fees on this page.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Search: {{search_query}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. What the pending fees total is and which students or heads make up most of it.\n"
                    . "2. Any pattern that stands out — overdue periods, heads with the largest balances.\n"
                    . "3. One or two concrete next steps for follow-up.\n\n"
                    . 'If no fee records were reported, say that no pending fees were provided.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Fee records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Fee figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                    ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                    ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                    ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
                ]),
                'output_schema' => null,
                'output_format' => 'text',
                'provider' => null,
                'model' => null,
                'temperature' => null,
                'max_tokens' => null,
                'safety_rules' => json_encode([
                    'Do not invent a fee amount, student name, head or date.',
                    'Do not describe the records as empty; say only that no fees were provided.',
                ]),
                'allow_as_evidence' => false,
                'requires_review' => false,
                'status' => 'published',
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_key' => 'k12.fees.collection_report',
                'name' => 'Fee collection report',
                'description' => 'A short plain-language overview of fee collection for the current period.',
                'domain' => 'k12',
                'category' => 'report',
                'system_prompt' => 'You write short summaries of school fee collection for administrators. Use only the payments, amounts and periods given to you. Never invent a payment, amount, student or date. If the list is a partial view, say so. If no payments are listed, say only that you were not given any — never describe collection as empty, because you cannot tell the difference between an empty period and a list that did not reach you.',
                'user_prompt' => "Summarise this fee collection report for an administrator.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Collection scope: {{filters}}\n"
                    . "Distinct payments in the report: {{record_count}}\n"
                    . "Payments shown below: {{rows_shown}}\n"
                    . "This is a partial view: {{is_partial}}\n\n"
                    . "Totals:\n{{metrics}}\n\n"
                    . "Payments:\n{{records}}\n\n"
                    . 'Give a short overview: total collected, any heads or periods that stand out, and anything an administrator would want to know first. If "This is a partial view" is yes, make clear the totals cover the whole report while the list below is a sample. Keep it under 150 words.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Payments', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Totals', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Period filters', 'required' => false, 'type' => 'string'],
                    ['key' => 'record_count', 'label' => 'Total payments', 'required' => false, 'type' => 'string'],
                    ['key' => 'rows_shown', 'label' => 'Payments listed', 'required' => false, 'type' => 'string'],
                    ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
                ]),
                'output_schema' => null,
                'output_format' => 'text',
                'provider' => null,
                'model' => null,
                'temperature' => null,
                'max_tokens' => null,
                'safety_rules' => json_encode([
                    'Do not invent a payment, amount, student, head or date.',
                    'Do not describe the report as empty; say only that no payments were provided.',
                ]),
                'allow_as_evidence' => false,
                'requires_review' => false,
                'status' => 'published',
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_key' => 'k12.fees.defaulter_report',
                'name' => 'Fee defaulter analysis',
                'description' => 'Analysis of fee defaulters and overdue balances.',
                'domain' => 'k12',
                'category' => 'report',
                'system_prompt' => 'You analyse fee defaulter reports for school administrators. Work only from the data given below. Never state a balance, student name, period or head that is not in it. Never estimate or fill gaps from general knowledge. If the data does not support a conclusion, say what is missing. Format money in Indian rupees with Indian digit grouping.',
                'user_prompt' => "Analyse the fee defaulter report on this page.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Search: {{search_query}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. How many students are in default and what the total outstanding is.\n"
                    . "2. Which heads or periods carry the largest overdue balances.\n"
                    . "3. Any pattern worth flagging — long-outstanding balances, repeated defaults.\n"
                    . "4. One or two concrete follow-up actions.\n\n"
                    . 'If no defaulter records were reported, say that no defaults were provided.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Defaulter records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Defaulter figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                    ['key' => 'record_count', 'label' => 'Total defaulters', 'required' => false, 'type' => 'string'],
                    ['key' => 'rows_shown', 'label' => 'Defaulters listed', 'required' => false, 'type' => 'string'],
                    ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
                ]),
                'output_schema' => null,
                'output_format' => 'text',
                'provider' => null,
                'model' => null,
                'temperature' => null,
                'max_tokens' => null,
                'safety_rules' => json_encode([
                    'Do not invent a balance, student name, period or head.',
                    'Do not describe the report as empty; say only that no defaulters were provided.',
                ]),
                'allow_as_evidence' => false,
                'requires_review' => false,
                'status' => 'published',
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($templates as $template) {
            $exists = DB::table('ai_templates')
                ->where('template_key', $template['template_key'])
                ->whereNull('sub_institute_id')
                ->exists();

            if (! $exists) {
                DB::table('ai_templates')->insert($template);
            }
        }

        if (Schema::hasTable('ai_modules')) {
            $row = DB::table('ai_modules')
                ->where('module_key', 'fees')
                ->whereNull('sub_institute_id')
                ->first();

            if ($row) {
                $capabilities = json_decode($row->capabilities ?? '[]', true);
                if (! is_array($capabilities)) {
                    $capabilities = [];
                }

                $capabilities['generative'] = true;

                DB::table('ai_modules')
                    ->where('id', $row->id)
                    ->update([
                        'capabilities' => json_encode($capabilities),
                        'updated_at' => $now,
                    ]);
            }
        }

        if (Schema::hasTable('ai_suggestions')) {
            $suggestions = [
                ['fees', 'generative', 'Summarise pending fees', 'generate', 'k12.fees.pending_summary', null, false, 10],
                ['fees', 'generative', 'Summarise fee collection', 'generate', 'k12.fees.collection_report', null, false, 20],
                ['fees', 'generative', 'Analyse fee defaulters', 'generate', 'k12.fees.defaulter_report', null, false, 30],
            ];

            foreach ($suggestions as [$module, $capability, $label, $actionType, $actionRef, $prompt, $requiresEntity, $sortOrder]) {
                $exists = DB::table('ai_suggestions')
                    ->where('module_key', $module)
                    ->where('capability', $capability)
                    ->where('label', $label)
                    ->whereNull('sub_institute_id')
                    ->exists();

                if (! $exists) {
                    DB::table('ai_suggestions')->insert([
                        'module_key' => $module,
                        'capability' => $capability,
                        'label' => $label,
                        'description' => null,
                        'icon' => null,
                        'action_type' => $actionType,
                        'action_ref' => $actionRef,
                        'prompt' => $prompt,
                        'payload' => null,
                        'requires_entity' => $requiresEntity,
                        'allowed_roles' => null,
                        'required_permissions' => null,
                        'sort_order' => $sortOrder,
                        'status' => 1,
                        'sub_institute_id' => null,
                        'client_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $templateKeys = [
            'k12.fees.pending_summary',
            'k12.fees.collection_report',
            'k12.fees.defaulter_report',
        ];

        foreach ($templateKeys as $key) {
            DB::table('ai_templates')
                ->where('template_key', $key)
                ->whereNull('sub_institute_id')
                ->delete();
        }

        if (Schema::hasTable('ai_modules')) {
            $row = DB::table('ai_modules')
                ->where('module_key', 'fees')
                ->whereNull('sub_institute_id')
                ->first();

            if ($row) {
                $capabilities = json_decode($row->capabilities ?? '[]', true);
                if (is_array($capabilities)) {
                    $capabilities['generative'] = false;

                    DB::table('ai_modules')
                        ->where('id', $row->id)
                        ->update([
                            'capabilities' => json_encode($capabilities),
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->where('module_key', 'fees')
                ->where('capability', 'generative')
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }
};
