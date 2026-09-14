<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore the baseline Fees AI workspace data that was present before the newer
     * module additions landed.
     *
     * This is intentionally additive and idempotent: it only enriches the existing
     * Fees configuration and never deletes or rewrites unrelated modules.
     */
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('ai_modules')) {
            $row = DB::table('ai_modules')
                ->where('module_key', 'fees')
                ->whereNull('sub_institute_id')
                ->first();

            $capabilities = [
                'conversational' => true,
                'generative' => true,
                'agent' => false,
                'workflow' => true,
                'ontology' => false,
            ];

            if ($row) {
                $existing = json_decode((string) ($row->capabilities ?? '[]'), true);
                if (is_array($existing)) {
                    $capabilities = array_replace($capabilities, $existing);
                }

                $capabilities['conversational'] = true;
                $capabilities['generative'] = true;
                $capabilities['workflow'] = true;

                DB::table('ai_modules')
                    ->where('id', $row->id)
                    ->update([
                        'label' => 'Fees',
                        'description' => 'Fee collection, defaulters and reports.',
                        'route_patterns' => json_encode(['/fees', '/fees/**']),
                        'entity_key' => null,
                        'entity_param' => null,
                        'capabilities' => json_encode($capabilities),
                        'icon' => 'receipt',
                        'sort_order' => 40,
                        'match_priority' => 70,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('ai_modules')->insert([
                    'module_key' => 'fees',
                    'label' => 'Fees',
                    'domain' => 'k12',
                    'description' => 'Fee collection, defaulters and reports.',
                    'route_patterns' => json_encode(['/fees', '/fees/**']),
                    'entity_key' => null,
                    'entity_param' => null,
                    'capabilities' => json_encode($capabilities),
                    'allowed_roles' => null,
                    'icon' => 'receipt',
                    'sort_order' => 40,
                    'match_priority' => 70,
                    'status' => 1,
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('ai_suggestions')) {
            $suggestions = [
                ['fees', 'conversational', 'Which students have pending fees?', 'prompt', null, 'Which students currently have pending or unpaid fees?', false, 10],
                ['fees', 'conversational', 'Fee collection summary', 'prompt', null, 'Summarise fee collection for the current period.', false, 20],
                ['fees', 'conversational', 'Show defaulters', 'prompt', null, 'Show the fee defaulter report.', false, 30],
                ['fees', 'workflow', 'Review pending fees', 'start_workflow', 'fees_collection', null, false, 10],
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

                if ($exists) {
                    continue;
                }

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

        if (Schema::hasTable('ai_templates')) {
            $templates = [
                [
                    'template_key' => 'k12.fees.pending_summary',
                    'module_key' => 'fees',
                    'name' => 'Pending fees summary',
                    'description' => 'A short summary of pending or unpaid fees for a student or cohort.',
                    'domain' => 'k12',
                    'category' => 'report',
                    'kind' => 'prompt',
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
                ],
                [
                    'template_key' => 'k12.fees.collection_report',
                    'module_key' => 'fees',
                    'name' => 'Fee collection report',
                    'description' => 'A short plain-language overview of fee collection for the current period.',
                    'domain' => 'k12',
                    'category' => 'report',
                    'kind' => 'prompt',
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
                ],
                [
                    'template_key' => 'k12.fees.defaulter_report',
                    'module_key' => 'fees',
                    'name' => 'Fee defaulter analysis',
                    'description' => 'Analysis of fee defaulters and overdue balances.',
                    'domain' => 'k12',
                    'category' => 'report',
                    'kind' => 'prompt',
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
                ],
            ];

            foreach ($templates as $template) {
                $exists = DB::table('ai_templates')
                    ->where('template_key', $template['template_key'])
                    ->whereNull('sub_institute_id')
                    ->exists();

                if ($exists) {
                    DB::table('ai_templates')
                        ->where('template_key', $template['template_key'])
                        ->whereNull('sub_institute_id')
                        ->update([
                            'module_key' => $template['module_key'],
                            'name' => $template['name'],
                            'description' => $template['description'],
                            'domain' => $template['domain'],
                            'category' => $template['category'],
                            'kind' => $template['kind'],
                            'system_prompt' => $template['system_prompt'],
                            'user_prompt' => $template['user_prompt'],
                            'variables' => $template['variables'],
                            'output_schema' => $template['output_schema'],
                            'output_format' => $template['output_format'],
                            'provider' => $template['provider'],
                            'model' => $template['model'],
                            'temperature' => $template['temperature'],
                            'max_tokens' => $template['max_tokens'],
                            'safety_rules' => $template['safety_rules'],
                            'allow_as_evidence' => $template['allow_as_evidence'],
                            'requires_review' => $template['requires_review'],
                            'status' => $template['status'],
                            'updated_at' => $now,
                        ]);

                    continue;
                }

                DB::table('ai_templates')->insert([
                    'template_key' => $template['template_key'],
                    'module_key' => $template['module_key'],
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'domain' => $template['domain'],
                    'category' => $template['category'],
                    'version' => 1,
                    'kind' => $template['kind'],
                    'system_prompt' => $template['system_prompt'],
                    'user_prompt' => $template['user_prompt'],
                    'variables' => $template['variables'],
                    'output_schema' => $template['output_schema'],
                    'output_format' => $template['output_format'],
                    'provider' => $template['provider'],
                    'model' => $template['model'],
                    'temperature' => $template['temperature'],
                    'max_tokens' => $template['max_tokens'],
                    'safety_rules' => $template['safety_rules'],
                    'allow_as_evidence' => $template['allow_as_evidence'],
                    'requires_review' => $template['requires_review'],
                    'status' => $template['status'],
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty: this migration is a restoration and should never
        // delete known-good data that may have been added by later modules.
    }
};
