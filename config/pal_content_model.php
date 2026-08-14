<?php

/*
|--------------------------------------------------------------------------
| New PAL → Content Model — projection rules
|--------------------------------------------------------------------------
|
| The Content Model sub-module projects the PAL V4 Content Intelligence Layer
| (PAL_V4_Content_Intelligence_Layer.md) directly out of `semantic_intelligence`,
| the table the chapter-extraction pipeline writes.
|
| NOTHING in this file is content. Every value here is either
|   (a) a NORMALISATION rule — how a vocabulary the extractor emits maps onto the
|       closed vocabulary already registered in config/pal_content.php, or
|   (b) a THRESHOLD the projection is judged against.
|
| The content itself — concepts, definitions, misconceptions, questions,
| rubrics, pedagogy, applications — is read from the database on every request.
| No concept, question, misconception or example is ever written here.
|
| The closed vocabularies (bloom levels, the 6 Indian cultural contexts, the 9
| languages, formats, H5P types, quality statuses, frameworks) are NOT duplicated
| here: they live in config/pal_content.php and are read through PalVocabulary,
| so the Content Model and the existing Content Intelligence layer can never
| drift apart.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Source of truth
    |--------------------------------------------------------------------------
    |
    | The extraction pipeline shipped the table with two columns misspelt
    | (`full_intelegance_json`, `qulity_flag`) while the migration that created
    | it used the corrected spellings. Both are listed so the projection works
    | on either schema without a rename that would break the extractor.
    |
    */
    'source' => [
        'table' => 'semantic_intelligence',
        'blob_columns' => ['full_intelegance_json', 'full_intelligence_json'],
        'quality_columns' => ['qulity_flag', 'quality_flag'],
        'concept_count_columns' => ['total_concepts', 'total_topics'],

        // Per-concept slices the extractor denormalises out of the blob. Read
        // in preference to the blob because they are far cheaper to load.
        'slice_columns' => [
            'knowledge', 'ability', 'skill', 'competency', 'blooms_level', 'dok',
            'prerequisites', 'misconceptions', 'real_world_applications', 'pedagogy',
            'learning_objectives', 'learning_outcomes', 'assessment_blueprint',
            'assessment_rubrics',
        ],

        // chapter_id is the only join key that resolves on this estate — the
        // extractor's concept_id strings are its own namespace and do not
        // reference lms_concept.
        'chapter_join' => ['table' => 'chapter_master', 'key' => 'id', 'name_column' => 'chapter_name'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bloom aliases → the closed set in config/pal_content.php
    |--------------------------------------------------------------------------
    |
    | The extractor emits Bloom's original tier names ("Remember", "Analyse"),
    | PAL keys on its own slugs. Unknown values are NOT coerced — they are
    | reported as an unmapped tag so the gap is visible instead of silent.
    |
    */
    'bloom_aliases' => [
        'remember' => 'recall',
        'recall' => 'recall',
        'knowledge' => 'recall',
        'understand' => 'understand',
        'understanding' => 'understand',
        'comprehend' => 'understand',
        'comprehension' => 'understand',
        'apply' => 'apply',
        'application' => 'apply',
        'applying' => 'apply',
        'analyze' => 'analyze',
        'analyse' => 'analyze',
        'analysis' => 'analyze',
        'analyzing' => 'analyze',
        'evaluate' => 'evaluate',
        'evaluation' => 'evaluate',
        'evaluating' => 'evaluate',
        'create' => 'create',
        'creating' => 'create',
        'synthesis' => 'create',
        'synthesise' => 'create',
    ],

    /*
    |--------------------------------------------------------------------------
    | Difficulty words → the 1-5 integer scale
    |--------------------------------------------------------------------------
    */
    'difficulty_aliases' => [
        'very easy' => 1,
        'easy' => 2,
        'basic' => 2,
        'low' => 2,
        'medium' => 3,
        'moderate' => 3,
        'average' => 3,
        'hard' => 4,
        'difficult' => 4,
        'high' => 4,
        'very hard' => 5,
        'challenging' => 5,
        'advanced' => 5,
    ],

    // concept.importance → :Concept.priority_score (1-10, spec §6.1).
    'importance_priority' => [
        'core' => 9,
        'critical' => 10,
        'high' => 8,
        'supporting' => 6,
        'medium' => 5,
        'enrichment' => 3,
        'low' => 3,
        'optional' => 2,
    ],

    // DOK (Webb) is orthogonal to Bloom and the extractor emits it separately.
    // Kept as its own axis; used for cognitive-load and latency derivation.
    'dok_levels' => [
        1 => 'Recall and Reproduction',
        2 => 'Skills and Concepts',
        3 => 'Strategic Thinking',
        4 => 'Extended Thinking',
    ],

    /*
    |--------------------------------------------------------------------------
    | knowledge_type: extractor's knowledge_type → the 4 PAL types
    |--------------------------------------------------------------------------
    */
    'knowledge_type_aliases' => [
        'fact' => 'factual',
        'factual' => 'factual',
        'definition' => 'conceptual',
        'concept' => 'conceptual',
        'conceptual' => 'conceptual',
        'principle' => 'conceptual',
        'law' => 'conceptual',
        'procedure' => 'procedural',
        'procedural' => 'procedural',
        'process' => 'procedural',
        'method' => 'procedural',
        'strategy' => 'metacognitive',
        'metacognitive' => 'metacognitive',
        'reflection' => 'metacognitive',
    ],

    /*
    |--------------------------------------------------------------------------
    | Type 1 — the 4-variant Concept Learning ladder (spec §2.1)
    |--------------------------------------------------------------------------
    |
    | Each variant slot names the semantic sections that can BACK it. A variant
    | whose sources are all empty for a concept is reported as a GAP with the
    | reason, never invented — the LLM authoring endpoint is the only thing that
    | fills a gap, and only when a human asks it to.
    |
    */
    'variant_blueprint' => [
        1 => [
            'format' => 'text_diagram',
            'label' => 'Text + diagram',
            'serves_bloom' => 'understand',
            'when_served' => 'First delivery',
            'sources' => ['definition', 'evidence', 'knowledge'],
            'corrective_format' => 'visual',
            'h5p_type' => 'course_presentation',
        ],
        2 => [
            'format' => 'video',
            'label' => 'Video + interactive pauses',
            'serves_bloom' => 'understand',
            'when_served' => 'Variant 1 failed (2 wrong at L1)',
            'sources' => ['pedagogy', 'abilities'],
            'corrective_format' => 'visual',
            'h5p_type' => 'interactive_video',
        ],
        3 => [
            'format' => 'story_audio',
            'label' => 'Analogy / story / audio',
            'serves_bloom' => 'understand',
            'when_served' => 'Variant 2 failed, or auditory learner',
            'sources' => ['real_world_applications'],
            'corrective_format' => 'story',
            'h5p_type' => 'branching_scenario',
        ],
        4 => [
            'format' => 'simulation',
            'label' => 'Simulation / manipulative',
            'serves_bloom' => 'apply',
            'when_served' => 'Optional; discovery-first learners',
            'sources' => ['pedagogy_practical', 'skills'],
            'corrective_format' => 'simulation',
            'h5p_type' => 'documentation_tool',
        ],
    ],

    // A pedagogy strategy is treated as practical/hands-on (and so as a
    // Variant 4 source) when its name matches one of these stems.
    'practical_pedagogy_stems' => [
        'experiential', 'activity', 'hands', 'practical', 'inquiry',
        'experiment', 'demonstration', 'project', 'game', 'simulation',
    ],

    // A pedagogy strategy is treated as visual/media-led (a Variant 2 source).
    'visual_pedagogy_stems' => [
        'visual', 'video', 'demonstration', 'model', 'diagram',
        'animation', 'multimedia', 'observation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Type 4 — assessment_type → delivery format + H5P recommendation
    |--------------------------------------------------------------------------
    |
    | h5p_type values must exist in config/pal_content.php `h5p_types`;
    | PalVocabulary rejects anything else on write.
    |
    */
    'assessment_type_map' => [
        'mcq' => ['format' => 'mcq', 'h5p_type' => 'multiple_choice', 'guessing' => 'medium'],
        'multiple choice' => ['format' => 'mcq', 'h5p_type' => 'multiple_choice', 'guessing' => 'medium'],
        'assertion-reason' => ['format' => 'mcq', 'h5p_type' => 'multiple_choice', 'guessing' => 'medium'],
        'assertion reason' => ['format' => 'mcq', 'h5p_type' => 'multiple_choice', 'guessing' => 'medium'],
        'true/false' => ['format' => 'mcq', 'h5p_type' => 'multiple_choice', 'guessing' => 'high'],
        'true or false' => ['format' => 'mcq', 'h5p_type' => 'multiple_choice', 'guessing' => 'high'],
        'fill in the blanks' => ['format' => 'fill_blank', 'h5p_type' => 'fill_in_the_blanks', 'guessing' => 'low'],
        'fill in the blank' => ['format' => 'fill_blank', 'h5p_type' => 'fill_in_the_blanks', 'guessing' => 'low'],
        'match the following' => ['format' => 'match', 'h5p_type' => 'drag_and_drop', 'guessing' => 'low'],
        'matching' => ['format' => 'match', 'h5p_type' => 'drag_and_drop', 'guessing' => 'low'],
        'short answer' => ['format' => 'short_answer', 'h5p_type' => 'fill_in_the_blanks', 'guessing' => 'low'],
        'very short answer' => ['format' => 'short_answer', 'h5p_type' => 'fill_in_the_blanks', 'guessing' => 'low'],
        'long answer' => ['format' => 'essay', 'h5p_type' => 'essay', 'guessing' => 'low'],
        'essay' => ['format' => 'essay', 'h5p_type' => 'essay', 'guessing' => 'low'],
        'case study' => ['format' => 'scenario', 'h5p_type' => 'branching_scenario', 'guessing' => 'low'],
        'case based' => ['format' => 'scenario', 'h5p_type' => 'branching_scenario', 'guessing' => 'low'],
        'source based' => ['format' => 'scenario', 'h5p_type' => 'branching_scenario', 'guessing' => 'low'],
        'diagram' => ['format' => 'diagram', 'h5p_type' => 'image_hotspot', 'guessing' => 'low'],
        'label the diagram' => ['format' => 'diagram', 'h5p_type' => 'image_hotspot', 'guessing' => 'low'],
        'numerical' => ['format' => 'numerical', 'h5p_type' => 'fill_in_the_blanks', 'guessing' => 'low'],
        'practical' => ['format' => 'performance', 'h5p_type' => 'documentation_tool', 'guessing' => 'low'],
        'activity' => ['format' => 'performance', 'h5p_type' => 'documentation_tool', 'guessing' => 'low'],
        'project' => ['format' => 'performance', 'h5p_type' => 'documentation_tool', 'guessing' => 'low'],
        'oral' => ['format' => 'oral', 'h5p_type' => 'questionnaire', 'guessing' => 'low'],
        'viva' => ['format' => 'oral', 'h5p_type' => 'questionnaire', 'guessing' => 'low'],
    ],

    // Used when an assessment_type is not in the map above. `format` is left
    // null so the gap surfaces in the metadata-completeness report rather than
    // being papered over with a wrong value.
    'assessment_type_default' => ['format' => null, 'h5p_type' => null, 'guessing' => null],

    /*
    |--------------------------------------------------------------------------
    | Scaffolding by practice level (spec §3.1 "Scaffolding available")
    |--------------------------------------------------------------------------
    */
    'scaffold_by_level' => [
        1 => 'hint_available',
        2 => 'worked_example',
        3 => 'hint_sequence',
        4 => 'none',
        5 => 'rubric',
    ],

    'latency_by_level' => [
        1 => 'fast_recall',
        2 => 'fast_recall',
        3 => 'medium_application',
        4 => 'slow_analysis',
        5 => 'extended_creation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Duration + cognitive-load estimation
    |--------------------------------------------------------------------------
    |
    | Reading speed is the mid-point of the 150-250 wpm range typically used for
    | Grade 6-10 readers; the per-level floor stops a one-line item from being
    | costed at zero minutes.
    |
    */
    'estimation' => [
        'words_per_minute' => 180,
        'min_minutes_by_content_type' => ['concept' => 3, 'practice' => 1, 'corrective' => 2, 'assessment' => 1],
        'seconds_per_mark' => 90,
        // Intrinsic load rises with DOK; extraneous load rises when the item
        // depends on a visual the estate may not have.
        'intrinsic_by_dok' => [1 => 1, 2 => 2, 3 => 4, 4 => 5],
        'germane_by_practice_level' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
    ],

    // Words that mark an item as depending on a picture/diagram to be answerable.
    'visual_dependency_stems' => [
        'diagram', 'figure', 'graph', 'image', 'picture', 'map', 'chart',
        'given below', 'shown below', 'observe the', 'label the',
    ],

    /*
    |--------------------------------------------------------------------------
    | Indian cultural context — deterministic pre-pass (spec §2.3)
    |--------------------------------------------------------------------------
    |
    | The 6 contexts themselves are the closed set in config/pal_content.php.
    | This lexicon is only a CHEAP FIRST PASS over the extractor's real-world
    | application text; when no context scores above `min_score` the classifier
    | escalates to the LLM rather than guessing. Nothing here invents an example
    | — it only decides which bucket an already-extracted example falls in.
    |
    */
    'cultural_lexicon' => [
        'urban_market' => ['market', 'shop', 'mall', 'city', 'traffic', 'bus', 'metro', 'price', 'shopkeeper', 'bill', 'urban', 'factory', 'industry'],
        'agriculture_farm' => ['farm', 'crop', 'soil', 'harvest', 'irrigation', 'plough', 'fertiliser', 'fertilizer', 'seed', 'agriculture', 'tractor', 'field', 'manure'],
        'sports_cricket' => ['cricket', 'bat', 'ball', 'run', 'wicket', 'match', 'kabaddi', 'hockey', 'football', 'athlete', 'player', 'sport', 'stadium'],
        'rural_village' => ['village', 'well', 'hut', 'panchayat', 'bullock', 'cattle', 'rural', 'handpump', 'chulha', 'firewood'],
        'festival_cultural' => ['festival', 'diwali', 'holi', 'pongal', 'onam', 'rangoli', 'temple', 'procession', 'wedding', 'sweets', 'lamp', 'decorat'],
        'coastal_fishing' => ['sea', 'ocean', 'fish', 'boat', 'coast', 'tide', 'salt', 'harbour', 'harbor', 'net', 'shore', 'marine'],
    ],

    'cultural_classifier' => [
        // A bucket must win by at least this raw score to be accepted without LLM help.
        'min_score' => 2,
        // Two buckets within this margin of each other = genuinely mixed.
        'tie_margin' => 1,
        'fallback' => 'mixed',
        'unmatched' => 'none',
    ],

    /*
    |--------------------------------------------------------------------------
    | Framework signals the extraction does not carry (spec §5.1 standards block)
    |--------------------------------------------------------------------------
    |
    | RIASEC / Gardner / NGSS / CASEL / NCDG / career cluster are NOT emitted by
    | the extractor and are not derivable from its text by rule. They are left
    | NULL by the projection and filled only by the LLM enrichment endpoint,
    | which writes `tagged_by = 'ai'` + `quality_status = 'draft'` so a human
    | still has to approve them (CONTENT LAW C5).
    |
    */
    'llm_only_fields' => [
        'riasec_signal', 'gardner_intelligence', 'ngss_practice', 'casel_domain',
        'ncdg_goal', 'career_cluster_signal', 'aptitude_domain', 'p21_skill',
        'soft_skill_signal', 'hpc_lens_primary', 'gender_representation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mandatory metadata before a node may be approved (spec Appendix)
    |--------------------------------------------------------------------------
    */
    'mandatory_fields' => [
        'concept' => ['concept_key', 'bloom_level', 'difficulty_1_to_5', 'language', 'grade', 'quality_status', 'cultural_context', 'format'],
        'practice' => ['concept_key', 'bloom_level', 'practice_level', 'difficulty_1_to_5', 'language', 'grade', 'quality_status', 'cultural_context'],
        'corrective' => ['concept_key', 'language', 'grade', 'quality_status', 'corrective_format'],
        'assessment' => ['concept_key', 'bloom_level', 'difficulty_1_to_5', 'language', 'grade', 'quality_status', 'format'],
    ],

    // Every field the metadata schema can carry, in the order the authoring UI
    // renders them. Drives both the completeness score and the form layout, so
    // the two can never disagree.
    'metadata_field_groups' => [
        'identity' => ['node_key', 'content_id_ref', 'concept_key', 'concept_name', 'sub_concept_ref', 'content_type', 'variant_number', 'practice_level'],
        'curriculum' => ['subject', 'grade', 'grade_band', 'stage', 'board', 'chapter', 'chapter_number'],
        'cognitive' => ['bloom_level', 'dok_level', 'knowledge_type', 'difficulty_1_to_5', 'cognitive_load_intrinsic', 'cognitive_load_extraneous', 'cognitive_load_germane'],
        'delivery' => ['format', 'h5p_type', 'pedagogy_tag', 'pedagogy_secondary', 'scaffold_type', 'response_latency_band', 'estimated_duration_minutes', 'visual_dependency', 'offline_compatible'],
        'language_culture' => ['language', 'language_variants_available', 'cultural_context', 'gender_representation', 'reading_level_fk'],
        'standards' => ['skills', 'competencies', 'casel_domain', 'ngss_practice', 'ncdg_goal', 'riasec_signal', 'gardner_intelligence', 'aptitude_domain', 'p21_skill', 'hpc_lens_primary'],
        'misconception' => ['misconception_tags', 'distractor_rationale', 'common_errors'],
        'psychometric' => ['marks', 'avg_time_seconds', 'guessing_vulnerability', 'evidence_verified'],
        'career' => ['career_cluster_signal', 'soft_skill_signal', 'nep_vocational_stream', 'nsqf_level'],
        'quality' => ['quality_status', 'tagged_by', 'confidence', 'version', 'reviewed_by', 'reviewed_at', 'content_flag', 'sensitivity_flag'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bloom ladder servability (spec §3.2)
    |--------------------------------------------------------------------------
    |
    | A gate needs `min_items` attempts to be measurable at all, so a rung with
    | fewer approved items than this cannot gate and is reported unservable.
    |
    */
    'ladder' => [
        'min_items_per_level' => 5,
        // A concept with no item at a rung is a hard coverage gap; with some but
        // fewer than min_items it is a soft gap.
        'report_soft_gaps' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM processing — used only where the database cannot answer
    |--------------------------------------------------------------------------
    |
    | Reuses the same provider + key-resolution chain as QuestionGenerationService
    | (config/deepseek.php → ai_api_keys → env), so the Content Model does not
    | introduce a second AI configuration surface.
    |
    */
    'llm' => [
        'enabled' => env('PAL_CONTENT_MODEL_LLM', true),
        'model' => env('PAL_CONTENT_MODEL_LLM_MODEL', null),   // null = the provider's own model

        /*
         | Provider resolution chain, tried in order. The first entry whose key
         | resolves wins, so a deployment that has only an OpenRouter key still
         | gets AI enrichment instead of a permanently disabled button.
         |
         | `api_type` is looked up in the `ai_api_keys` table (status = 1);
         | `env_key` is the environment fallback. Keys are NEVER written here.
         */
        'providers' => [
            [
                'name' => 'deepseek',
                'api_type' => env('DEEPSEEK_API_TYPE', 'DEEPSEEK_API_KEY'),
                'env_key' => 'DEEPSEEK_API_KEY',
                'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
                'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-pro'),
            ],
            [
                'name' => 'openrouter',
                'api_type' => 'OPENROUTER_API_KEY',
                // The estate's OPENAI_API_KEY value is an OpenRouter key
                // (sk-or-…), which is why it is listed against this provider
                // rather than against api.openai.com.
                'env_key' => 'OPENROUTER_API_KEY',
                'env_key_alt' => 'OPENAI_API_KEY',
                'env_key_prefix' => 'sk-or-',
                'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
                'model' => env('OPENROUTER_MODEL', 'deepseek/deepseek-chat'),
            ],
        ],

        'temperature' => 0.2,
        'timeout_seconds' => (int) env('PAL_CONTENT_MODEL_LLM_TIMEOUT', 180),
        'max_output_tokens' => 0,
        // Enrichment answers are cached per (node, kind, input fingerprint) so a
        // repeated page load never re-bills the provider.
        'cache_days' => 30,
        'max_items_per_call' => 12,
        // A machine may only ever propose. Repeated from config/pal_content.php
        // so the constraint is visible where the calls are configured.
        'forced_status' => 'draft',
        'forced_tagged_by' => 'ai',
    ],

    /*
    |--------------------------------------------------------------------------
    | Node key namespace
    |--------------------------------------------------------------------------
    |
    | Node keys are DERIVED, not stored: {prefix}.{semantic_intelligence.id}.
    | {concept-slug}.{discriminator}. They stay stable across re-projection as
    | long as the concept keeps its name, which is what the overlay and the
    | revision history are keyed on.
    |
    */
    'node_prefixes' => [
        'concept' => 'CL',
        'practice' => 'PR',
        'corrective' => 'CR',
        'assessment' => 'AS',
        'misconception' => 'MC',
    ],

    'concept_slug_max_length' => 48,
];
