<?php

namespace App\Domain\AI\Fees;

/**
 * Fees AI Capability Map — which AI Stack capability handles which Fees request.
 *
 * This is the single source of truth for "for this Fees request, which AI
 * capability is used, what data it reads, and what it produces."
 *
 * Each capability maps to one or more user question patterns. When the
 * Chatbot receives a fees question, the IntentClassifier identifies the
 * intent, the DeterministicPlanner selects the capability, and the
 * appropriate service/tool is called with real Fees data.
 *
 * ┌─────────────────────────┬─────────────────────────────────────────┐
 * │  User asks about...      │  AI Capability used                    │
 * ├─────────────────────────┼─────────────────────────────────────────┤
 * │  Pending fees            │  FeesPromptService (pendingFeesPrompt) │
 * │  Fee details for student │  FeesPromptService (feeDetailsPrompt)  │
 * │  Fee summary             │  FeesPromptService (feeSummaryPrompt)  │
 * │  Fee reminder            │  FeesPromptService (feeReminderPrompt) │
 * │  Fee status explanation  │  FeesPromptService + KB (status explain)│
 * │  Collection workflow     │  FeesPromptService (collectionPrompt)  │
 * │  Fee policy/rule Q       │  FeesKnowledgeBaseService              │
 * │  Fee collection report   │  FeesRecommendationService + MCP       │
 * │  Recommendation          │  FeesRecommendationService             │
 * │  Fee data query          │  FeesAgent via fees.getPending MCP     │
 * │  Collect/modify fee      │  FeesGuardrailService + Workflow       │
 * └─────────────────────────┴─────────────────────────────────────────┘
 *
 * ┌─────────────────────────┬─────────────────────────────────────────┐
 * │  Intent Classifier key   │  Question patterns it matches           │
 * ├─────────────────────────┼─────────────────────────────────────────┤
 * │  fees_query              │  "pending fees", "fee details",        │
 * │                          │  "fee summary", "fee reminder",        │
 * │                          │  "fee policy", "collection report",    │
 * │                          │  "how much fee is pending", etc.       │
 * └─────────────────────────┴─────────────────────────────────────────┘
 *
 * ┌─────────────────────────┬─────────────────────────────────────────┐
 * │  Lifecycle stage         │  What happens                           │
 * ├─────────────────────────┼─────────────────────────────────────────┤
 * │  Conversation (1)        │  User asks "Show pending fees"          │
 * │  Generative AI (2)       │  Classifier matches fees_query intent   │
 * │  Agent (3)               │  FeesAgent runs, reads real fees data   │
 * │  Planning (4)            │  DeterministicPlanner resolves steps    │
 * │  MCP Tool (5-6)          │  fees.getPending called via MCP         │
 * │  Real Data (7)           │  DB query returns actual fees rows      │
 * │  Evidence (8)            │  Data stored as evidence                │
 * │  Reasoning (9)           │  Data analyzed, patterns identified     │
 * │  Recommendation (10)     │  Recommendation drafted (if applicable) │
 * │  Human Approval (11)     │  Approval required for write actions    │
 * │  Action (12)             │  Fee collected/modified via workflow    │
 * └─────────────────────────┴─────────────────────────────────────────┘
 */
