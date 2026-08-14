<?php

/*
|--------------------------------------------------------------------------
| PAL V4 — Content Intelligence Layer vocabularies
|--------------------------------------------------------------------------
|
| Source spec: PAL_V4_Content_Intelligence_Layer.md (v4.0, March 2026).
| Plan: docs/lms-pal-content-intelligence-master-prompt.md (CONTENT LAW in §3).
|
| Every list below is a CLOSED set. An unregistered value is a write failure,
| not a new category (plan §4). This file is the single registry — services,
| commands, migrations and the API all validate against it via
| App\Services\PAL\Content\PalVocabulary.
|
| Nothing here is tenant-specific. Tenancy lives on the rows, not the vocabulary.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Bloom's taxonomy — spec §3.1
    |--------------------------------------------------------------------------
    |
    | `mapping_type_id` bridges to the vocabulary that ALREADY exists in
    | lms_mapping_type (children of parent 82 "Blooms Taxonomy"). Verified
    | 2026-08-13 on vivek_erp: 83 Creating, 84 Evaluate, 85 Analyse, 86 Apply,
    | 87 Understand, 88 Remember. We reuse those ids rather than minting a
    | parallel list, so anything already tagged through content_mapping_type
    | (15 rows today) reconciles instead of colliding.
    |
    | `practice_level` is the 5-level ladder. Note the spec collapses
    | evaluate+create into L5, so the ladder has 5 rungs over 6 Bloom levels.
    |
    */
    'bloom_levels' => [
        'recall'     => ['ordinal' => 1, 'practice_level' => 1, 'mapping_type_id' => 88, 'label' => 'Remember',  'tier' => 'Remember'],
        'understand' => ['ordinal' => 2, 'practice_level' => 2, 'mapping_type_id' => 87, 'label' => 'Understand', 'tier' => 'Comprehend'],
        'apply'      => ['ordinal' => 3, 'practice_level' => 3, 'mapping_type_id' => 86, 'label' => 'Apply',      'tier' => 'Apply'],
        'analyze'    => ['ordinal' => 4, 'practice_level' => 4, 'mapping_type_id' => 85, 'label' => 'Analyse',    'tier' => 'Analyse'],
        'evaluate'   => ['ordinal' => 5, 'practice_level' => 5, 'mapping_type_id' => 84, 'label' => 'Evaluate',   'tier' => 'Evaluate'],
        'create'     => ['ordinal' => 6, 'practice_level' => 5, 'mapping_type_id' => 83, 'label' => 'Creating',   'tier' => 'Create'],
    ],

    // Bloom parent node in lms_mapping_type. Used to read existing tags back.
    'bloom_mapping_parent_id' => 82,

    /*
    |--------------------------------------------------------------------------
    | Practice ladder — spec §3.1 and §3.2
    |--------------------------------------------------------------------------
    |
    | `gate` is what must be true to LEAVE this level for the next one.
    |   metric: net_fluency  → needs `min_items` attempts at this level
    |   metric: bkt_mastery  → concept-level mastery, item count irrelevant
    |
    */
    'practice_levels' => [
        1 => [
            'bloom_level' => 'recall',
            'name' => 'Recall',
            'tasks' => ['identify', 'name', 'recognise'],
            'scaffold' => 'hint',
            'h5p' => ['multiple_choice', 'flash_cards', 'memory_game'],
            'gate' => ['metric' => 'net_fluency', 'threshold' => 0.60, 'min_items' => 5],
        ],
        2 => [
            'bloom_level' => 'understand',
            'name' => 'Understanding',
            'tasks' => ['explain', 'classify', 'describe'],
            'scaffold' => 'worked_example',
            'h5p' => ['fill_in_the_blanks', 'drag_and_drop'],
            'gate' => ['metric' => 'net_fluency', 'threshold' => 0.55, 'min_items' => 5],
        ],
        3 => [
            'bloom_level' => 'apply',
            'name' => 'Application',
            'tasks' => ['solve', 'calculate', 'use'],
            'scaffold' => 'hint_sequence',
            'h5p' => ['fill_in_the_blanks', 'mark_the_words'],
            'gate' => ['metric' => 'bkt_mastery', 'threshold' => 0.70],
        ],
        4 => [
            'bloom_level' => 'analyze',
            'name' => 'Analysis',
            'tasks' => ['compare', 'differentiate', 'examine'],
            'scaffold' => 'none',
            'h5p' => ['essay', 'course_presentation'],
            'gate' => ['metric' => 'bkt_mastery', 'threshold' => 0.85],
        ],
        5 => [
            'bloom_level' => 'create',
            'name' => 'Evaluate/Create',
            'tasks' => ['design', 'justify', 'invent'],
            'scaffold' => 'rubric',
            'h5p' => ['documentation_tool', 'branching_scenario'],
            // L5 is terminal — there is no gate out of it. Access is gated IN
            // by hpc_ceilings below plus an Assessor Agent confirmation.
            'gate' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regression rules — spec §3.2
    |--------------------------------------------------------------------------
    */
    'regression' => [
        // 3 consecutive wrong at Lx → serve misconception corrective → return to Lx
        'consecutive_wrong_to_misconception' => 3,
        // misconception unresolved after N corrective attempts → drop to Lx-1
        'corrective_attempts_before_demotion' => 2,
        // teacher alert when a learner falls from this level to this level
        'teacher_alert_on_drop_from' => 3,
        'teacher_alert_on_drop_to'   => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | HPC ceilings — spec §3.2 "Bloom's ceiling by HPC level"
    |--------------------------------------------------------------------------
    */
    'hpc_ceilings' => [
        'Stream'   => 3,
        'Mountain' => 4,
        'Sky'      => 5,
    ],

    'hpc_lenses' => ['Awareness', 'Sensitivity', 'Creativity'],

    /*
    |--------------------------------------------------------------------------
    | The 4-type content model — spec §1
    |--------------------------------------------------------------------------
    |
    | `graph_label` is recorded for the P7 Neo4j projection. NOTHING writes to
    | Neo4j today — the ERP graph migration is at Phase 0 freeze (CONTENT LAW C8).
    |
    */
    'content_types' => [
        'concept'    => ['label' => 'Concept Learning',     'graph_label' => 'ConceptContent',     'blooms' => ['recall', 'understand']],
        'practice'   => ['label' => 'Practice Content',     'graph_label' => 'ConceptContent',     'blooms' => ['apply', 'analyze', 'evaluate', 'create']],
        'corrective' => ['label' => 'Misconception Library','graph_label' => 'MiscorrectiveContent','blooms' => ['recall', 'understand', 'apply', 'analyze', 'evaluate', 'create']],
        'assessment' => ['label' => 'Assessment Bank',      'graph_label' => 'Question',           'blooms' => ['apply', 'analyze', 'evaluate']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery formats — spec §2.1 variant table
    |--------------------------------------------------------------------------
    |
    | `variant` is the default variant slot the format serves in the re-route
    | ladder. CONTENT LAW C7: after a failure the router must serve a DIFFERENT
    | format, so the router walks these slots rather than re-serving.
    |
    */
    'formats' => [
        'text_diagram' => ['variant' => 1, 'label' => 'Text + Diagram'],
        'video'        => ['variant' => 2, 'label' => 'Video + interactive pauses'],
        'story_audio'  => ['variant' => 3, 'label' => 'Analogy / story / audio narration'],
        'simulation'   => ['variant' => 4, 'label' => 'Simulation / virtual manipulative'],
        'h5p'          => ['variant' => 4, 'label' => 'H5P interactive'],
        'pdf'          => ['variant' => 1, 'label' => 'PDF / document'],
        'external'     => ['variant' => 1, 'label' => 'External link'],
    ],

    // Order the variant router walks when re-routing after a failure (spec §2.1).
    'variant_ladder' => [1, 2, 3, 4],

    'difficulty_range' => ['min' => 1, 'max' => 5],

    /*
    |--------------------------------------------------------------------------
    | QA pipeline — spec §7.1
    |--------------------------------------------------------------------------
    |
    | CONTENT LAW C4: quality_status gates DELIVERY, not authoring. Only
    | statuses listed in `servable` reach a learner.
    | CONTENT LAW C5: no batch job may write a status flagged `human_only`.
    |
    */
    'quality_statuses' => [
        'draft'             => ['stage' => 1, 'human_only' => false],
        'reviewed'          => ['stage' => 2, 'human_only' => true],
        'pedagogy_reviewed' => ['stage' => 3, 'human_only' => true],
        'piloted'           => ['stage' => 4, 'human_only' => false],
        'approved'          => ['stage' => 5, 'human_only' => true],
        'deprecated'        => ['stage' => 6, 'human_only' => true],
    ],

    'servable_statuses' => ['approved'],

    // Legal stage transitions. Anything else is rejected by ContentMetadataService.
    'quality_transitions' => [
        'draft'             => ['reviewed', 'deprecated'],
        'reviewed'          => ['pedagogy_reviewed', 'draft', 'deprecated'],
        'pedagogy_reviewed' => ['piloted', 'approved', 'reviewed', 'deprecated'],
        'piloted'           => ['approved', 'pedagogy_reviewed', 'deprecated'],
        'approved'          => ['deprecated', 'reviewed'],
        'deprecated'        => ['draft'],
    ],

    'tagged_by' => ['human', 'ai', 'imported', 'derived'],

    /*
    |--------------------------------------------------------------------------
    | Indian cultural context — spec §2.3
    |--------------------------------------------------------------------------
    */
    'cultural_contexts' => [
        'urban_market', 'agriculture_farm', 'sports_cricket',
        'rural_village', 'festival_cultural', 'coastal_fishing',
        'mixed', 'none',
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages — ISO 639-1, spec §2.2
    |--------------------------------------------------------------------------
    */
    'languages' => ['en', 'hi', 'gu', 'ta', 'te', 'mr', 'bn', 'kn', 'ml'],
    'default_language' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Misconception library — spec §4.3
    |--------------------------------------------------------------------------
    */
    'corrective_formats' => ['visual', 'story', 'simulation', 'audio'],

    'misconception' => [
        // Occurrences before a suspected misconception is treated as confirmed (spec §4.4 step 3).
        'confirm_after_occurrences' => 2,
        // Occurrences that raise a teacher alert.
        'teacher_alert_after_occurrences' => 3,
        // Share of a class with the same dominant error that makes it a majority
        // problem worth a whole-class intervention (spec §6.3).
        'majority_threshold' => 0.40,
        'priority_range' => ['min' => 1, 'max' => 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge type / scaffold / latency — spec §5.1
    |--------------------------------------------------------------------------
    */
    'knowledge_types' => ['factual', 'conceptual', 'procedural', 'metacognitive'],

    'scaffold_types' => ['none', 'hint_available', 'worked_example', 'hint_sequence', 'rubric', 'peer_review'],

    'response_latency_bands' => ['fast_recall', 'medium_application', 'slow_analysis', 'extended_creation'],

    'guessing_vulnerability' => ['low', 'medium', 'high'],

    'gender_representation' => ['balanced', 'male_skewed', 'female_skewed', 'neutral', 'unreviewed'],

    /*
    |--------------------------------------------------------------------------
    | Standards frameworks — spec §5.1 and §6.1
    |--------------------------------------------------------------------------
    */
    'casel_domains' => [
        'self_awareness', 'self_management', 'social_awareness',
        'relationship_skills', 'responsible_decision_making',
    ],

    'ngss_practices' => [
        'asking_questions', 'developing_models', 'planning_investigations',
        'analyzing_data', 'using_mathematics', 'constructing_explanations',
        'engaging_in_argument', 'obtaining_evaluating_communicating',
    ],

    'ncdg_goals' => ['PS1', 'PS2', 'PS3', 'ED1', 'EDL1', 'EDL2', 'CM1', 'CM2', 'CM3'],

    // RIASEC. mapping_type_id bridges to the existing lms_mapping_type rows
    // (children of parent 105 "Interests"), verified 2026-08-13.
    'riasec_signals' => [
        'R' => ['label' => 'Realistic',     'mapping_type_id' => 106],
        'I' => ['label' => 'Investigative', 'mapping_type_id' => 107],
        'A' => ['label' => 'Artistic',      'mapping_type_id' => 108],
        'S' => ['label' => 'Social',        'mapping_type_id' => 109],
        'E' => ['label' => 'Enterprising',  'mapping_type_id' => 110],
        'C' => ['label' => 'Conventional',  'mapping_type_id' => 111],
    ],

    'gardner_intelligences' => [
        'linguistic', 'logical_mathematical', 'spatial', 'bodily_kinesthetic',
        'musical', 'interpersonal', 'intrapersonal', 'naturalistic',
    ],

    'aptitude_domains' => ['verbal', 'numerical', 'spatial', 'mechanical', 'artistic'],

    'career_clusters' => [
        'stem', 'arts_av', 'business_finance', 'health_science', 'agriculture',
        'education', 'hospitality', 'manufacturing', 'it_ites', 'government',
    ],

    'soft_skill_signals' => ['perseverance', 'leadership', 'empathy', 'curiosity', 'collaboration'],

    'p21_skills' => ['critical_thinking', 'creativity', 'collaboration', 'communication'],

    'nep_vocational_streams' => [
        'agriculture', 'it_ites', 'healthcare', 'retail', 'beauty_wellness',
        'automotive', 'electronics', 'tourism_hospitality', 'apparel', 'plumbing',
    ],

    'nsqf_range' => ['min' => 1, 'max' => 6],

    /*
    |--------------------------------------------------------------------------
    | Assessment types — spec §5.2
    |--------------------------------------------------------------------------
    */
    'assessment_types' => [
        'diagnostic'  => ['blooms' => ['recall', 'understand', 'apply'],           'purpose' => 'Build initial BKT baseline; identify gaps'],
        'formative'   => ['blooms' => ['recall', 'understand', 'apply', 'analyze'],'purpose' => 'Real-time BKT update; misconception detection'],
        'competency'  => ['blooms' => ['apply', 'analyze', 'evaluate', 'create'],  'purpose' => 'HPC rubric rating'],
        'retention'   => ['blooms' => ['recall', 'understand', 'apply'],           'purpose' => 'Forgetting-curve check'],
        'sky'         => ['blooms' => ['analyze', 'evaluate', 'create'],           'purpose' => 'Confirm Sky-level HPC'],
        'vocational'  => ['blooms' => ['apply', 'analyze', 'evaluate', 'create'],  'purpose' => 'Demonstrated performance; portfolio'],
    ],

    // Retention probe schedule in days post-mastery (spec §5.2).
    'retention_probe_days' => [7, 30, 90],
    'retention_review_threshold' => 0.70,

    /*
    |--------------------------------------------------------------------------
    | Pedagogy — NOT invented here
    |--------------------------------------------------------------------------
    |
    | CONTENT LAW / plan §4: pedagogy_tag is read from the existing
    | lms_mapping_type estate, never from a parallel list. Verified 2026-08-13
    | on vivek_erp: 9 children under parent 73569 ("Types", element_id
    | content_library) are the live pedagogy vocabulary. The spec's prose says
    | "12 pedagogy types"; the data has 9. The DATA wins — `pedagogy_source`
    | below is authoritative and PalVocabulary reads it at runtime, so adding a
    | 10th row in lms_mapping_type is picked up without a code change.
    |
    */
    'pedagogy_source' => [
        'table' => 'lms_mapping_type',
        'parent_id' => 73569,
        'status' => 1,
    ],

    // Fallback slugs used only when lms_mapping_type is unreachable (e.g. unit
    // tests with no DB). Kept in sync with the 9 live rows.
    'pedagogy_fallback' => [
        'inquiry_based', 'experiential', 'flipped_classroom', 'art_integrated',
        'competency_based', 'problem_based', 'collaborative', 'integrated_teaching',
        'blended_learning',
    ],

    /*
    |--------------------------------------------------------------------------
    | H5P matrix — spec §8.1
    |--------------------------------------------------------------------------
    |
    | ASPIRATIONAL. Verified 2026-08-13: h5p_scenarios=11,
    | h5p_interactive_video=0, h5p_video_interactions=0. Plan §1.2.3 —
    | an H5P requirement must NEVER gate delivery. `h5p_type` is a
    | recommendation only; the router falls back to content_master file/url.
    |
    */
    'h5p_gates_delivery' => false,

    'h5p_types' => [
        'multiple_choice', 'fill_in_the_blanks', 'drag_and_drop', 'mark_the_words',
        'flash_cards', 'memory_game', 'dialog_cards', 'image_hotspot',
        'interactive_video', 'course_presentation', 'branching_scenario',
        'documentation_tool', 'essay', 'questionnaire', 'agamotto',
    ],

    /*
    |--------------------------------------------------------------------------
    | IRT — spec §5.1, derived from history not from a pilot
    |--------------------------------------------------------------------------
    |
    | lms_online_exam_answer holds 2,418,015 graded responses (verified
    | 2026-08-13), so irt_b / discrimination_index / first_attempt_correct_rate
    | are computable today. `min_responses` is the floor below which a derived
    | parameter is statistically meaningless and is left NULL instead.
    |
    */
    'irt' => [
        'min_responses' => 30,
        // Below this discrimination the item does not separate learners —
        // spec §7.1 stage 4 says REVISE before approving.
        'revise_below_discrimination' => 0.25,
        'approve_above_discrimination' => 0.30,
        // Upper/lower group fraction for the classical discrimination index.
        'group_fraction' => 0.27,
        'b_bounds' => ['min' => -4.0, 'max' => 4.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Live monitoring — spec §7.3
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'min_usage_for_review' => 100,
        'underperforming_mastery_below' => 0.50,
        'all_variants_failing_below' => 0.55,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI tagging batch — CONTENT LAW C5
    |--------------------------------------------------------------------------
    */
    'ai_tagging' => [
        'batch_size' => 500,
        // A batch may never write anything but this status. Enforced in code,
        // repeated here so the constraint is visible where it is configured.
        'forced_status' => 'draft',
        'forced_tagged_by' => 'ai',
        // Proposals below this confidence are stored but flagged for mandatory review.
        'low_confidence_below' => 0.60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy — CONTENT LAW C3
    |--------------------------------------------------------------------------
    |
    | Content-intelligence rows carry sub_institute_id explicitly (unlike the 27
    | pal_* tables, which have none and resolve tenancy through learner_id).
    | The single documented exception is shared curriculum vocabulary:
    | sub_institute_id = 0 AND scope = 'global'.
    |
    */
    'scopes' => ['tenant', 'global'],
    'global_sub_institute_id' => 0,
];
