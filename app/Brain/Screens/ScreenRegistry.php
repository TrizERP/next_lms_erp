<?php

namespace App\Brain\Screens;

/**
 * ONE definition per Enterprise Brain screen.
 *
 * The shape mirrors hp-enterprise-brain/web/src/shell/viewMeta.ts — the same
 * seven sections, the same screens — but each screen is bound here to the
 * tables it actually reads in this LMS database, so a screen either shows real
 * tenant rows or says plainly that its store has not been provisioned.
 *
 * Screens with their own behaviour (capabilities, ingestion, settings,
 * departments, people, overview) have dedicated controller actions and are
 * listed here only for their metadata.
 */
class ScreenRegistry
{
    public const SECTIONS = [
        'overview' => 'Overview',
        'foundation' => 'Foundation',
        'intelligence-loop' => 'Intelligence Loop',
        'analytics' => 'Analytics',
        'knowledge' => 'Knowledge',
        'automation' => 'Automation',
        'account' => 'Account',
    ];

    public static function all(): array
    {
        return [
            /* ---------------------------------------------------------- Overview */
            'overview' => [
                'title' => 'Organization',
                'section' => 'overview',
                'dedicated' => true,
                'description' => 'What this organization contains, and how far its data has travelled through the loop.',
            ],

            /* -------------------------------------------------------- Foundation */
            'departments' => [
                'title' => 'Departments',
                'section' => 'foundation',
                'dedicated' => true,
                'description' => 'How the organization is structured, and who leads each unit.',
            ],
            'people' => [
                'title' => 'People',
                'section' => 'foundation',
                'dedicated' => true,
                'description' => 'Everyone recorded in this organization, and whose record is incomplete.',
            ],
            'capabilities' => [
                'title' => 'Capabilities',
                'section' => 'foundation',
                'dedicated' => true,
                'description' => 'What people need to be able to do, and who is assigned to each.',
            ],
            'ingestion' => [
                'title' => 'Ingestion',
                'section' => 'foundation',
                'dedicated' => true,
                'description' => 'Bring this organization’s LMS data into the Brain.',
            ],

            /* ------------------------------------------------ Intelligence Loop */
            'signals' => [
                'title' => 'Signals',
                'section' => 'intelligence-loop',
                'description' => 'What the data has flagged, and who it concerns.',
                'metrics' => [
                    ['key' => 'total', 'label' => 'Signals', 'table' => 'hpbrain_signals'],
                    ['key' => 'open', 'label' => 'Open', 'table' => 'hpbrain_signals', 'where' => ['status' => 'open']],
                    ['key' => 'critical', 'label' => 'Critical', 'table' => 'hpbrain_signals', 'where' => ['severity' => 'critical']],
                    ['key' => 'evidence', 'label' => 'Evidence linked', 'table' => 'hpbrain_evidence'],
                ],
                'panels' => [
                    [
                        'key' => 'signals',
                        'title' => 'Signal stream',
                        'table' => 'hpbrain_signals',
                        'columns' => [
                            'classification' => 'Classification',
                            'source' => 'Source',
                            'priority' => 'Priority',
                            'severity' => 'Severity',
                            'confidence' => 'Confidence',
                            'related_entity_type' => 'Entity',
                            'status' => 'Status',
                            'created_date' => 'Raised',
                        ],
                    ],
                ],
            ],
            'evidence' => [
                'title' => 'Evidence',
                'section' => 'intelligence-loop',
                'description' => 'What supports each signal, and how firmly it is held.',
                'metrics' => [
                    ['key' => 'total', 'label' => 'Evidence records', 'table' => 'hpbrain_evidence'],
                    ['key' => 'verified', 'label' => 'Verified', 'table' => 'hpbrain_evidence', 'where' => ['status' => 'verified']],
                    ['key' => 'case_links', 'label' => 'Case links', 'table' => 'hpbrain_case_evidence'],
                ],
                'panels' => [
                    [
                        'key' => 'evidence',
                        'title' => 'Evidence ledger',
                        'table' => 'hpbrain_evidence',
                        'columns' => [
                            'evidence_type' => 'Type',
                            'source' => 'Source',
                            'confidence' => 'Confidence',
                            'provenance' => 'Provenance',
                            'status' => 'Status',
                            'observed_date' => 'Observed',
                        ],
                    ],
                    [
                        'key' => 'case_evidence',
                        'title' => 'Linked to cases',
                        'table' => 'hpbrain_case_evidence',
                        'columns' => [
                            'case_id' => 'Case',
                            'evidence_id' => 'Evidence',
                            'linked_date' => 'Linked',
                        ],
                    ],
                ],
            ],
            'deliberation' => [
                'title' => 'Deliberation',
                'section' => 'intelligence-loop',
                'description' => 'Open investigations and the decisions waiting on them.',
                'metrics' => [
                    ['key' => 'cases', 'label' => 'Cases', 'table' => 'hpbrain_cases'],
                    ['key' => 'open_cases', 'label' => 'Open cases', 'table' => 'hpbrain_cases', 'where' => ['status' => 'open']],
                    ['key' => 'hypotheses', 'label' => 'Hypotheses', 'table' => 'hpbrain_hypotheses'],
                    ['key' => 'recommendations', 'label' => 'Recommendations', 'table' => 'hpbrain_recommendations'],
                ],
                'panels' => [
                    [
                        'key' => 'cases',
                        'title' => 'Cases',
                        'table' => 'hpbrain_cases',
                        'columns' => [
                            'title' => 'Case',
                            'description' => 'Description',
                            'status' => 'Status',
                            'signal_id' => 'Signal',
                            'created_date' => 'Opened',
                        ],
                    ],
                    [
                        'key' => 'hypotheses',
                        'title' => 'Hypotheses',
                        'table' => 'hpbrain_hypotheses',
                        'columns' => [
                            'statement' => 'Statement',
                            'root_cause_family' => 'Root cause family',
                            'confidence' => 'Confidence',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'reasoning',
                        'title' => 'Reasoning steps',
                        'table' => 'hpbrain_reasoning_steps',
                        'columns' => [
                            'step_order' => '#',
                            'description' => 'Step',
                            'confidence_score' => 'Confidence',
                            'case_id' => 'Case',
                        ],
                    ],
                ],
            ],
            'workspace' => [
                'title' => 'Intelligence Workspace',
                'section' => 'intelligence-loop',
                'description' => 'What this organization currently knows about itself.',
                'metrics' => [
                    ['key' => 'signals', 'label' => 'Signals', 'table' => 'hpbrain_signals'],
                    ['key' => 'recommendations', 'label' => 'Recommendations', 'table' => 'hpbrain_recommendations'],
                    ['key' => 'decisions', 'label' => 'Decisions', 'table' => 'hpbrain_decisions'],
                    ['key' => 'outcomes', 'label' => 'Outcomes', 'table' => 'hpbrain_outcomes'],
                    ['key' => 'risks', 'label' => 'Risks', 'table' => 'hpbrain_risks'],
                ],
                'panels' => [
                    [
                        'key' => 'recommendations',
                        'title' => 'Recommendations',
                        'table' => 'hpbrain_recommendations',
                        'columns' => [
                            'title' => 'Recommendation',
                            'category' => 'Category',
                            'priority' => 'Priority',
                            'impact' => 'Impact',
                            'confidence' => 'Confidence',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'decisions',
                        'title' => 'Decisions',
                        'table' => 'hpbrain_decisions',
                        'columns' => [
                            'rationale' => 'Rationale',
                            'executor_type' => 'Executor',
                            'confidence' => 'Confidence',
                            'status' => 'Status',
                            'created_date' => 'Decided',
                        ],
                    ],
                    [
                        'key' => 'risks',
                        'title' => 'Risks',
                        'table' => 'hpbrain_risks',
                        'columns' => [
                            'category' => 'Category',
                            'probability' => 'Probability',
                            'impact' => 'Impact',
                            'score' => 'Score',
                            'mitigation' => 'Mitigation',
                            'status' => 'Status',
                        ],
                    ],
                ],
            ],
            'executions' => [
                'title' => 'Execution Center',
                'section' => 'intelligence-loop',
                'description' => 'What has been done about approved decisions, and the result.',
                'metrics' => [
                    ['key' => 'executions', 'label' => 'Executions', 'table' => 'hpbrain_eso_executions'],
                    ['key' => 'completed', 'label' => 'Completed', 'table' => 'hpbrain_eso_executions', 'where' => ['status' => 'completed']],
                    ['key' => 'failed', 'label' => 'Failed', 'table' => 'hpbrain_eso_executions', 'where' => ['status' => 'failed']],
                    ['key' => 'evidence', 'label' => 'Execution evidence', 'table' => 'hpbrain_eso_execution_evidence'],
                ],
                'panels' => [
                    [
                        'key' => 'executions',
                        'title' => 'Executions',
                        'table' => 'hpbrain_eso_executions',
                        'columns' => [
                            'eso_id' => 'ESO',
                            'executor_type' => 'Executor',
                            'status' => 'Status',
                            'started_date' => 'Started',
                            'completed_date' => 'Completed',
                            'error' => 'Error',
                        ],
                    ],
                    [
                        'key' => 'outcomes',
                        'title' => 'Outcomes',
                        'table' => 'hpbrain_outcomes',
                        'columns' => [
                            'result' => 'Result',
                            'confidence' => 'Confidence',
                            'feedback' => 'Feedback',
                            'created_date' => 'Recorded',
                        ],
                    ],
                ],
            ],

            /* --------------------------------------------------------- Analytics */
            'executive' => [
                'title' => 'Executive Dashboard',
                'section' => 'analytics',
                'description' => 'Organization health at a glance.',
                'metrics' => [
                    ['key' => 'capabilities', 'label' => 'Capabilities', 'table' => 'hpbrain_capabilities'],
                    ['key' => 'signals', 'label' => 'Signals', 'table' => 'hpbrain_signals'],
                    ['key' => 'decisions', 'label' => 'Decisions', 'table' => 'hpbrain_decisions'],
                    ['key' => 'executions', 'label' => 'Executions', 'table' => 'hpbrain_eso_executions'],
                    ['key' => 'metrics', 'label' => 'Metric points', 'table' => 'hpbrain_metrics'],
                ],
                'panels' => [
                    [
                        'key' => 'metrics',
                        'title' => 'Recorded metrics',
                        'table' => 'hpbrain_metrics',
                        'columns' => [
                            'metric_name' => 'Metric',
                            'metric_value' => 'Value',
                            'tags' => 'Tags',
                            'recorded_at' => 'Recorded',
                        ],
                    ],
                    [
                        'key' => 'telemetry',
                        'title' => 'Telemetry',
                        'table' => 'hpbrain_telemetry_events',
                        'columns' => [
                            'event_type' => 'Event',
                            'metric_name' => 'Metric',
                            'metric_value' => 'Value',
                            'unit' => 'Unit',
                            'recorded_date' => 'Recorded',
                        ],
                    ],
                ],
                'series' => [
                    ['key' => 'metric_trend', 'title' => 'Metric trend', 'table' => 'hpbrain_metrics', 'label' => 'metric_name', 'value' => 'metric_value', 'at' => 'recorded_at'],
                ],
            ],
            'decision-analytics' => [
                'title' => 'Decision Analytics',
                'section' => 'analytics',
                'description' => 'How decisions are performing over time.',
                'metrics' => [
                    ['key' => 'decisions', 'label' => 'Decisions', 'table' => 'hpbrain_decisions'],
                    ['key' => 'approved', 'label' => 'Approved', 'table' => 'hpbrain_decisions', 'where' => ['status' => 'approved']],
                    ['key' => 'outcomes', 'label' => 'Outcomes', 'table' => 'hpbrain_outcomes'],
                    ['key' => 'learnings', 'label' => 'Learnings', 'table' => 'hpbrain_learnings'],
                ],
                'panels' => [
                    [
                        'key' => 'decisions',
                        'title' => 'Decisions',
                        'table' => 'hpbrain_decisions',
                        'columns' => [
                            'rationale' => 'Rationale',
                            'executor_type' => 'Executor',
                            'status' => 'Status',
                            'confidence' => 'Confidence',
                            'created_date' => 'Decided',
                        ],
                    ],
                    [
                        'key' => 'outcomes',
                        'title' => 'Outcomes',
                        'table' => 'hpbrain_outcomes',
                        'columns' => [
                            'decision_id' => 'Decision',
                            'result' => 'Result',
                            'confidence' => 'Confidence',
                            'created_date' => 'Recorded',
                        ],
                    ],
                ],
                'breakdowns' => [
                    ['key' => 'by_status', 'title' => 'Decisions by status', 'table' => 'hpbrain_decisions', 'group' => 'status'],
                ],
            ],
            'decision-intelligence' => [
                'title' => 'Decision Intelligence',
                'section' => 'analytics',
                'description' => 'Patterns across decisions, risks and outcomes.',
                'metrics' => [
                    ['key' => 'risks', 'label' => 'Risks', 'table' => 'hpbrain_risks'],
                    ['key' => 'patterns', 'label' => 'Reasoning patterns', 'table' => 'hpbrain_reasoning_patterns'],
                    ['key' => 'learnings', 'label' => 'Learnings', 'table' => 'hpbrain_learnings'],
                ],
                'panels' => [
                    [
                        'key' => 'risks',
                        'title' => 'Risk register',
                        'table' => 'hpbrain_risks',
                        'columns' => [
                            'category' => 'Category',
                            'probability' => 'Probability',
                            'impact' => 'Impact',
                            'score' => 'Score',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'patterns',
                        'title' => 'Reasoning patterns',
                        'table' => 'hpbrain_reasoning_patterns',
                        'columns' => [
                            'pattern_code' => 'Code',
                            'name' => 'Pattern',
                            'pattern_type' => 'Type',
                            'version' => 'Version',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'learnings',
                        'title' => 'Learnings',
                        'table' => 'hpbrain_learnings',
                        'columns' => [
                            'pattern' => 'Pattern',
                            'description' => 'Description',
                            'confidence' => 'Confidence',
                            'reusable' => 'Reusable',
                        ],
                    ],
                ],
                'breakdowns' => [
                    ['key' => 'risk_category', 'title' => 'Risks by category', 'table' => 'hpbrain_risks', 'group' => 'category'],
                ],
            ],
            'mental-models' => [
                'title' => 'Organizational Knowledge',
                'section' => 'analytics',
                'description' => 'The mental models the organization reasons with.',
                'metrics' => [
                    ['key' => 'models', 'label' => 'Mental models', 'table' => 'hpbrain_mental_models'],
                    ['key' => 'active', 'label' => 'Active', 'table' => 'hpbrain_mental_models', 'where' => ['status' => 'active']],
                    ['key' => 'learnings', 'label' => 'Reinforcing learnings', 'table' => 'hpbrain_learnings'],
                ],
                'panels' => [
                    [
                        'key' => 'models',
                        'title' => 'Mental models',
                        'table' => 'hpbrain_mental_models',
                        'columns' => [
                            'name' => 'Model',
                            'domain' => 'Domain',
                            'description' => 'Description',
                            'confidence' => 'Confidence',
                            'reinforcement_count' => 'Reinforced',
                            'status' => 'Status',
                        ],
                    ],
                ],
                'breakdowns' => [
                    ['key' => 'by_domain', 'title' => 'Models by domain', 'table' => 'hpbrain_mental_models', 'group' => 'domain'],
                ],
            ],

            /* --------------------------------------------------------- Knowledge */
            'graph' => [
                'title' => 'Graph Explorer',
                'section' => 'knowledge',
                'description' => 'Entities and the relationships between them.',
                'metrics' => [
                    ['key' => 'entities', 'label' => 'Context entities', 'table' => 'hpbrain_context_entities'],
                    ['key' => 'capabilities', 'label' => 'Capabilities', 'table' => 'hpbrain_capabilities'],
                    ['key' => 'assignments', 'label' => 'Assignments', 'table' => 'hpbrain_capability_assignments'],
                    ['key' => 'departments', 'label' => 'Departments', 'table' => 'hpbrain_departments'],
                    ['key' => 'people', 'label' => 'People', 'table' => 'hpbrain_people'],
                ],
                'panels' => [
                    [
                        'key' => 'entities',
                        'title' => 'Context entities',
                        'table' => 'hpbrain_context_entities',
                        'columns' => [
                            'entity_type' => 'Type',
                            'key_term' => 'Term',
                            'canonical_meaning' => 'Canonical meaning',
                            'tenant_specific_value' => 'Tenant value',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'edges',
                        'title' => 'Capability → target edges',
                        'table' => 'hpbrain_capability_assignments',
                        'columns' => [
                            'capability_id' => 'Capability',
                            'target_type' => 'Target type',
                            'target_id' => 'Target',
                            'status' => 'Status',
                            'assigned_date' => 'Assigned',
                        ],
                    ],
                ],
            ],
            'kasba' => [
                'title' => 'KASBA Explorer',
                'section' => 'knowledge',
                'dedicated' => true,
                'description' => 'Knowledge, ability, skill, behaviour and attitude across this organization.',
            ],
            'knowledge-library' => [
                'title' => 'Knowledge Library',
                'section' => 'knowledge',
                'description' => 'Reusable knowledge assets.',
                'metrics' => [
                    ['key' => 'assets', 'label' => 'Assets', 'table' => 'hpbrain_knowledge_assets'],
                    ['key' => 'published', 'label' => 'Published', 'table' => 'hpbrain_knowledge_assets', 'where' => ['status' => 'published']],
                    ['key' => 'processes', 'label' => 'Process definitions', 'table' => 'hpbrain_process_definitions'],
                ],
                'panels' => [
                    [
                        'key' => 'assets',
                        'title' => 'Knowledge assets',
                        'table' => 'hpbrain_knowledge_assets',
                        'search' => ['title', 'category', 'content'],
                        'columns' => [
                            'title' => 'Title',
                            'category' => 'Category',
                            'tags' => 'Tags',
                            'confidence' => 'Confidence',
                            'reuse_count' => 'Reuse',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'processes',
                        'title' => 'Process library',
                        'table' => 'hpbrain_process_definitions',
                        'columns' => [
                            'process_code' => 'Code',
                            'name' => 'Process',
                            'category' => 'Category',
                            'version' => 'Version',
                            'status' => 'Status',
                        ],
                    ],
                ],
                'breakdowns' => [
                    ['key' => 'by_category', 'title' => 'Assets by category', 'table' => 'hpbrain_knowledge_assets', 'group' => 'category'],
                ],
            ],
            'memory' => [
                'title' => 'Memory',
                'section' => 'knowledge',
                'description' => 'What the Brain retains between sessions.',
                'metrics' => [
                    ['key' => 'sessions', 'label' => 'Conversation sessions', 'table' => 'hpbrain_conversation_sessions'],
                    ['key' => 'messages', 'label' => 'Messages', 'table' => 'hpbrain_conversation_messages'],
                    ['key' => 'events', 'label' => 'Stored events', 'table' => 'hpbrain_event_store'],
                    ['key' => 'learnings', 'label' => 'Learnings', 'table' => 'hpbrain_learnings'],
                ],
                'panels' => [
                    [
                        'key' => 'sessions',
                        'title' => 'Conversation sessions',
                        'table' => 'hpbrain_conversation_sessions',
                        'columns' => [
                            'title' => 'Session',
                            'context_type' => 'Context',
                            'pinned' => 'Pinned',
                            'created_date' => 'Started',
                        ],
                    ],
                    [
                        'key' => 'events',
                        'title' => 'Event store',
                        'table' => 'hpbrain_event_store',
                        'columns' => [
                            'type' => 'Event',
                            'entity_type' => 'Entity type',
                            'entity_id' => 'Entity',
                            'status' => 'Status',
                            'created_at' => 'Recorded',
                        ],
                    ],
                ],
            ],
            'ai-assistant' => [
                'title' => 'AI Assistant',
                'section' => 'knowledge',
                'dedicated' => true,
                'description' => 'Context-scoped search, conversation and AI operation history.',
            ],
            'eso-library' => [
                'title' => 'ESO Library',
                'section' => 'knowledge',
                'description' => 'Executable strategic objectives.',
                'metrics' => [
                    ['key' => 'definitions', 'label' => 'ESO definitions', 'table' => 'hpbrain_eso_definitions'],
                    ['key' => 'active', 'label' => 'Active', 'table' => 'hpbrain_eso_definitions', 'where' => ['status' => 'active']],
                    ['key' => 'executions', 'label' => 'Executions', 'table' => 'hpbrain_eso_executions'],
                    ['key' => 'efficacy', 'label' => 'Efficacy records', 'table' => 'hpbrain_eso_efficacy_records'],
                ],
                'panels' => [
                    [
                        'key' => 'definitions',
                        'title' => 'ESO definitions',
                        'table' => 'hpbrain_eso_definitions',
                        'search' => ['name', 'eso_code', 'objective'],
                        'columns' => [
                            'eso_code' => 'Code',
                            'name' => 'Name',
                            'objective' => 'Objective',
                            'trust_level' => 'Trust',
                            'version' => 'Version',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'efficacy',
                        'title' => 'Efficacy',
                        'table' => 'hpbrain_eso_efficacy_records',
                        'columns' => [
                            'eso_definition_id' => 'ESO',
                            'gap_type' => 'Gap type',
                            'efficacy_score' => 'Score',
                            'sample_size' => 'Sample',
                            'computed_date' => 'Computed',
                        ],
                    ],
                ],
            ],

            /* -------------------------------------------------------- Automation */
            'agents' => [
                'title' => 'Agent Monitor',
                'section' => 'automation',
                'description' => 'What the agents are doing, and on whose authority.',
                'metrics' => [
                    ['key' => 'executors', 'label' => 'Executors', 'table' => 'hpbrain_executors'],
                    ['key' => 'available', 'label' => 'Available', 'table' => 'hpbrain_executors', 'where' => ['available' => 1]],
                    ['key' => 'ai_runs', 'label' => 'AI executions', 'table' => 'hpbrain_ai_executions'],
                    ['key' => 'notifications', 'label' => 'Notifications', 'table' => 'hpbrain_notifications'],
                ],
                'panels' => [
                    [
                        'key' => 'executors',
                        'title' => 'Executors',
                        'table' => 'hpbrain_executors',
                        'columns' => [
                            'name' => 'Executor',
                            'executor_type' => 'Type',
                            'trust_level' => 'Trust',
                            'current_workload' => 'Workload',
                            'max_concurrent' => 'Max',
                            'available' => 'Available',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'ai_executions',
                        'title' => 'AI execution history',
                        'table' => 'hpbrain_ai_executions',
                        'columns' => [
                            'service_name' => 'Service',
                            'provider' => 'Provider',
                            'model' => 'Model',
                            'status' => 'Status',
                            'latency_ms' => 'Latency (ms)',
                            'created_date' => 'Ran',
                        ],
                    ],
                ],
            ],
            'tasks' => [
                'title' => 'Task Orchestrator',
                'section' => 'automation',
                'description' => 'Scheduled and queued work.',
                'metrics' => [
                    ['key' => 'tasks', 'label' => 'Capability tasks', 'table' => 'hpbrain_capability_tasks'],
                    ['key' => 'open', 'label' => 'Open', 'table' => 'hpbrain_capability_tasks', 'where' => ['status' => 'open']],
                    ['key' => 'executions', 'label' => 'Executions', 'table' => 'hpbrain_eso_executions'],
                    ['key' => 'processes', 'label' => 'Process definitions', 'table' => 'hpbrain_process_definitions'],
                ],
                'panels' => [
                    [
                        'key' => 'tasks',
                        'title' => 'Capability tasks',
                        'table' => 'hpbrain_capability_tasks',
                        'search' => ['name', 'description'],
                        'columns' => [
                            'name' => 'Task',
                            'description' => 'Description',
                            'capability_id' => 'Capability',
                            'evidence_required' => 'Evidence required',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'key' => 'executions',
                        'title' => 'Execution queue',
                        'table' => 'hpbrain_eso_executions',
                        'columns' => [
                            'eso_id' => 'ESO',
                            'executor_type' => 'Executor',
                            'status' => 'Status',
                            'started_date' => 'Started',
                            'completed_date' => 'Completed',
                        ],
                    ],
                ],
            ],
            'policies' => [
                'title' => 'Policy Management',
                'section' => 'automation',
                'description' => 'The rules execution must respect.',
                'metrics' => [
                    ['key' => 'policies', 'label' => 'Policies', 'table' => 'hpbrain_policies'],
                    ['key' => 'active', 'label' => 'Active', 'table' => 'hpbrain_policies', 'where' => ['status' => 'active']],
                    ['key' => 'executors', 'label' => 'Executors bound', 'table' => 'hpbrain_executors'],
                ],
                'panels' => [
                    [
                        'key' => 'policies',
                        'title' => 'Policies',
                        'table' => 'hpbrain_policies',
                        'search' => ['name', 'scope'],
                        'columns' => [
                            'name' => 'Policy',
                            'policy_type' => 'Type',
                            'scope' => 'Scope',
                            'trust_levels' => 'Trust levels',
                            'version' => 'Version',
                            'status' => 'Status',
                        ],
                    ],
                ],
            ],

            /* ----------------------------------------------------------- Account */
            'settings' => [
                'title' => 'Settings',
                'section' => 'account',
                'dedicated' => true,
                'description' => 'Configuration, keys and audit for this organization.',
            ],
        ];
    }

    public static function get(string $screen): ?array
    {
        $all = self::all();
        return $all[$screen] ?? null;
    }

    /** Screen keys belonging to a section, in registry order. */
    public static function inSection(string $section): array
    {
        $keys = [];
        foreach (self::all() as $key => $meta) {
            if ($meta['section'] === $section) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
