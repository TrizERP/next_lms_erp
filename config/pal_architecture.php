<?php

/*
|--------------------------------------------------------------------------
| New PAL → Administration — the PAL V4 architecture control plane
|--------------------------------------------------------------------------
|
| This file is the SHIPPED DEFAULT for the nine architecture subsystems the
| Master Blueprint specifies (PAL_V4_Master_Blueprint.md). Every value here is
| transcribed from that document — the 9 intelligence layers (§5), the 12-step
| adaptive loop (§6), the BKT parameters and mastery bands (§5 Layer 4), the
| four NEP 2020 HPC stages (§2), the Stream/Mountain/Sky rubric (§3), the Neo4j
| node reference (Appendix A), the five agent personas (§5 Layer 8), the
| 9-dimension student model (§4) and the career intelligence tags (§10).
|
| Two rules govern this file:
|
|   1. It holds STRUCTURE AND PARAMETERS, never learner data and never content.
|      Concepts, questions, misconceptions and chapters are read from the
|      database — see config/pal_content_model.php for that half.
|
|   2. It is a DEFAULT, not the live value. An institute's administrator edits
|      these through New PAL → Administration; the edit is stored per tenant in
|      `pal_architecture_settings` and merged over this file on read by
|      App\Services\PAL\Administration\ArchitectureRegistry. Re-deploying never
|      clobbers a tenant's tuning, and a tenant that has never touched a
|      subsystem transparently tracks whatever this file says.
|
| The closed framework vocabularies (CASEL, NGSS, NCDG, RIASEC, Gardner, music,
| sports, finance) are NOT duplicated here — they live in config/pal_v4.php and
| are read through FrameworkCatalogService, so Administration and the pedagogy
| engine can never drift apart.
|
| `panels` on each subsystem describes how the workspace renders it. Six panel
| kinds exist and the frontend implements exactly those six:
|
|   live     COMPUTED OUTPUT — what this subsystem produces when run against the
|            estate's real learner evidence (App\Services\PAL\Runtime\SubsystemRuntime).
|            Declared first on every subsystem so the page opens on what the
|            configuration actually does, not on the configuration itself.
|   metrics  server-computed health figures (read-only, from ArchitectureHealthService)
|   records  a list of typed rows, some columns editable
|   params   scalar settings — number, select, text, toggle
|   matrix   a rubric grid, rows × columns of free text
|   catalog  a read-only reference list (schema, vocabulary)
|
| `live` holds no settings and is never writable: ArchitectureRegistry's
| WRITABLE_KINDS excludes it, so a client cannot POST to it.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Editing guard rails
    |--------------------------------------------------------------------------
    |
    | Administration writes architecture parameters that change how every
    | learner is assessed, so the write path is deliberately narrow.
    |
    */
    'guards' => [
        // Students never see this workspace; teachers read it; only these
        // profiles may write. Matched case-insensitively against the caller's
        // user_profile_name, in addition to the is_admin flag on the token.
        'writer_profiles' => ['admin', 'administrator', 'principal', 'director', 'super admin'],

        // A subsystem whose settings would take effect on live learners is
        // held behind an explicit confirmation in the UI.
        'confirm_on_write' => ['mastery-model', 'progression-rubric', 'ai-agents'],
    ],

    'subsystems' => [

        /*
        |======================================================================
        | 1. The 9-layer intelligence architecture — Master Blueprint §5
        |======================================================================
        */
        'intelligence-layers' => [
            'label' => 'Intelligence layers',
            'tagline' => 'The nine layers that turn a learner event into an adapted next step.',
            'icon' => 'layers',
            'source_document' => 'PAL_V4_Master_Blueprint.md §5',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Live layer readiness'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Layer readiness'],
                [
                    'kind' => 'records',
                    'key' => 'layers',
                    'title' => 'Layer registry',
                    'help' => 'Each layer names the service that owns it. A layer may be disabled to take it out of the adaptive loop without removing its data.',
                    'columns' => [
                        ['key' => 'ordinal', 'label' => '#', 'type' => 'number', 'editable' => false, 'width' => 'narrow'],
                        ['key' => 'name', 'label' => 'Layer', 'type' => 'text', 'editable' => false],
                        ['key' => 'function', 'label' => 'Function', 'type' => 'text', 'editable' => false, 'width' => 'wide'],
                        ['key' => 'owner_service', 'label' => 'Owning service', 'type' => 'code', 'editable' => false, 'width' => 'wide'],
                        ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'editable' => true, 'width' => 'narrow'],
                    ],
                ],
            ],
            'settings' => [
                'layers' => [
                    [
                        'ordinal' => 1,
                        'key' => 'learner_profile',
                        'name' => 'Learner Profile Engine',
                        'function' => 'Builds and maintains the 9-dimension student model in real time.',
                        'owner_service' => 'App\Services\PAL\Intelligence\LearnerStateEngine',
                        'reads_tables' => ['pal_competencies', 'pal_learner_states', 'pal_learning_sessions'],
                        'enabled' => true,
                    ],
                    [
                        'ordinal' => 2,
                        'key' => 'content_intelligence',
                        'name' => 'Content Intelligence Engine',
                        'function' => 'Manages the 4-type content model and selects the optimal content node for each learning moment.',
                        'owner_service' => 'App\Services\PAL\Content\ContentIntelligenceService',
                        'reads_tables' => ['pal_content_metadata', 'pal_concept_metadata'],
                        'enabled' => true,
                    ],
                    [
                        'ordinal' => 3,
                        'key' => 'knowledge_graph',
                        'name' => 'Knowledge Graph Traversal',
                        'function' => 'Navigates the competency DAG for prerequisite satisfaction and path generation.',
                        'owner_service' => 'App\Services\PAL\ULU\ULUGraphService',
                        'reads_tables' => [],
                        'enabled' => false,
                    ],
                    [
                        'ordinal' => 4,
                        'key' => 'bkt',
                        'name' => 'Bayesian Knowledge Tracing',
                        'function' => 'Updates mastery probability after every assessment response.',
                        'owner_service' => 'App\Services\PAL\Intelligence\LearnerStateEngine',
                        'reads_tables' => ['pal_competencies', 'pal_assessment_results'],
                        'enabled' => true,
                    ],
                    [
                        'ordinal' => 5,
                        'key' => 'misconception',
                        'name' => 'Misconception Diagnosis',
                        'function' => 'Matches wrong-answer patterns against the misconception library and triggers correctives.',
                        'owner_service' => 'App\Services\PAL\Intelligence\MisconceptionIntelligenceEngine',
                        'reads_tables' => ['pal_misconceptions', 'pal_learner_misconceptions'],
                        'enabled' => true,
                    ],
                    [
                        'ordinal' => 6,
                        'key' => 'retention',
                        'name' => 'Spaced Repetition & Retention',
                        'function' => 'Schedules reviews on the Ebbinghaus forgetting curve.',
                        'owner_service' => 'App\Services\PAL\Intelligence\LearningVelocityEngine',
                        'reads_tables' => ['lms_concept_mastery_log'],
                        'enabled' => true,
                    ],
                    [
                        'ordinal' => 7,
                        'key' => 'path_navigator',
                        'name' => 'Generative Path Navigator',
                        'function' => 'Generates Class A/B/C personalised paths by available data depth.',
                        'owner_service' => 'App\Services\PAL\Intelligence\RecommendationEngine',
                        'reads_tables' => ['pal_content_recommendations'],
                        'enabled' => true,
                    ],
                    [
                        'ordinal' => 8,
                        'key' => 'agentic_ai',
                        'name' => 'Agentic AI Orchestration',
                        'function' => 'Five specialised agents with defined autonomy levels and escalation conditions.',
                        'owner_service' => 'App\Services\PAL\AI\AIOrchestrationService',
                        'reads_tables' => [],
                        'enabled' => true,
                    ],
                    [
                        'ordinal' => 9,
                        'key' => 'standards',
                        'name' => 'Standards Framework Integration',
                        'function' => 'Maps every content node and learner event onto CASEL, NGSS, NCDG, music, sports and finance frameworks.',
                        'owner_service' => 'App\Services\PAL\Framework\FrameworkCatalogService',
                        'reads_tables' => ['pal_framework_progress'],
                        'enabled' => true,
                    ],
                ],
            ],
        ],

        /*
        |======================================================================
        | 2. The 12-step adaptive loop — Master Blueprint §6
        |======================================================================
        */
        'adaptive-loop' => [
            'label' => 'Adaptive loop',
            'tagline' => 'The twelve steps a session runs through, and which engine owns each.',
            'icon' => 'repeat',
            'source_document' => 'PAL_V4_Master_Blueprint.md §6',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'The loop, executed'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Loop readiness'],
                [
                    'kind' => 'params',
                    'key' => 'execution',
                    'title' => 'Execution policy',
                    'fields' => [
                        ['key' => 'in_session_adaptation', 'label' => 'In-session adaptation', 'type' => 'toggle', 'help' => 'Step 7 — adapt difficulty and modality mid-session rather than only between sessions.'],
                        ['key' => 'consecutive_wrong_switch_modality', 'label' => 'Wrong answers before modality switch', 'type' => 'number', 'min' => 1, 'max' => 6, 'help' => 'Blueprint default is 2.'],
                        ['key' => 'consecutive_correct_advance', 'label' => 'Correct answers before advancing difficulty', 'type' => 'number', 'min' => 1, 'max' => 8, 'help' => 'Blueprint default is 3.'],
                        ['key' => 'diagnostic_item_count', 'label' => 'Diagnostic entry items', 'type' => 'number', 'min' => 3, 'max' => 20, 'help' => 'Step 2 — adaptive entry items served at goal selection.'],
                        ['key' => 'diagnostic_target_success', 'label' => 'IRT target success probability', 'type' => 'number', 'min' => 0.3, 'max' => 0.95, 'step' => 0.05, 'help' => 'Blueprint targets P = 0.70.'],
                        ['key' => 'retention_probe_items', 'label' => 'Monthly retention probe items', 'type' => 'number', 'min' => 1, 'max' => 10, 'help' => 'Step 12 — questions asked on already-mastered concepts.'],
                    ],
                ],
                [
                    'kind' => 'records',
                    'key' => 'steps',
                    'title' => 'The twelve steps',
                    'columns' => [
                        ['key' => 'step', 'label' => 'Step', 'type' => 'number', 'editable' => false, 'width' => 'narrow'],
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'editable' => false],
                        ['key' => 'description', 'label' => 'What happens', 'type' => 'text', 'editable' => false, 'width' => 'wide'],
                        ['key' => 'layer', 'label' => 'Layer', 'type' => 'text', 'editable' => false, 'width' => 'narrow'],
                        ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'editable' => true, 'width' => 'narrow'],
                    ],
                ],
            ],
            'settings' => [
                'execution' => [
                    'in_session_adaptation' => true,
                    'consecutive_wrong_switch_modality' => 2,
                    'consecutive_correct_advance' => 3,
                    'diagnostic_item_count' => 8,
                    'diagnostic_target_success' => 0.70,
                    'retention_probe_items' => 3,
                ],
                'steps' => [
                    ['step' => 1, 'key' => 'goal_selection', 'name' => 'Goal selection', 'description' => 'Learner picks a concept, exam goal, skill or interest pathway; prior data decides Class A/B/C routing.', 'layer' => 7, 'enabled' => true],
                    ['step' => 2, 'key' => 'diagnostic', 'name' => 'Diagnostic assessment', 'description' => 'Adaptive entry items at the configured IRT target; time tracked for fluency.', 'layer' => 4, 'enabled' => true],
                    ['step' => 3, 'key' => 'graph_build', 'name' => 'Knowledge graph build', 'description' => 'BKT updates every affected concept; prerequisite chain traversed.', 'layer' => 3, 'enabled' => true],
                    ['step' => 4, 'key' => 'gap_identification', 'name' => 'Gap & misconception identification', 'description' => 'Wrong answers matched to the library; weak-concept priority queue generated.', 'layer' => 5, 'enabled' => true],
                    ['step' => 5, 'key' => 'path_generation', 'name' => 'Personalised path generation', 'description' => 'Class A/B/C routing; pedagogy selected from engagement history.', 'layer' => 7, 'enabled' => true],
                    ['step' => 6, 'key' => 'micro_delivery', 'name' => 'Micro-learning delivery', 'description' => 'Concept → practice L1–L3 → mini-assessment, in the learner\'s dominant modality and language.', 'layer' => 2, 'enabled' => true],
                    ['step' => 7, 'key' => 'in_session_adaptation', 'name' => 'Real-time in-session adaptation', 'description' => 'Modality switches and difficulty moves happen mid-session without waiting for a teacher.', 'layer' => 8, 'enabled' => true],
                    ['step' => 8, 'key' => 'ai_feedback', 'name' => 'AI feedback — why, not just score', 'description' => 'Misconception-specific narrative in the learner\'s language, with a corrective served immediately.', 'layer' => 8, 'enabled' => true],
                    ['step' => 9, 'key' => 'spaced_reinforcement', 'name' => 'Spaced reinforcement', 'description' => 'Review date computed from current mastery; notification queued.', 'layer' => 6, 'enabled' => true],
                    ['step' => 10, 'key' => 'adaptive_reassessment', 'name' => 'Adaptive reassessment', 'description' => 'Sky-level branching tasks and peer-explanation prompts for high performers.', 'layer' => 4, 'enabled' => true],
                    ['step' => 11, 'key' => 'mastery_update', 'name' => 'Mastery update', 'description' => 'BKT, fluency, all nine dimensions and the HPC rating refreshed; standards evidence accumulated.', 'layer' => 1, 'enabled' => true],
                    ['step' => 12, 'key' => 'retention_tracking', 'name' => 'Long-term retention tracking', 'description' => 'Monthly probe on mastered concepts; re-teach triggered on a forgetting breach.', 'layer' => 6, 'enabled' => true],
                ],
            ],
        ],

        /*
        |======================================================================
        | 3. BKT mastery model — Master Blueprint §5 Layer 4, §4.3
        |======================================================================
        */
        'mastery-model' => [
            'label' => 'Mastery model',
            'tagline' => 'Bayesian Knowledge Tracing parameters, mastery bands and the fluency formula.',
            'icon' => 'gauge',
            'source_document' => 'PAL_V4_Master_Blueprint.md §4.3, §5 Layer 4',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Computed mastery'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Mastery data'],
                [
                    'kind' => 'params',
                    'key' => 'bkt',
                    'title' => 'BKT parameters',
                    'help' => 'Estate-wide priors. A concept with enough response data calibrates its own values; these apply until it does.',
                    'fields' => [
                        ['key' => 'p_init', 'label' => 'P(L₀) — prior knowledge', 'type' => 'number', 'min' => 0.01, 'max' => 0.5, 'step' => 0.01, 'help' => 'Probability the learner already knows a concept before any evidence.'],
                        ['key' => 'p_transit', 'label' => 'P(T) — learning transition', 'type' => 'number', 'min' => 0.01, 'max' => 0.6, 'step' => 0.01, 'help' => 'Probability an attempt moves an unknown concept to known.'],
                        ['key' => 'p_slip', 'label' => 'P(S) — slip', 'type' => 'number', 'min' => 0.01, 'max' => 0.4, 'step' => 0.01, 'help' => 'Knows the concept but answers wrong. Above 0.3 makes mastery unreachable.'],
                        ['key' => 'p_guess', 'label' => 'P(G) — guess', 'type' => 'number', 'min' => 0.01, 'max' => 0.5, 'step' => 0.01, 'help' => 'Does not know but answers right. For 4-option MCQ, 0.25 is the floor.'],
                        ['key' => 'min_attempts_for_mastery', 'label' => 'Minimum attempts before mastery is credited', 'type' => 'number', 'min' => 1, 'max' => 15, 'help' => 'Stops a single lucky answer from unlocking the next concept.'],
                        ['key' => 'calibrate_per_concept', 'label' => 'Calibrate per concept from response data', 'type' => 'toggle'],
                    ],
                ],
                [
                    'kind' => 'params',
                    'key' => 'fluency',
                    'title' => 'Fluency formula',
                    'help' => 'Fluency (accuracy × speed) is the primary mastery index — 8/10 in 2 minutes is not 8/10 in 12 minutes.',
                    'fields' => [
                        ['key' => 'error_weight', 'label' => 'Error fluency weight', 'type' => 'number', 'min' => 0, 'max' => 1, 'step' => 0.05, 'help' => 'Net fluency = correct fluency − (error fluency × this). Blueprint uses 0.5.'],
                        ['key' => 'rolling_average_days', 'label' => 'Rolling average window (days)', 'type' => 'number', 'min' => 7, 'max' => 90, 'help' => 'Fluency delta compares the current session against this window.'],
                        ['key' => 'negative_delta_triggers_review', 'label' => 'Negative fluency delta triggers review', 'type' => 'toggle', 'help' => 'Treats a fluency drop as a forgetting-curve breach.'],
                    ],
                ],
                [
                    'kind' => 'records',
                    'key' => 'bands',
                    'title' => 'Mastery bands',
                    'help' => 'The band a learner falls in decides what content is served, which HPC tier is credited and when the concept is reviewed again.',
                    'columns' => [
                        ['key' => 'min', 'label' => 'From', 'type' => 'number', 'editable' => true, 'step' => 0.01, 'width' => 'narrow'],
                        ['key' => 'max', 'label' => 'To', 'type' => 'number', 'editable' => true, 'step' => 0.01, 'width' => 'narrow'],
                        ['key' => 'tier', 'label' => 'HPC tier', 'type' => 'select', 'editable' => true, 'options' => ['stream', 'mountain', 'sky'], 'width' => 'narrow'],
                        ['key' => 'serves', 'label' => 'Content served', 'type' => 'text', 'editable' => true, 'width' => 'wide'],
                        ['key' => 'review_interval_days', 'label' => 'Review after (days)', 'type' => 'number', 'editable' => true, 'min' => 1, 'max' => 120, 'width' => 'narrow'],
                    ],
                ],
            ],
            'settings' => [
                'bkt' => [
                    'p_init' => 0.15,
                    'p_transit' => 0.20,
                    'p_slip' => 0.10,
                    'p_guess' => 0.25,
                    'min_attempts_for_mastery' => 3,
                    'calibrate_per_concept' => true,
                ],
                'fluency' => [
                    'error_weight' => 0.5,
                    'rolling_average_days' => 30,
                    'negative_delta_triggers_review' => true,
                ],
                'bands' => [
                    ['key' => 'stream', 'min' => 0.00, 'max' => 0.49, 'tier' => 'stream', 'serves' => 'Concept learning content — the concept is not established yet', 'review_interval_days' => 1],
                    ['key' => 'mountain_approach', 'min' => 0.50, 'max' => 0.69, 'tier' => 'mountain', 'serves' => 'Practice ladder L1–L3', 'review_interval_days' => 1],
                    ['key' => 'mountain_mastery', 'min' => 0.70, 'max' => 0.84, 'tier' => 'mountain', 'serves' => 'Practice ladder L3–L4; spaced review scheduled', 'review_interval_days' => 3],
                    ['key' => 'sky_approach', 'min' => 0.85, 'max' => 0.92, 'tier' => 'sky', 'serves' => 'Practice ladder L4–L5; peer-teaching opportunity offered', 'review_interval_days' => 7],
                    ['key' => 'sky_mastery', 'min' => 0.93, 'max' => 1.00, 'tier' => 'sky', 'serves' => 'Expanded tasks; next concept unlocked', 'review_interval_days' => 30],
                ],
            ],
        ],

        /*
        |======================================================================
        | 4. NEP 2020 four-stage HPC architecture — Master Blueprint §2
        |======================================================================
        */
        'hpc-stages' => [
            'label' => 'HPC stages',
            'tagline' => 'NEP 2020\'s 5+3+3+4 model — what each stage prioritises and how PAL delivers it.',
            'icon' => 'graduation',
            'source_document' => 'PAL_V4_Master_Blueprint.md §2',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Live stage placement'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Stage coverage'],
                [
                    'kind' => 'params',
                    'key' => 'policy',
                    'title' => 'Stage gating',
                    'fields' => [
                        ['key' => 'enforce_bloom_ceiling', 'label' => 'Enforce the Bloom ceiling per stage', 'type' => 'toggle', 'help' => 'Refuses to serve a Foundational learner content above the stage ceiling.'],
                        ['key' => 'enforce_pedagogy_priority', 'label' => 'Prefer the stage\'s primary pedagogies', 'type' => 'toggle'],
                        ['key' => 'auto_assign_stage_from_grade', 'label' => 'Assign stage automatically from grade', 'type' => 'toggle', 'help' => 'Off means a stage must be set on the learner record explicitly.'],
                    ],
                ],
                [
                    'kind' => 'records',
                    'key' => 'stages',
                    'title' => 'The four stages',
                    'columns' => [
                        ['key' => 'label', 'label' => 'Stage', 'type' => 'text', 'editable' => false],
                        ['key' => 'grade_from', 'label' => 'Grade from', 'type' => 'number', 'editable' => true, 'min' => 0, 'max' => 12, 'width' => 'narrow'],
                        ['key' => 'grade_to', 'label' => 'Grade to', 'type' => 'number', 'editable' => true, 'min' => 0, 'max' => 12, 'width' => 'narrow'],
                        ['key' => 'bloom_ceiling', 'label' => 'Bloom ceiling', 'type' => 'select', 'editable' => true, 'options' => ['recall', 'understand', 'apply', 'analyse', 'evaluate', 'create'], 'width' => 'narrow'],
                        ['key' => 'primary_pedagogies', 'label' => 'Primary pedagogies', 'type' => 'tags', 'editable' => true, 'width' => 'wide'],
                        ['key' => 'primary_h5p', 'label' => 'Primary H5P types', 'type' => 'tags', 'editable' => true, 'width' => 'wide'],
                        ['key' => 'assessment_mode', 'label' => 'Assessment', 'type' => 'text', 'editable' => true],
                        ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'editable' => true, 'width' => 'narrow'],
                    ],
                ],
            ],
            'settings' => [
                'policy' => [
                    'enforce_bloom_ceiling' => true,
                    'enforce_pedagogy_priority' => true,
                    'auto_assign_stage_from_grade' => true,
                ],
                'stages' => [
                    [
                        'key' => 'foundational',
                        'label' => 'Foundational (Bal Vatika – Grade 2)',
                        'grade_from' => 0,
                        'grade_to' => 2,
                        'ages' => '3–8',
                        'bloom_ceiling' => 'apply',
                        'primary_pedagogies' => ['play_based', 'activity_based', 'art_integrated'],
                        'primary_h5p' => ['drag_and_drop', 'memory_game', 'image_hotspot'],
                        'assessment_mode' => 'Observational rubric — teacher and parent via conversational AI',
                        'priority_competencies' => [
                            'CG-1 Habits for health and safety',
                            'CG-2 Sensorial perceptions',
                            'CG-6 Natural environment care',
                            'CG-7 Observation and logical thinking',
                            'CG-8 Mathematical understanding',
                            'CG-13 Habits for formal learning',
                        ],
                        'enabled' => true,
                    ],
                    [
                        'key' => 'preparatory',
                        'label' => 'Preparatory (Grades 3–5)',
                        'grade_from' => 3,
                        'grade_to' => 5,
                        'ages' => '8–11',
                        'bloom_ceiling' => 'analyse',
                        'primary_pedagogies' => ['inquiry_based', 'experiential', 'activity_based'],
                        'primary_h5p' => ['interactive_video', 'course_presentation', 'drag_and_drop'],
                        'assessment_mode' => 'First full BKT baseline at Grade 3 entry; peer assessment activated',
                        'priority_competencies' => [
                            'Formalised literacy and numeracy progression',
                            'Social awareness (CASEL) formal tracking begins',
                            'RIASEC signal accumulation begins',
                        ],
                        'enabled' => true,
                    ],
                    [
                        'key' => 'middle',
                        'label' => 'Middle (Grades 6–8)',
                        'grade_from' => 6,
                        'grade_to' => 8,
                        'ages' => '11–14',
                        'bloom_ceiling' => 'evaluate',
                        'primary_pedagogies' => ['inquiry_based', 'scenario_based', 'collaborative'],
                        'primary_h5p' => ['branching_scenario', 'interactive_video', 'question_set'],
                        'assessment_mode' => 'Full 9-dimension student model; teacher misconception heatmap live',
                        'priority_competencies' => [
                            'Pre-vocational exploration (NEP vocational stream tags)',
                            'NGSS science practices in Science and Mathematics',
                            'NCDG goals CM1 and CM2 accumulate',
                            'Mountain → Sky progression targeted',
                        ],
                        'enabled' => true,
                    ],
                    [
                        'key' => 'secondary',
                        'label' => 'Secondary (Grades 9–12)',
                        'grade_from' => 9,
                        'grade_to' => 12,
                        'ages' => '15–18',
                        'bloom_ceiling' => 'create',
                        'primary_pedagogies' => ['project_based', 'scenario_based', 'experiential'],
                        'primary_h5p' => ['branching_scenario', 'documentation_tool', 'interactive_book'],
                        'assessment_mode' => 'Career pathway report at Grade 9 entry; employability index at 11–12',
                        'priority_competencies' => [
                            'Advanced multidisciplinary competencies',
                            'Vocational education NSQF levels 3–5',
                            'NCDG career planning CM4',
                            'Board exam preparation integrated',
                        ],
                        'enabled' => true,
                    ],
                ],
            ],
        ],

        /*
        |======================================================================
        | 5. Stream / Mountain / Sky rubric — Master Blueprint §3
        |======================================================================
        */
        'progression-rubric' => [
            'label' => 'Progression rubric',
            'tagline' => 'Stream, Mountain and Sky across Awareness, Sensitivity and Creativity.',
            'icon' => 'mountain',
            'source_document' => 'PAL_V4_Master_Blueprint.md §3',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Awarded levels'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Rubric usage'],
                [
                    'kind' => 'matrix',
                    'key' => 'rubric',
                    'title' => 'The rubric',
                    'help' => 'The descriptors the Assessor Agent scores against. Editing them changes how every HPC rating on this estate is awarded.',
                    'row_label' => 'Dimension',
                    'rows' => [
                        ['key' => 'awareness', 'label' => 'Awareness', 'sublabel' => 'Cognition'],
                        ['key' => 'sensitivity', 'label' => 'Sensitivity', 'sublabel' => 'Socio-emotional'],
                        ['key' => 'creativity', 'label' => 'Creativity', 'sublabel' => 'Innovation'],
                    ],
                    'columns' => [
                        ['key' => 'stream', 'label' => 'Stream'],
                        ['key' => 'mountain', 'label' => 'Mountain'],
                        ['key' => 'sky', 'label' => 'Sky'],
                    ],
                ],
                [
                    'kind' => 'records',
                    'key' => 'triggers',
                    'title' => 'Agent scoring triggers',
                    'help' => 'Observable evidence that raises a learner to a level. The Assessor Agent may only award a level whose trigger it can point at.',
                    'columns' => [
                        ['key' => 'level', 'label' => 'Level', 'type' => 'select', 'editable' => false, 'options' => ['stream', 'mountain', 'sky'], 'width' => 'narrow'],
                        ['key' => 'signal', 'label' => 'Observable signal', 'type' => 'text', 'editable' => true, 'width' => 'wide'],
                        ['key' => 'enabled', 'label' => 'Active', 'type' => 'toggle', 'editable' => true, 'width' => 'narrow'],
                    ],
                ],
            ],
            'settings' => [
                'rubric' => [
                    'awareness' => [
                        'stream' => 'Follows 2–3 step instructions; recounts basic details; recognises shapes and patterns.',
                        'mountain' => 'Follows 4–5 step tasks; applies concepts to simple cases; identifies cause and effect; gives instructions to peers.',
                        'sky' => 'Handles conditional logic; summarises complex tasks; uses precise terminology independently; formulates original strategies.',
                    ],
                    'sensitivity' => [
                        'stream' => 'Receptive to help; enjoys familiar tasks; gives general reactions such as "I like it".',
                        'mountain' => 'Explains reasons for own reactions; enjoys collaboration; adapts peer approaches; considers others\' perspectives.',
                        'sky' => 'Leads peers; revises own ideas on feedback; manages conflict with empathy; advocates for peers.',
                    ],
                    'creativity' => [
                        'stream' => 'Follows predetermined steps; combines given elements into new shapes.',
                        'mountain' => 'Devises novel approaches to familiar tasks; uses tools in more than one way.',
                        'sky' => 'Implements original solutions; self-determines rules; sustains extended creative work; creates from scratch.',
                    ],
                ],
                'triggers' => [
                    ['key' => 'stream_steps', 'level' => 'stream', 'signal' => 'Completes tasks of three steps or fewer correctly', 'enabled' => true],
                    ['key' => 'stream_language', 'level' => 'stream', 'signal' => 'Responds in general language without reasons ("it was easy")', 'enabled' => true],
                    ['key' => 'stream_prompting', 'level' => 'stream', 'signal' => 'Cannot proceed past step two without prompting', 'enabled' => true],
                    ['key' => 'stream_guessing', 'level' => 'stream', 'signal' => 'Guessing pattern — high response speed with high error fluency', 'enabled' => true],
                    ['key' => 'mountain_independent', 'level' => 'mountain', 'signal' => 'Completes a 4–5 step task independently', 'enabled' => true],
                    ['key' => 'mountain_reason', 'level' => 'mountain', 'signal' => 'Gives a reason for a choice ("because the LCM of 3 and 4 is 12")', 'enabled' => true],
                    ['key' => 'mountain_peer', 'level' => 'mountain', 'signal' => 'Can instruct a peer through a familiar task', 'enabled' => true],
                    ['key' => 'mountain_vocabulary', 'level' => 'mountain', 'signal' => 'Uses subject vocabulary, not yet with full precision', 'enabled' => true],
                    ['key' => 'sky_branching', 'level' => 'sky', 'signal' => 'Handles branching if/then decisions', 'enabled' => true],
                    ['key' => 'sky_terminology', 'level' => 'sky', 'signal' => 'Uses technical terminology precisely and unprompted', 'enabled' => true],
                    ['key' => 'sky_explains_peer', 'level' => 'sky', 'signal' => 'Explains a peer\'s different approach', 'enabled' => true],
                    ['key' => 'sky_original', 'level' => 'sky', 'signal' => 'Produces an original solution the system did not prompt', 'enabled' => true],
                    ['key' => 'sky_revises', 'level' => 'sky', 'signal' => 'Revises an earlier answer in light of new information', 'enabled' => true],
                ],
            ],
        ],

        /*
        |======================================================================
        | 6. Neo4j knowledge graph — Master Blueprint Appendix A
        |======================================================================
        */
        'knowledge-graph' => [
            'label' => 'Knowledge graph',
            'tagline' => 'The competency DAG — node labels, relationships and connection health.',
            'icon' => 'graph',
            'source_document' => 'PAL_V4_Master_Blueprint.md Appendix A',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Projected prerequisite graph'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Connection'],
                [
                    'kind' => 'params',
                    'key' => 'policy',
                    'title' => 'Graph policy',
                    'fields' => [
                        ['key' => 'enabled', 'label' => 'Use the graph for traversal', 'type' => 'toggle', 'help' => 'Off means prerequisite checks fall back to the relational concept tables.'],
                        ['key' => 'sync_ulu', 'label' => 'Mirror learning units into the graph', 'type' => 'toggle', 'help' => 'Writes a :LearningUnit node and its framework edges whenever a ULU is approved.'],
                        ['key' => 'sync_concepts', 'label' => 'Mirror concepts and prerequisites', 'type' => 'toggle', 'help' => 'Projects :Concept nodes and [:REQUIRES] edges from the relational concept tables.'],
                        ['key' => 'fail_open', 'label' => 'Continue when the graph is unreachable', 'type' => 'toggle', 'help' => 'On: a graph outage degrades traversal rather than failing the learner\'s session.'],
                    ],
                ],
                [
                    'kind' => 'catalog',
                    'key' => 'schema',
                    'title' => 'Node and relationship reference',
                    'help' => 'The labels the blueprint defines. A label marked present has at least one node in the connected database.',
                ],
            ],
            'settings' => [
                'policy' => [
                    'enabled' => false,
                    'sync_ulu' => true,
                    'sync_concepts' => false,
                    'fail_open' => true,
                ],
                'schema' => [
                    'nodes' => [
                        ['label' => 'Subject', 'note' => 'Top of the content hierarchy'],
                        ['label' => 'Chapter', 'note' => 'Belongs to a subject'],
                        ['label' => 'Concept', 'note' => 'The mastery unit; carries the BKT gate'],
                        ['label' => 'SubConcept', 'note' => 'Assessable slice of a concept'],
                        ['label' => 'ConceptContent', 'note' => 'A format variant of a concept'],
                        ['label' => 'Misconception', 'note' => 'An error pattern with corrective pathways'],
                        ['label' => 'Question', 'note' => 'IRT-calibrated assessment item'],
                        ['label' => 'Learner', 'note' => 'Carries the 9-dimension profile'],
                        ['label' => 'LearnerMastery', 'note' => 'One per learner per concept'],
                        ['label' => 'LearningPath', 'note' => 'Generated Class A/B/C sequence'],
                        ['label' => 'LearningCell', 'note' => 'A step within a path'],
                        ['label' => 'LearningUnit', 'note' => 'Unified Learning Unit (ULU)'],
                        ['label' => 'Stage', 'note' => 'NEP 5+3+3+4 stage gate'],
                        ['label' => 'CurricularGoal', 'note' => 'NCERT curricular goal'],
                        ['label' => 'Competency', 'note' => 'Maps a curricular goal onto concepts'],
                        ['label' => 'VocationalStream', 'note' => 'NEP vocational pathway'],
                        ['label' => 'NSQFUnit', 'note' => 'Vocational competency level'],
                        ['label' => 'CASELCompetency', 'note' => 'Socio-emotional evidence target'],
                        ['label' => 'NGSSPractice', 'note' => 'Science practice evidence target'],
                        ['label' => 'NCDGGoal', 'note' => 'Career development evidence target'],
                        ['label' => 'CareerCluster', 'note' => 'RIASEC-derived career signal'],
                    ],
                    'relationships' => [
                        ['type' => 'REQUIRES', 'from' => 'Concept', 'to' => 'Concept', 'note' => 'Prerequisite chain — the DAG that gates progression'],
                        ['type' => 'HAS_MISCONCEPTION', 'from' => 'Concept', 'to' => 'Misconception', 'note' => 'Known error patterns for a concept'],
                        ['type' => 'CORRECTS_WITH', 'from' => 'Misconception', 'to' => 'ConceptContent', 'note' => 'The corrective pathway'],
                        ['type' => 'HAS_MASTERY', 'from' => 'Learner', 'to' => 'LearnerMastery', 'note' => 'Per-concept BKT state'],
                        ['type' => 'HAS_PATH', 'from' => 'Learner', 'to' => 'LearningPath', 'note' => 'Active personalised path'],
                        ['type' => 'CONTAINS_STEP', 'from' => 'LearningPath', 'to' => 'LearningCell', 'note' => 'Ordered path steps'],
                        ['type' => 'USES_CONTENT', 'from' => 'LearningCell', 'to' => 'ConceptContent', 'note' => 'What the step serves'],
                        ['type' => 'BELONGS_TO', 'from' => 'Question', 'to' => 'SubConcept', 'note' => 'Assessment coverage'],
                        ['type' => 'TRIGGERS_MISCONCEPTION', 'from' => 'Question', 'to' => 'Misconception', 'note' => 'A distractor that diagnoses an error'],
                        ['type' => 'TEACHES', 'from' => 'LearningUnit', 'to' => 'Concept', 'note' => 'The ULU\'s academic core'],
                        ['type' => 'DEVELOPS', 'from' => 'LearningUnit', 'to' => 'CASELCompetency', 'note' => 'Socio-emotional layer'],
                        ['type' => 'EXERCISES', 'from' => 'LearningUnit', 'to' => 'NGSSPractice', 'note' => 'Science practice layer'],
                        ['type' => 'EVIDENCES', 'from' => 'LearningUnit', 'to' => 'NCDGGoal', 'note' => 'Career development layer'],
                        ['type' => 'SIGNALS_CAREER', 'from' => 'LearningUnit', 'to' => 'CareerCluster', 'note' => 'RIASEC accumulation'],
                        ['type' => 'MAPS_TO_CONCEPT', 'from' => 'Competency', 'to' => 'Concept', 'note' => 'Curricular goal crosswalk'],
                    ],
                ],
            ],
        ],

        /*
        |======================================================================
        | 7. Agentic AI — Master Blueprint §5 Layer 8, Appendix B
        |======================================================================
        */
        'ai-agents' => [
            'label' => 'AI agents',
            'tagline' => 'Five personas, their autonomy levels and when they must escalate to a human.',
            'icon' => 'bot',
            'source_document' => 'PAL_V4_Master_Blueprint.md §5 Layer 8, Appendix B',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Agent readiness'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Provider'],
                [
                    'kind' => 'params',
                    'key' => 'provider',
                    'title' => 'Provider policy',
                    'fields' => [
                        ['key' => 'default_model', 'label' => 'Default model', 'type' => 'text', 'help' => 'Used by any agent that does not pin its own.'],
                        ['key' => 'max_tokens', 'label' => 'Max tokens per call', 'type' => 'number', 'min' => 256, 'max' => 8192],
                        ['key' => 'temperature', 'label' => 'Temperature', 'type' => 'number', 'min' => 0, 'max' => 1, 'step' => 0.1],
                        ['key' => 'safety_filter', 'label' => 'Safety filter on generated content', 'type' => 'toggle', 'help' => 'Blueprint §11.3 requires every AI-generated string to pass a filter before a minor sees it. Leave on.'],
                        ['key' => 'log_all_calls', 'label' => 'Audit-log every agent call', 'type' => 'toggle', 'help' => 'Required for the 10-year audit retention rule.'],
                    ],
                ],
                [
                    'kind' => 'records',
                    'key' => 'agents',
                    'title' => 'Agent registry',
                    'help' => 'Autonomy HIGH means the agent acts without asking; MEDIUM means it proposes and a teacher confirms. Escalation is the condition that always pulls a human in.',
                    'columns' => [
                        ['key' => 'name', 'label' => 'Agent', 'type' => 'text', 'editable' => false],
                        ['key' => 'role', 'label' => 'Primary role', 'type' => 'text', 'editable' => false, 'width' => 'wide'],
                        ['key' => 'autonomy', 'label' => 'Autonomy', 'type' => 'select', 'editable' => true, 'options' => ['high', 'medium', 'low'], 'width' => 'narrow'],
                        ['key' => 'escalation', 'label' => 'Escalates when', 'type' => 'text', 'editable' => true, 'width' => 'wide'],
                        ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'editable' => true],
                        ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'editable' => true, 'width' => 'narrow'],
                    ],
                ],
            ],
            'settings' => [
                'provider' => [
                    'default_model' => 'openai/gpt-4o',
                    'max_tokens' => 2048,
                    'temperature' => 0.4,
                    'safety_filter' => true,
                    'log_all_calls' => true,
                ],
                'agents' => [
                    [
                        'key' => 'assessor',
                        'name' => 'Assessor Agent',
                        'role' => 'Selects items by IRT, extracts performance markers, detects misconceptions, rates Stream/Mountain/Sky.',
                        'autonomy' => 'high',
                        'escalation' => 'Mastery stagnation beyond 5 sessions → Feedback Agent and teacher alert',
                        'model' => '',
                        'enabled' => true,
                    ],
                    [
                        'key' => 'feedback',
                        'name' => 'Feedback Agent',
                        'role' => 'Generates why-wrong narrative explanations, calculates delta to the next level, drafts teacher feedback.',
                        'autonomy' => 'high',
                        'escalation' => 'Negative fluency trend beyond 3 sessions → teacher and parent notification',
                        'model' => '',
                        'enabled' => true,
                    ],
                    [
                        'key' => 'path_navigator',
                        'name' => 'Path Navigator Agent',
                        'role' => 'Generates Class A/B/C paths, picks content format by learning style, triggers expanded tasks.',
                        'autonomy' => 'medium',
                        'escalation' => 'Majority problem above 60% of a class → teacher flag for content redesign',
                        'model' => '',
                        'enabled' => true,
                    ],
                    [
                        'key' => 'retention_monitor',
                        'name' => 'Retention Monitor Agent',
                        'role' => 'Schedules spaced reviews, monitors the forgetting curve, triggers re-teach cycles.',
                        'autonomy' => 'medium',
                        'escalation' => 'Mastery falls below 0.70 after 30 days → auto re-teach and teacher alert',
                        'model' => '',
                        'enabled' => true,
                    ],
                    [
                        'key' => 'tutor',
                        'name' => 'Conversational AI Tutor',
                        'role' => 'Natural-language student Q&A in Indian languages; detects frustration and adjusts explanation style.',
                        'autonomy' => 'high',
                        'escalation' => 'Any distress signal → school counsellor and teacher, immediately',
                        'model' => '',
                        'enabled' => false,
                    ],
                ],
            ],
        ],

        /*
        |======================================================================
        | 8. The 9-dimension student model — Master Blueprint §4
        |======================================================================
        */
        'student-model' => [
            'label' => 'Student model',
            'tagline' => 'The nine dimensions PAL tracks simultaneously, and where each one\'s evidence comes from.',
            'icon' => 'radar',
            'source_document' => 'PAL_V4_Master_Blueprint.md §4',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Computed dimensions'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Evidence coverage'],
                [
                    'kind' => 'params',
                    'key' => 'policy',
                    'title' => 'Inference policy',
                    'fields' => [
                        ['key' => 'min_events_for_inference', 'label' => 'Minimum events before a dimension is reported', 'type' => 'number', 'min' => 1, 'max' => 50, 'help' => 'Below this the dimension reads "insufficient evidence" rather than showing a misleading number.'],
                        ['key' => 'engagement_drop_alert_pct', 'label' => 'Engagement drop that alerts a teacher (%)', 'type' => 'number', 'min' => 5, 'max' => 80, 'help' => 'Blueprint alerts on a drop above 30% across 3 days.'],
                        ['key' => 'engagement_window_days', 'label' => 'Engagement comparison window (days)', 'type' => 'number', 'min' => 1, 'max' => 30],
                        ['key' => 'confidence_scale_max', 'label' => 'Self-rating scale maximum', 'type' => 'number', 'min' => 3, 'max' => 10, 'help' => 'Blueprint uses a 1–5 self-rating.'],
                    ],
                ],
                [
                    'kind' => 'records',
                    'key' => 'dimensions',
                    'title' => 'The nine dimensions',
                    'help' => 'Weight decides how strongly a dimension pulls on content selection. A dimension with no evidence source is inert until its write path exists.',
                    'columns' => [
                        ['key' => 'ordinal', 'label' => '#', 'type' => 'number', 'editable' => false, 'width' => 'narrow'],
                        ['key' => 'name', 'label' => 'Dimension', 'type' => 'text', 'editable' => false],
                        ['key' => 'measurement', 'label' => 'How it is measured', 'type' => 'text', 'editable' => false, 'width' => 'wide'],
                        ['key' => 'evidence_table', 'label' => 'Evidence from', 'type' => 'code', 'editable' => false],
                        ['key' => 'weight', 'label' => 'Weight', 'type' => 'number', 'editable' => true, 'min' => 0, 'max' => 1, 'step' => 0.05, 'width' => 'narrow'],
                        ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'editable' => true, 'width' => 'narrow'],
                    ],
                ],
            ],
            'settings' => [
                'policy' => [
                    'min_events_for_inference' => 5,
                    'engagement_drop_alert_pct' => 30,
                    'engagement_window_days' => 3,
                    'confidence_scale_max' => 5,
                ],
                'dimensions' => [
                    ['ordinal' => 1, 'key' => 'knowledge_mastery', 'name' => 'Knowledge mastery', 'measurement' => 'BKT mastery probability per concept, 0.0–1.0. The primary gate for progression.', 'evidence_table' => 'pal_competencies', 'weight' => 1.00, 'enabled' => true],
                    ['ordinal' => 2, 'key' => 'confidence', 'name' => 'Confidence', 'measurement' => 'Self-rating × inverse hesitation time. Low knowledge with high confidence is a misconception alert.', 'evidence_table' => 'pal_learner_states', 'weight' => 0.60, 'enabled' => true],
                    ['ordinal' => 3, 'key' => 'learning_speed', 'name' => 'Learning speed', 'measurement' => 'Concepts mastered per week, normalised by difficulty. Drives Class A/B/C routing.', 'evidence_table' => 'pal_competencies', 'weight' => 0.70, 'enabled' => true],
                    ['ordinal' => 4, 'key' => 'forgetting_curve', 'name' => 'Forgetting curve', 'measurement' => 'Ebbinghaus retention M(t) = R × e^(−t/S); sets the next review date.', 'evidence_table' => 'lms_concept_mastery_log', 'weight' => 0.80, 'enabled' => true],
                    ['ordinal' => 5, 'key' => 'engagement', 'name' => 'Engagement', 'measurement' => '(click rate × 0.3) + (session duration × 0.4) + (return frequency × 0.3).', 'evidence_table' => 'pal_learning_sessions', 'weight' => 0.65, 'enabled' => true],
                    ['ordinal' => 6, 'key' => 'error_patterns', 'name' => 'Error patterns', 'measurement' => 'Wrong answers matched against the misconception library; frequency counted per tag.', 'evidence_table' => 'pal_learner_misconceptions', 'weight' => 0.90, 'enabled' => true],
                    ['ordinal' => 7, 'key' => 'learning_style', 'name' => 'Learning style', 'measurement' => 'Dominant modality inferred from content-type engagement; biases format selection.', 'evidence_table' => 'pal_learner_preferences', 'weight' => 0.50, 'enabled' => true],
                    ['ordinal' => 8, 'key' => 'socio_emotional', 'name' => 'Socio-emotional', 'measurement' => 'Peer assessment plus teacher HPC sensitivity ratings, scored as CASEL competencies.', 'evidence_table' => 'pal_collaboration_activities', 'weight' => 0.55, 'enabled' => true],
                    ['ordinal' => 9, 'key' => 'context_profile', 'name' => 'Context profile', 'measurement' => 'Mother tongue, rural/urban, guardian education, device and bandwidth; selects language variant and example type.', 'evidence_table' => 'pal_learner_states', 'weight' => 0.45, 'enabled' => true],
                ],
            ],
        ],

        /*
        |======================================================================
        | 9. Career intelligence — Master Blueprint §10
        |======================================================================
        */
        'career-pathway' => [
            'label' => 'Career pathway',
            'tagline' => 'Six years of ordinary answers becoming a validated Grade 9 career profile.',
            'icon' => 'compass',
            'source_document' => 'PAL_V4_Master_Blueprint.md §10',
            'panels' => [
                ['kind' => 'live', 'key' => 'live', 'title' => 'Accumulated career signal'],
                ['kind' => 'metrics', 'key' => 'health', 'title' => 'Report readiness'],
                [
                    'kind' => 'params',
                    'key' => 'policy',
                    'title' => 'Report policy',
                    'fields' => [
                        ['key' => 'report_grade', 'label' => 'Grade the report is generated at', 'type' => 'number', 'min' => 6, 'max' => 12, 'help' => 'Blueprint generates at Grade 9 entry from data accumulated since Grade 4.'],
                        ['key' => 'signal_accumulation_from_grade', 'label' => 'Start accumulating signals from grade', 'type' => 'number', 'min' => 1, 'max' => 12],
                        ['key' => 'min_events_for_report', 'label' => 'Minimum learning events before a report is valid', 'type' => 'number', 'min' => 100, 'max' => 20000, 'help' => 'The sample worked example rests on 5,847 events. Below the floor the report is withheld rather than guessed.'],
                        ['key' => 'auto_generate', 'label' => 'Generate automatically when a learner qualifies', 'type' => 'toggle'],
                        ['key' => 'require_counsellor_release', 'label' => 'Counsellor must release before parents see it', 'type' => 'toggle', 'help' => 'A career recommendation is consequential; the blueprint keeps a human in the loop.'],
                        ['key' => 'employability_index_from_grade', 'label' => 'Employability readiness index from grade', 'type' => 'number', 'min' => 9, 'max' => 12],
                    ],
                ],
                [
                    'kind' => 'records',
                    'key' => 'signals',
                    'title' => 'Career signals accumulated',
                    'help' => 'Each signal is a tag already carried on content. Enabling one makes every answered question contribute evidence towards it — no extra assessment is asked of the learner.',
                    'columns' => [
                        ['key' => 'name', 'label' => 'Signal', 'type' => 'text', 'editable' => false],
                        ['key' => 'accumulates', 'label' => 'What accumulates', 'type' => 'text', 'editable' => false, 'width' => 'wide'],
                        ['key' => 'output', 'label' => 'Guidance output', 'type' => 'text', 'editable' => false, 'width' => 'wide'],
                        ['key' => 'vocabulary_source', 'label' => 'Vocabulary', 'type' => 'code', 'editable' => false],
                        ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'toggle', 'editable' => true, 'width' => 'narrow'],
                    ],
                ],
                [
                    'kind' => 'catalog',
                    'key' => 'vocabulary',
                    'title' => 'Career vocabularies in force',
                    'help' => 'Read live from config/pal_v4.php through FrameworkCatalogService — the same values the content tagger validates against.',
                ],
            ],
            'settings' => [
                'policy' => [
                    'report_grade' => 9,
                    'signal_accumulation_from_grade' => 4,
                    'min_events_for_report' => 1500,
                    'auto_generate' => false,
                    'require_counsellor_release' => true,
                    'employability_index_from_grade' => 11,
                ],
                'signals' => [
                    ['key' => 'riasec', 'name' => 'RIASEC code', 'accumulates' => 'Correct-fluency signals per Holland type over time', 'output' => 'Career personality profile — six-dimension radar', 'vocabulary_source' => 'pal_v4.riasec', 'enabled' => true],
                    ['key' => 'gardner', 'name' => 'Gardner intelligence', 'accumulates' => 'Which modality and intelligence type the content exercises', 'output' => 'Intelligence strengths map — eight dimensions', 'vocabulary_source' => 'pal_v4.gardner', 'enabled' => true],
                    ['key' => 'aptitude', 'name' => 'Aptitude domain', 'accumulates' => 'Verbal, numerical, spatial, mechanical and artistic performance', 'output' => 'Academic aptitude profile for the counsellor', 'vocabulary_source' => 'internal', 'enabled' => true],
                    ['key' => 'vocational', 'name' => 'NEP vocational stream', 'accumulates' => 'Which vocational domain the content aligns to', 'output' => 'Recommended vocational pathway', 'vocabulary_source' => 'internal', 'enabled' => false],
                    ['key' => 'nsqf', 'name' => 'NSQF level', 'accumulates' => 'Demonstrated competency level against the national framework', 'output' => 'Certification readiness indicator', 'vocabulary_source' => 'internal', 'enabled' => false],
                    ['key' => 'ncdg', 'name' => 'NCDG goal', 'accumulates' => 'Which career development goal the work evidences', 'output' => 'NCDG counsellor report — PS, EDL and CM domains', 'vocabulary_source' => 'pal_v4.ncdg', 'enabled' => true],
                    ['key' => 'soft_skills', 'name' => 'Soft skill signal', 'accumulates' => 'Perseverance, collaboration and curiosity read from engagement behaviour', 'output' => 'Employability disposition score', 'vocabulary_source' => 'internal', 'enabled' => true],
                ],
                'aptitude_domains' => ['verbal', 'numerical', 'logical', 'spatial', 'mechanical', 'artistic'],
            ],
        ],
    ],
];