class FeesCapabilityMap
{
    /**
     * Every Fees AI capability, its use cases, data source, and output.
     *
     * @return array<int, array{
     *   capability: string,
     *   intent: string,
     *   question_patterns: array<string>,
     *   service: string,
     *   input_data: string,
     *   output: string,
     *   guardrails: array<string>,
     *   mcp_tool: string|null,
     *   workflow: string|null,
     *   requires_confirmation: bool,
     *   audit_logged: bool
     * }>
     */
    public static function capabilities(): array
    {
        return [
            [
                'capability' => 'Conversational AI — Data Query',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Show me pending fees',
                    'What are the pending fees for student X',
                    'How much fee is pending',
                    'Show me students with unpaid fees',
                    'Are there any defaulters',
                    'Show fee details for student X',
                    'What is the total fee collection',
                    'Show me fee summary',
                    'Are fees still pending for student X',
                ],
                'service' => FeesPromptService::class . '::pendingFeesPrompt',
                'input_data' => 'Real fees_breackoff + tblstudent + fees_title data filtered by sub_institute_id and syear',
                'output' => 'Structured list of students with pending amounts, totals, record count',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission'],
                'mcp_tool' => 'fees.getPending',
                'workflow' => null,
                'requires_confirmation' => false,
                'audit_logged' => false,
            ],
            [
                'capability' => 'Conversational AI — Fee Details',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Fee details for student X',
                    'What is student X fee structure',
                    'Show fee breakdown for student X',
                ],
                'service' => FeesPromptService::class . '::feeDetailsPrompt',
                'input_data' => 'fees_breackoff + tblstudent + fees_collect receipts for specific student',
                'output' => 'Complete fee breakdown: demands, payments, receipts, balances',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission'],
                'mcp_tool' => null,
                'workflow' => null,
                'requires_confirmation' => false,
                'audit_logged' => false,
            ],
            [
                'capability' => 'Conversational AI — Fee Summary',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Show fee summary',
                    'What is the total fee collection',
                    'How much has been collected this year',
                    'Fee collection summary',
                ],
                'service' => FeesPromptService::class . '::feeSummaryPrompt',
                'input_data' => 'fees_breackoff total demand + fees_collect total collected, headwise breakdown',
                'output' => 'Demand vs collected totals, receipt count, headwise fee breakdown',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission'],
                'mcp_tool' => 'fees.collection_report',
                'workflow' => null,
                'requires_confirmation' => false,
                'audit_logged' => false,
            ],
            [
                'capability' => 'Conversational AI — Fee Reminder',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Send fee reminder to student X',
                    'Draft a fee reminder',
                    'Fee reminder message for student X',
                ],
                'service' => FeesPromptService::class . '::feeReminderPrompt',
                'input_data' => 'Pending fees for student + last receipt details',
                'output' => 'Draft reminder message with actual pending amounts',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission'],
                'mcp_tool' => null,
                'workflow' => null,
                'requires_confirmation' => false,
                'audit_logged' => false,
            ],
            [
                'capability' => 'Knowledge Base — Fee Policies',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Can students pay fees in installments',
                    'What is the late fee policy',
                    'What are the refund rules',
                    'How do I apply for scholarship',
                    'Fee payment rules',
                    'When are fees due',
                ],
                'service' => FeesKnowledgeBaseService::class . '::policyFor / findPolicies',
                'input_data' => 'knowledge_base_detail table, category=fees, status=1',
                'output' => 'Matching policy content with title and details',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission'],
                'mcp_tool' => null,
                'workflow' => null,
                'requires_confirmation' => false,
                'audit_logged' => false,
            ],
            [
                'capability' => 'Generative AI — Fee Report',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Create a pending fees report',
                    'Generate a fee collection report',
                    'Make a report for pending fees',
                    'Headwise fee report',
                ],
                'service' => FeesPromptService::class . '::pendingFeesReportPrompt + ai.templates.render',
                'input_data' => 'Real pending fees data grouped by student, with template rendering',
                'output' => 'Formatted report using fees AI templates',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission'],
                'mcp_tool' => 'ai.templates.render',
                'workflow' => null,
                'requires_confirmation' => false,
                'audit_logged' => false,
            ],
            [
                'capability' => 'Recommendation Engine — Collection Reminder',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Which students need fee reminders',
                    'Who hasn\'t paid fees',
                    'Fee collection action needed',
                ],
                'service' => FeesRecommendationService::class . '::draftForStudent',
                'input_data' => 'Student fee data + policy context',
                'output' => 'Draft recommendation: action_type, risk_level, rationale, subject_entity_key',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission', 'governance'],
                'mcp_tool' => null,
                'workflow' => 'fees_collection',
                'requires_confirmation' => false,
                'audit_logged' => true,
            ],
            [
                'capability' => 'Recommendation Engine — Collection Report',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Fee collection review',
                    'Collection analysis',
                    'How is fee collection going',
                ],
                'service' => FeesRecommendationService::class . '::draftCollectionReport',
                'input_data' => 'Aggregate fees data: demand, collected, outstanding',
                'output' => 'Collection report recommendation with risk level',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission', 'governance'],
                'mcp_tool' => null,
                'workflow' => null,
                'requires_confirmation' => false,
                'audit_logged' => true,
            ],
            [
                'capability' => 'Agent — Comprehensive Fees Analysis',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Analyze fee situation for student X',
                    'Full fees analysis',
                    'Complete fees report for student X',
                ],
                'service' => 'App\Agents\Fees\FeesAgent::run',
                'input_data' => 'All fee data via FeesPromptService + Knowledge Base + Breakoff summary',
                'output' => 'Structured analysis: prompt, knowledge_base, breakoff_summary',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission', 'prompt_injection'],
                'mcp_tool' => 'fees.getPending',
                'workflow' => 'fees_collection',
                'requires_confirmation' => false,
                'audit_logged' => true,
            ],
            [
                'capability' => 'Guardrails + Workflow — Collect Fee',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Collect the pending fee for this student',
                    'Record a fee payment',
                    'Take payment for student X',
                ],
                'service' => 'FeesGuardrailService::validateForFeesAction + WorkflowEngine',
                'input_data' => 'Student ID + amount + payment details',
                'output' => 'Confirmation required → Approved → Executed → Audited',
                'guardrails' => [
                    'institute_scope',
                    'no_fake_data',
                    'fees.collect_permission',
                    'amount_accuracy',
                    'action_confirmation',
                    'policy_compliance',
                    'prompt_injection',
                ],
                'mcp_tool' => null,
                'workflow' => 'fees_collection',
                'requires_confirmation' => true,
                'audit_logged' => true,
            ],
            [
                'capability' => 'Guardrails + Workflow — Refund Fee',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Refund fee for student X',
                    'Cancel fee for student X',
                    'Process fee refund',
                ],
                'service' => 'FeesGuardrailService::validateForFeesAction + WorkflowEngine',
                'input_data' => 'Student ID + refund amount + reason',
                'output' => 'Confirmation required → Approved → Refunded → Audited',
                'guardrails' => [
                    'institute_scope',
                    'no_fake_data',
                    'fees.write_permission',
                    'amount_accuracy',
                    'action_confirmation',
                    'policy_compliance',
                    'prompt_injection',
                ],
                'mcp_tool' => null,
                'workflow' => 'fees_collection',
                'requires_confirmation' => true,
                'audit_logged' => true,
            ],
            [
                'capability' => 'Conversational AI — Collection Workflow',
                'intent' => 'fees_query',
                'question_patterns' => [
                    'Fee collection workflow for student X',
                    'What needs to be collected',
                    'Collection steps for student X',
                ],
                'service' => FeesPromptService::class . '::collectionWorkflowPrompt',
                'input_data' => 'Student fee details: total, paid, pending, payment info',
                'output' => 'Workflow steps with current status and next actions',
                'guardrails' => ['institute_scope', 'no_fake_data', 'fees.read_permission'],
                'mcp_tool' => null,
                'workflow' => 'fees_collection',
                'requires_confirmation' => false,
                'audit_logged' => false,
            ],
        ];
    }

    /**
     * Get the capability for a specific question pattern.
     *
     * @return array<string, mixed>|null
     */
    public static function findForQuestion(string $question): ?array
    {
        foreach (self::capabilities() as $capability) {
            foreach ($capability['question_patterns'] as $pattern) {
                if (stripos($question, $pattern) !== false) {
                    return $capability;
                }
            }
        }

        return null;
    }
}