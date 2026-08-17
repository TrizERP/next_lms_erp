<?php

/*
|--------------------------------------------------------------------------
| PAL V4 — H5P Model bootstrap seed
|--------------------------------------------------------------------------
|
| This file is NOT the runtime source of truth. It is the *seed* that the
| `pal_h5p_model_registry` migration writes into `pal_vocabulary`, and the
| offline fallback H5PModelRegistry uses when that table is unreachable
| (unit tests, a fresh schema, a migration that has not run yet).
|
| At runtime everything below is read back out of `pal_vocabulary` — so a
| school that adds a 22nd H5P type, retags a pedagogy, or rewires the
| pedagogy × framework coverage matrix does it in data, and both the API and
| the H5P Content UI pick it up without a code change.
|
| Source: PAL_V4_Pedagogy_Frameworks_H5P.md v4.0
|   §1.2  the 12 pedagogies              → domain `pedagogy_tags`
|   §1.3  pedagogy selection algorithm   → domain `pedagogy_selection_rules`
|   §2    CASEL / SEL                    → domain `casel_domains`
|   §3    NGSS / STEM                    → domain `ngss_practices`
|   §4    NCDG / career                  → domain `ncdg_goals`
|   §5    Music framework                → domain `music_domains`
|   §6    Sports framework               → domain `sports_domains`
|   §7    Banking & Finance pathway      → domain `finance_levels`
|   §8.1  H5P content type master ref    → domain `h5p_types`
|   §8.2  xAPI statement processing      → domain `xapi_verbs`
|   §9    pedagogy × framework matrix    → `pedagogy_tags[*].metadata.coverage`
|
| `metadata.implementation` on an H5P type binds the abstract type to the
| H5P tables this ERP actually has. `status: native` means there is a real
| table behind it, and it is what makes the H5P Content hub dynamic.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Registry identity
    |----------------------------------------------------------------------
    | Seeded rows are written as system, global-scope vocabulary so they are
    | shared by every tenant. A tenant may add its own rows alongside them
    | (is_system = 0, its own sub_institute_id) and those are merged on read.
    */
    'registry' => [
        'table' => 'pal_vocabulary',
        'scope' => 'global',
        'sub_institute_id' => 0,
        'cache_ttl_seconds' => (int) env('PAL_H5P_REGISTRY_TTL', 300),
    ],

    /*
    |----------------------------------------------------------------------
    | §8.1 — H5P content type master reference (21 types)
    |----------------------------------------------------------------------
    | fluency_trackable: yes | partial | no  (the doc's third column)
    | engagement_weight: authored multiplier applied to the engagement score
    |   a session on this type contributes. Every OTHER engagement figure
    |   (completion_rate, avg duration, avg engagement score, usage count) is
    |   COMPUTED from telemetry and is null until there is data — see
    |   H5PEngagementService.
    */
    'h5p_types' => [
        'interactive_video' => [
            'label' => 'Interactive Video',
            'description' => 'Video with questions, bookmarks and branching pauses embedded on the timeline.',
            'pal_use_cases' => ['Concept Learning V2', 'Flipped Classroom pre-session', 'Sports technique analysis', 'Music appreciation'],
            'bloom_from' => 'understand',
            'bloom_to' => 'evaluate',
            'xapi_events' => ['answered', 'completed', 'progressed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 1.3,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => false,
            'mobile_optimised' => true,
            'implementation' => [
                'status' => 'native',
                'source_table' => 'h5p_interactive_video',
                'child_table' => 'h5p_video_interactions',
                'child_foreign_key' => 'video_id',
                'child_label' => 'interaction',
                'columns' => [
                    'id' => 'id',
                    'title' => 'title',
                    'body' => 'video_path',
                    'chapter' => 'chapter_id',
                    'subject' => 'subject_id',
                    'standard' => 'standard_id',
                    'tenant' => 'sub_institute_id',
                    'created_by' => 'created_by',
                    'created_at' => 'created_at',
                    'soft_delete' => 'deleted_at',
                ],
                'route' => 'h5p_interactive_video.index',
                'module_title' => 'Interactive Video',
                'module_description' => 'Video with timed questions, hotspots and checkpoints',
                'icon' => 'mdi mdi-video',
                'sort_order' => 2,
            ],
        ],
        'course_presentation' => [
            'label' => 'Course Presentation',
            'description' => 'Slide deck with embedded interactions and progress indicators.',
            'pal_use_cases' => ['Concept Learning V1', 'Project presentation', 'Linear scenario'],
            'bloom_from' => 'understand',
            'bloom_to' => 'create',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'partial',
            'engagement_weight' => 1.0,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'branching_scenario' => [
            'label' => 'Branching Scenario',
            'description' => 'Decision tree where each choice leads to a different consequence path.',
            'pal_use_cases' => ['Scenario Based (primary)', 'Sky-level assessment', 'Career exploration', 'Finance decisions'],
            'bloom_from' => 'evaluate',
            'bloom_to' => 'create',
            'xapi_events' => ['answered', 'completed', 'progressed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 1.4,
            'social_mode' => 'individual',
            'gamification_potential' => 'high',
            'retry_allowed' => true,
            'offline_compatible' => false,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'documentation_tool' => [
            'label' => 'Documentation Tool',
            'description' => 'Structured evidence capture: photo, text and reflection collected into a portfolio item.',
            'pal_use_cases' => ['Experiential', 'Project Based', 'Vocational / Competency', 'Sports training log', 'Music portfolio'],
            'bloom_from' => 'apply',
            'bloom_to' => 'create',
            'xapi_events' => ['completed', 'submitted'],
            'fluency_trackable' => 'no',
            'engagement_weight' => 1.5,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => false,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'fill_in_the_blanks' => [
            'label' => 'Fill in the Blanks',
            'description' => 'Cloze text where the learner supplies the missing terms.',
            'pal_use_cases' => ['Practice L2–L3', 'Finance calculations', 'Language vocabulary'],
            'bloom_from' => 'apply',
            'bloom_to' => 'apply',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 0.9,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'drag_and_drop' => [
            'label' => 'Drag and Drop',
            'description' => 'Match, sort or place items onto targets.',
            'pal_use_cases' => ['Activity Based', 'Practice L2', 'Sports strategy board', 'Art Integrated'],
            'bloom_from' => 'apply',
            'bloom_to' => 'apply',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 1.1,
            'social_mode' => 'individual',
            'gamification_potential' => 'high',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'multiple_choice' => [
            'label' => 'Multiple Choice',
            'description' => 'Single or multi-select question with per-option feedback.',
            'pal_use_cases' => ['Assessment Bank', 'Diagnostic', 'Practice L1–L2'],
            'bloom_from' => 'recall',
            'bloom_to' => 'apply',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 0.8,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => [
                'status' => 'native',
                'source_table' => 'lms_question_master',
                'child_table' => 'answer_master',
                'child_foreign_key' => 'question_id',
                'child_label' => 'option',
                // The question bank is shared with the rest of the LMS, so the
                // MCQ view is the `question_type_id = 1` slice of it — there is
                // no separate H5P MCQ table and none should be created.
                'where' => ['question_type_id' => 1],
                'columns' => [
                    'id' => 'id',
                    'title' => 'question_title',
                    'body' => 'description',
                    'chapter' => 'chapter_id',
                    'subject' => 'subject_id',
                    'standard' => 'standard_id',
                    'tenant' => 'sub_institute_id',
                    'created_by' => 'created_by',
                    'created_at' => 'created_on',
                    'concept' => 'concept',
                    'learning_outcome' => 'learning_outcome',
                    'hint' => 'hint_text',
                ],
                'route' => 'h5p_mcq.index',
                'module_title' => 'Multiple Choice Questions',
                'module_description' => 'Level-banded MCQ drawn from the chapter question bank',
                'icon' => 'mdi mdi-help-circle-outline',
                'sort_order' => 3,
            ],
        ],
        'memory_game' => [
            'label' => 'Memory Game',
            'description' => 'Card-pair matching against recall and speed.',
            'pal_use_cases' => ['Game Based recall', 'Vocabulary pairs', 'Music notation'],
            'bloom_from' => 'recall',
            'bloom_to' => 'recall',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 1.35,
            'social_mode' => 'individual',
            'gamification_potential' => 'high',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'flash_cards' => [
            'label' => 'Flash Cards',
            'description' => 'Single-sided prompt cards with a checked answer and hint.',
            'pal_use_cases' => ['Flashcard pedagogy', 'Spaced review', 'Music theory'],
            'bloom_from' => 'recall',
            'bloom_to' => 'understand',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 1.0,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => [
                'status' => 'native',
                'source_table' => 'h5p_flashcard',
                'columns' => [
                    'id' => 'id',
                    'title' => 'question',
                    'body' => 'content',
                    'chapter' => 'chapter_id',
                    'subject' => 'subject_id',
                    'standard' => 'standard_id',
                    'tenant' => 'sub_institute_id',
                    'created_by' => 'created_by',
                    'created_at' => 'created_at',
                    'soft_delete' => 'deleted_at',
                    'answer' => 'correct_answer',
                    'hint' => 'hint',
                ],
                'route' => 'h5p_flashacard.index',
                'module_title' => 'Flash Cards',
                'module_description' => 'Spaced-repetition cards with hints and self-check',
                'icon' => 'mdi mdi-cards',
                'sort_order' => 4,
            ],
        ],
        'dialog_cards' => [
            'label' => 'Dialog Cards',
            'description' => 'Two-sided cards flipped for retrieval practice.',
            'pal_use_cases' => ['Flashcard pedagogy', 'Language', 'Q&A review'],
            'bloom_from' => 'recall',
            'bloom_to' => 'understand',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 1.0,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'image_hotspot' => [
            'label' => 'Image Hotspot',
            'description' => 'Annotated image where each hotspot reveals a titled explanation.',
            'pal_use_cases' => ['Art Integrated', 'Science labelling', 'Sports technique analysis', 'Misconception corrective'],
            'bloom_from' => 'understand',
            'bloom_to' => 'apply',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'partial',
            'engagement_weight' => 1.1,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => [
                'status' => 'native',
                'source_table' => 'h5p_scenarios',
                'child_table' => 'h5p_scenario_points',
                'child_foreign_key' => 'scenario_id',
                'child_label' => 'hotspot',
                'columns' => [
                    'id' => 'id',
                    'title' => 'title',
                    'body' => 'description',
                    'media' => 'file_path',
                    'chapter' => 'chapter_id',
                    'subject' => 'subject_id',
                    'standard' => 'standard_id',
                    'tenant' => 'sub_institute_id',
                    'created_by' => 'created_by',
                    'created_at' => 'created_at',
                    'soft_delete' => 'deleted_at',
                ],
                'route' => 'scenario_based.index',
                'module_title' => 'Scenario',
                'module_description' => 'Scenario-based learning from an annotated image',
                'icon' => 'fa fa-image',
                'sort_order' => 1,
            ],
        ],
        'crossword' => [
            'label' => 'Crossword',
            'description' => 'Clue-driven grid for terminology recall.',
            'pal_use_cases' => ['Game Based vocabulary', 'Language', 'Science terminology'],
            'bloom_from' => 'recall',
            'bloom_to' => 'recall',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'partial',
            'engagement_weight' => 1.3,
            'social_mode' => 'individual',
            'gamification_potential' => 'high',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => false,
            'implementation' => ['status' => 'planned'],
        ],
        'essay' => [
            'label' => 'Essay',
            'description' => 'Short extended response scored against keyword and rubric criteria.',
            'pal_use_cases' => ['Practice L4', 'NGSS explanation', 'CASEL reflection', 'Project report'],
            'bloom_from' => 'analyze',
            'bloom_to' => 'create',
            'xapi_events' => ['completed'],
            'fluency_trackable' => 'no',
            'engagement_weight' => 1.2,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => false,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'questionnaire' => [
            'label' => 'Questionnaire',
            'description' => 'Non-graded structured reflection or observation form.',
            'pal_use_cases' => ['CASEL self-reflection', 'Parent observation', 'Peer assessment', 'Post-activity reflection'],
            'bloom_from' => null,
            'bloom_to' => null,
            'xapi_events' => ['completed'],
            'fluency_trackable' => 'no',
            'engagement_weight' => 0.7,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => false,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'summary' => [
            'label' => 'Summary',
            'description' => 'Pick the statements that correctly summarise a passage.',
            'pal_use_cases' => ['NGSS communication practice', 'Reading comprehension'],
            'bloom_from' => 'understand',
            'bloom_to' => 'understand',
            'xapi_events' => ['completed'],
            'fluency_trackable' => 'partial',
            'engagement_weight' => 0.8,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'audio_recorder' => [
            'label' => 'Audio Recorder',
            'description' => 'Learner records and submits spoken or performed audio.',
            'pal_use_cases' => ['Music performance capture', 'Oral language assessment', 'Science explanation aloud'],
            'bloom_from' => 'apply',
            'bloom_to' => 'create',
            'xapi_events' => ['completed'],
            'fluency_trackable' => 'no',
            'engagement_weight' => 1.2,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => true,
            'offline_compatible' => false,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'agamotto' => [
            'label' => 'Agamotto',
            'description' => 'Sequenced image reveal for before/after and mystery framing.',
            'pal_use_cases' => ['Game Based mystery reveal', 'Before/after science concepts'],
            'bloom_from' => 'understand',
            'bloom_to' => 'understand',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'no',
            'engagement_weight' => 1.25,
            'social_mode' => 'individual',
            'gamification_potential' => 'high',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'mark_the_words' => [
            'label' => 'Mark the Words',
            'description' => 'Select the words in a passage that satisfy a stated rule.',
            'pal_use_cases' => ['Activity Based language', 'Reading comprehension'],
            'bloom_from' => 'understand',
            'bloom_to' => 'apply',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 0.95,
            'social_mode' => 'individual',
            'gamification_potential' => 'low',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'arithmetic_quiz' => [
            'label' => 'Arithmetic Quiz',
            'description' => 'Timed generated arithmetic drill for automaticity.',
            'pal_use_cases' => ['Finance calculations', 'Math practice L1–L2'],
            'bloom_from' => 'apply',
            'bloom_to' => 'apply',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'yes',
            'engagement_weight' => 1.0,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'find_the_hotspot' => [
            'label' => 'Find the Hotspot',
            'description' => 'Click the correct region of an image to answer a prompt.',
            'pal_use_cases' => ['Science labelling', 'Activity Based'],
            'bloom_from' => 'apply',
            'bloom_to' => 'apply',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'partial',
            'engagement_weight' => 1.05,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
        'image_sequencing' => [
            'label' => 'Image Sequencing',
            'description' => 'Order images into the correct process or chronological sequence.',
            'pal_use_cases' => ['Experiential before/after', 'Science process ordering'],
            'bloom_from' => 'understand',
            'bloom_to' => 'understand',
            'xapi_events' => ['answered', 'completed'],
            'fluency_trackable' => 'partial',
            'engagement_weight' => 1.05,
            'social_mode' => 'individual',
            'gamification_potential' => 'medium',
            'retry_allowed' => true,
            'offline_compatible' => true,
            'mobile_optimised' => true,
            'implementation' => ['status' => 'planned'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | §1.2 — the 12 pedagogies, and §9 — the coverage matrix
    |----------------------------------------------------------------------
    | coverage[framework][tag] = 'strong' | 'supporting'
    |   'strong'      = the §9 matrix marks this pedagogy "(strong)" — it is a
    |                   primary evidence generator for that framework tag.
    |   'supporting'  = listed in the matrix without the strong marker.
    | A framework absent from a pedagogy's coverage is the matrix's "—".
    */
    'pedagogy_tags' => [
        'inquiry_based' => [
            'label' => 'Inquiry Based Teaching',
            'description' => 'Question-led exploration: the phenomenon comes first, the explanation last.',
            'learning_theory' => 'Constructivism (Piaget, Vygotsky)',
            'nep_alignment' => 'CG-7 Observation and Logical Thinking; CG-6 Natural Environment Care',
            'hpc_domain' => 'awareness',
            'hpc_rubric' => [
                'stream' => 'Asks a prompted "I wonder why…" question',
                'mountain' => 'Generates a testable question independently; plans a way to find out',
                'sky' => 'Generates a novel question; designs the investigation; handles "what if X changes?"',
            ],
            'primary_h5p' => ['interactive_video', 'course_presentation'],
            'secondary_h5p' => ['essay', 'questionnaire'],
            'when_to_use' => ['Introducing a new science concept', 'Before revealing a mathematical pattern', 'Where discovery improves long-term retention'],
            'coverage' => [
                'casel' => ['social_awareness' => 'supporting'],
                'ngss' => ['asking_questions' => 'strong'],
                'ncdg' => ['EDL2' => 'supporting'],
            ],
            'aliases' => ['inquiry-based', 'concept_based', 'concept-based', 'socratic'],
        ],
        'experiential' => [
            'label' => 'Experiential Based Teaching',
            'description' => 'Real-world activity documented and reflected on, then generalised.',
            'learning_theory' => "Kolb's Experiential Learning Cycle",
            'nep_alignment' => 'Physical Education, Art and Vocational Education stages; CG-6, CG-11',
            'hpc_domain' => 'awareness',
            'hpc_rubric' => [
                'stream' => 'Describes what happened in the experience',
                'mountain' => 'Connects the experience to a concept; identifies what changed',
                'sky' => 'Generalises from the experience; applies it to a new situation; reflects on process',
            ],
            'primary_h5p' => ['documentation_tool'],
            'secondary_h5p' => ['image_sequencing'],
            'assessment_note' => 'Portfolio-assessed by the teacher with AI rubric support; BKT updates on reflection quality, not exam score.',
            'coverage' => [
                'casel' => ['self_management' => 'strong'],
                'ngss' => ['investigation' => 'supporting'],
                'ncdg' => ['EDL2' => 'strong'],
                'sports' => ['sports_fitness' => 'supporting'],
            ],
            'aliases' => ['audio'],
        ],
        'art_integrated' => [
            'label' => 'Art Integrated Teaching',
            'description' => 'Concepts carried through visual, performing and creative arts (NEP art-integration mandate).',
            'learning_theory' => 'NEP 2020 Art Integration + Multiple Intelligences (Gardner)',
            'nep_alignment' => 'NEP Art Integration mandate (compulsory across subjects); HPC Aesthetic & Cultural domain',
            'hpc_domain' => 'aesthetic_cultural',
            'hpc_rubric' => [
                'stream' => 'Creates art following given instructions',
                'mountain' => 'Adapts the art form to show understanding; explains the artistic choice',
                'sky' => 'Invents a new art-concept connection; presents it; evaluates others\' work',
            ],
            'primary_h5p' => ['image_hotspot', 'audio_recorder'],
            'secondary_h5p' => ['interactive_video', 'documentation_tool'],
            'gardner_intelligence' => ['spatial', 'musical', 'bodily_kinesthetic'],
            'special_tags' => ['nep_art_integration' => true],
            'coverage' => [
                'casel' => ['self_awareness' => 'supporting'],
                'music' => ['music_theory' => 'strong', 'music_appreciation' => 'strong'],
            ],
            'aliases' => ['visual-learning'],
        ],
        'game_based' => [
            'label' => 'Game Based Teaching',
            'description' => 'Optimal challenge, immediate feedback and a clear goal — served as a motivational injection.',
            'learning_theory' => 'Flow theory (Csikszentmihalyi)',
            'hpc_domain' => 'positive_learning_habits',
            'hpc_rubric' => [
                'stream' => 'Plays the game; completes the basic level',
                'mountain' => 'Develops a strategy; explains the decision-making',
                'sky' => 'Creates a variant; teaches the rules; identifies the concept inside the game design',
            ],
            'primary_h5p' => ['memory_game', 'crossword', 'agamotto', 'drag_and_drop'],
            'secondary_h5p' => ['course_presentation'],
            'engagement_note' => 'Typically 35–50% higher engagement. Serve when engagement drops, not as the primary content type — students may play without learning.',
            'coverage' => [
                'casel' => ['responsible_decision_making' => 'supporting'],
                'ncdg' => ['CM1' => 'supporting'],
            ],
            'aliases' => ['gamified'],
        ],
        'activity_based' => [
            'label' => 'Activity Based Teaching',
            'description' => 'Physical or virtual manipulation of objects before abstract symbolism.',
            'learning_theory' => 'Montessori + Constructivism',
            'nep_alignment' => 'Primary mode for the Foundational and Preparatory stages',
            'hpc_domain' => 'awareness',
            'hpc_rubric' => [
                'stream' => 'Completes the activity with guidance; names what happened',
                'mountain' => 'Explains the activity to a peer; connects it to the concept',
                'sky' => 'Modifies the activity; designs a variation; explains WHY it works',
            ],
            'primary_h5p' => ['drag_and_drop', 'mark_the_words', 'find_the_hotspot'],
            // §8.1 places Multiple Choice and Arithmetic Quiz at "Practice
            // L1–L2 / Apply", the same band this pedagogy works in, and they
            // are the practice formats this ERP's question bank actually
            // holds — so they are listed here rather than left unassigned.
            'secondary_h5p' => ['image_sequencing', 'fill_in_the_blanks', 'multiple_choice', 'arithmetic_quiz'],
            'coverage' => [
                'casel' => ['relationship_skills' => 'supporting'],
                'ngss' => ['investigation' => 'supporting'],
                'sports' => ['sports_fitness' => 'supporting', 'sports_technical' => 'supporting'],
            ],
            'aliases' => ['practice_based', 'practice-based'],
        ],
        'project_based' => [
            'label' => 'Project Based Teaching',
            'description' => 'Extended authentic work with milestones, evidence and an audience — the primary Sky-level vehicle.',
            'learning_theory' => 'Project-Based Learning (Buck Institute)',
            'hpc_domain' => 'creativity',
            'hpc_rubric' => [
                'stream' => 'Contributes to the group project with guidance; completes assigned tasks',
                'mountain' => 'Leads one aspect; explains decisions; adapts the plan when problems arise',
                'sky' => 'Leads the project; integrates multiple concepts; presents to an authentic audience',
            ],
            'primary_h5p' => ['documentation_tool'],
            'secondary_h5p' => ['course_presentation', 'essay'],
            'special_tags' => [
                'social_mode' => 'small_group',
                'peer_collaboration_required' => true,
                'teacher_rubric_assessed' => true,
                'portfolio_submission' => true,
                'soft_skill_signals' => ['collaboration', 'leadership', 'perseverance', 'communication'],
            ],
            'coverage' => [
                'casel' => ['relationship_skills' => 'strong', 'self_management' => 'supporting'],
                'ngss' => ['communication' => 'supporting'],
                'ncdg' => ['CM3' => 'strong'],
                'finance' => ['finance_planning' => 'supporting'],
            ],
            'aliases' => ['problem-based', 'problem_based'],
        ],
        'flashcard' => [
            'label' => 'Flashcard / Spaced Repetition Teaching',
            'description' => 'Strategic review at increasing intervals — the vehicle for the spaced repetition engine.',
            'learning_theory' => 'Ebbinghaus Forgetting Curve + Spaced Repetition System',
            'hpc_domain' => 'positive_learning_habits',
            'hpc_rubric' => [
                'stream' => 'Reviews the deck when prompted',
                'mountain' => 'Keeps the review schedule; recalls without the hint',
                'sky' => 'Self-schedules review; builds own decks; recalls at speed',
            ],
            'primary_h5p' => ['dialog_cards', 'flash_cards'],
            // Multiple Choice at §8.1's "Practice L1 / Recall" end is a
            // retrieval-practice format, which is what this pedagogy is.
            'secondary_h5p' => ['memory_game', 'multiple_choice'],
            'engagement_note' => 'Every session generates fluency data (correct/error per second) and updates the forgetting-curve parameters — the tightest data pipeline in PAL V4.',
            'coverage' => [
                'casel' => ['self_management' => 'supporting'],
                'ncdg' => ['EDL1' => 'supporting'],
                'music' => ['music_theory' => 'supporting'],
                'finance' => ['finance_literacy' => 'supporting'],
            ],
        ],
        'flipped_classroom' => [
            'label' => 'Flipped Classroom Teaching',
            'description' => 'Concept delivery at home, practice and application in class, with a completion gate between them.',
            'learning_theory' => 'Reversed homework/classwork model',
            'hpc_domain' => 'positive_learning_habits',
            'hpc_rubric' => [
                'stream' => 'Completes the pre-session when reminded',
                'mountain' => 'Completes the pre-session unprompted; arrives with questions',
                'sky' => 'Pre-reads beyond the set material; leads the in-class application',
            ],
            'primary_h5p' => ['interactive_video'],
            'secondary_h5p' => ['course_presentation', 'fill_in_the_blanks'],
            'gate' => [
                'requires_pre_session_completion' => true,
                'gated_practice_level_from' => 3,
                'reason_code' => 'flipped_gate',
            ],
            'coverage' => [
                'casel' => ['self_management' => 'strong'],
                'ngss' => ['communication' => 'supporting'],
                'ncdg' => ['EDL2' => 'strong'],
            ],
        ],
        'scenario_based' => [
            'label' => 'Scenario Based Teaching',
            'description' => 'A realistic Indian scenario where decisions carry consequences down different branches.',
            'learning_theory' => 'Situated Learning (Lave & Wenger)',
            'hpc_domain' => 'awareness',
            'hpc_rubric' => [
                'stream' => 'Chooses between two options with guidance',
                'mountain' => 'Evaluates consequences before deciding',
                'sky' => 'Handles If/Then logic; makes a principled decision; reflects on alternative outcomes',
            ],
            'primary_h5p' => ['branching_scenario'],
            'secondary_h5p' => ['course_presentation', 'image_hotspot'],
            'design_rules' => [
                'good' => ['India-specific context', 'Genuine ethical tension across paths', 'Real emotional stakes'],
                'avoid' => ['Generic western contexts', 'Only one right path', 'Pure logical puzzle with no stakes'],
            ],
            'coverage' => [
                'casel' => ['responsible_decision_making' => 'strong'],
                'ngss' => ['argumentation' => 'supporting'],
                'ncdg' => ['CM1' => 'strong', 'CM4' => 'strong', 'CM2' => 'supporting'],
                'sports' => ['sports_tactical' => 'supporting'],
                'finance' => ['finance_products' => 'strong', 'finance_planning' => 'strong'],
            ],
            'aliases' => ['story-based', 'simulation'],
        ],
        'spiritual_science' => [
            'label' => 'Spiritual Science Teaching',
            'description' => 'Values, mindfulness, wellbeing and Indian Knowledge Systems — non-religious, consciousness-based.',
            'learning_theory' => 'Indian Knowledge Systems (IKS) + NEP 2020 IKS mandate',
            'hpc_domain' => 'socio_emotional',
            'hpc_rubric' => [
                'stream' => 'Follows the guided practice',
                'mountain' => 'Names the effect of the practice on their own state',
                'sky' => 'Self-initiates the practice; connects it to their learning and wellbeing',
            ],
            'primary_h5p' => ['interactive_video'],
            'secondary_h5p' => ['questionnaire'],
            'special_tags' => [
                'iks_content' => true,
                'nep_iks_mandate' => true,
                'wellness_signal' => true,
                'mindfulness_content' => true,
                'non_religious' => true,
            ],
            'coverage' => [
                'casel' => ['self_awareness' => 'strong'],
                'ncdg' => ['PS1' => 'supporting'],
                'music' => ['music_appreciation' => 'supporting'],
                'sports' => ['sports_sportsmanship' => 'supporting'],
            ],
        ],
        'competency_based' => [
            'label' => 'Skill / Competency Based Teaching',
            'description' => 'Progression gated on demonstrated performance, not time spent; portfolio is the assessment.',
            'learning_theory' => 'Mastery Learning (Bloom) + Competency-Based Education',
            'nep_alignment' => 'NEP 2020 Vocational Education mandate; NSQF',
            'hpc_domain' => 'awareness',
            'hpc_rubric' => [
                'stream' => 'Demonstrates the skill with support',
                'mountain' => 'Demonstrates the skill unaided to the stated standard',
                'sky' => 'Demonstrates, teaches and adapts the skill in a new context',
            ],
            'primary_h5p' => ['documentation_tool'],
            'secondary_h5p' => ['audio_recorder'],
            'nsqf_levels' => [
                1 => ['grade_band' => '6–7', 'example' => 'Demonstrates basic computer operating skills'],
                2 => ['grade_band' => '7–8', 'example' => 'Completes simple data entry with 95% accuracy'],
                3 => ['grade_band' => '8–10', 'example' => 'Provides basic first aid to NSDC standard'],
                4 => ['grade_band' => '10–12', 'example' => 'Delivers customer service in a hospitality context'],
                5 => ['grade_band' => '12', 'example' => 'Manages a small team; completes professional certification'],
            ],
            'coverage' => [
                'casel' => ['self_management' => 'supporting'],
                'ngss' => ['investigation' => 'supporting'],
                'ncdg' => ['CM3' => 'strong'],
                'music' => ['music_performance' => 'supporting'],
                'sports' => ['sports_technical' => 'strong'],
                'finance' => ['finance_products' => 'supporting'],
            ],
        ],
        'concept_sports' => [
            'label' => 'Concept Based Teaching — Sports',
            'description' => 'Sport as the vehicle for cross-curricular academic concepts, with an abstraction step at mastery.',
            'learning_theory' => 'Transfer Learning + Gardner Bodily-Kinesthetic',
            'hpc_domain' => 'physical_development',
            'hpc_rubric' => [
                'stream' => 'Follows the sport context and answers within it',
                'mountain' => 'Reads the situation; explains the concept inside the sport context',
                'sky' => 'Transfers the concept out of the sport context to the abstract form',
            ],
            'primary_h5p' => ['interactive_video', 'drag_and_drop'],
            'secondary_h5p' => ['fill_in_the_blanks', 'image_hotspot'],
            'special_tags' => ['auto_suggest_abstract_at_mastery' => 0.75],
            'coverage' => [
                'casel' => ['relationship_skills' => 'supporting'],
                'ngss' => ['math_computation' => 'supporting'],
                'ncdg' => ['PS2' => 'supporting'],
                'sports' => ['sports_tactical' => 'strong'],
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | §1.3 — pedagogy selection rules
    |----------------------------------------------------------------------
    | Evaluated in sort order by H5PIntelligenceService::selectPedagogy().
    | `when` is a declarative condition on the session context; the first
    | match wins. Editing these rows in pal_vocabulary re-tunes the selector.
    */
    'pedagogy_selection_rules' => [
        'concept_required' => [
            'label' => 'Concept declares a required pedagogy',
            'when' => ['field' => 'concept.pedagogy_required', 'operator' => 'present'],
            'then' => ['use' => '@concept.pedagogy_required'],
            'sort_order' => 1,
        ],
        'spaced_review' => [
            'label' => 'Session is a spaced review',
            'when' => ['field' => 'session.type', 'operator' => 'equals', 'value' => 'spaced_review'],
            'then' => ['use' => 'flashcard'],
            'sort_order' => 2,
        ],
        'engagement_declining' => [
            'label' => 'Engagement trend is declining — motivational injection',
            'when' => ['field' => 'session.engagement_trend', 'operator' => 'equals', 'value' => 'declining'],
            'then' => ['use' => 'game_based', 'temporary' => true],
            'sort_order' => 3,
        ],
        'top_ranked_available' => [
            'label' => 'Highest-engagement pedagogy with content available',
            'when' => ['field' => 'history.ranked', 'operator' => 'any_available', 'value' => 3],
            'then' => ['use' => '@history.ranked'],
            'sort_order' => 4,
        ],
        'fallback' => [
            'label' => 'Fallback',
            'when' => ['operator' => 'always'],
            'then' => ['use' => 'inquiry_based'],
            'sort_order' => 99,
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | §2 — CASEL / SEL
    |----------------------------------------------------------------------
    */
    'casel_domains' => [
        'self_awareness' => [
            'label' => 'Self-Awareness',
            'description' => 'Recognise own emotions, values, strengths and limitations with honesty and confidence.',
            'hpc_lens' => 'Sensitivity',
            'data_sources' => ['Self-assessment conversational AI', 'Confidence dimension of the 9D student model', 'Spiritual Science reflections', 'Gardner intelligence signals'],
            'hpc_rubric' => [
                'stream' => 'Names own emotion when asked; recognises one personal strength',
                'mountain' => 'Explains why they feel a certain way; connects emotion to situation',
                'sky' => 'Proactively manages emotions; accurately assesses own learning needs; seeks help appropriately',
            ],
            'ncdg' => ['PS1'],
            'h5p' => ['questionnaire'],
        ],
        'self_management' => [
            'label' => 'Self-Management',
            'description' => 'Regulate emotions, manage stress, set goals and motivate oneself.',
            'hpc_lens' => 'Sensitivity',
            'data_sources' => ['Session return frequency', 'Attempts before giving up', 'Goal-setting feature', 'Flipped pre-completion rate', 'Spaced review completion rate'],
            'hpc_rubric' => [
                'stream' => 'Follows routines with reminders; completes short tasks',
                'mountain' => 'Sets a small goal and tracks progress; manages frustration in a learning task',
                'sky' => 'Adjusts strategy proactively when the first approach fails; motivates peers; reflects on own growth',
            ],
            'ncdg' => ['PS1', 'EDL2'],
            'h5p' => ['documentation_tool', 'questionnaire'],
        ],
        'social_awareness' => [
            'label' => 'Social Awareness',
            'description' => 'Empathy for others from diverse backgrounds; understanding social and ethical norms.',
            'hpc_lens' => 'Sensitivity',
            'data_sources' => ['Peer assessment responses', 'Art Integrated sharing and appreciation', 'NGSS argumentation', 'Scenario stakeholder reasoning'],
            'hpc_rubric' => [
                'stream' => 'Recognises others feel differently with help',
                'mountain' => 'Considers diverse perspectives in discussion',
                'sky' => 'Advocates for peers; explains systemic unfairness',
            ],
            'ncdg' => ['PS2'],
            'h5p' => ['questionnaire', 'essay'],
        ],
        'relationship_skills' => [
            'label' => 'Relationship Skills',
            'description' => 'Communicate, collaborate, resolve conflict and work effectively in groups.',
            'hpc_lens' => 'Sensitivity + Creativity',
            'data_sources' => ['Project collaboration data', 'Peer assessment scores', 'Teacher HPC sensitivity ratings', 'Activity Based group mode'],
            'hpc_rubric' => [
                'stream' => 'Takes turns; follows group rules; cooperates with prompting',
                'mountain' => 'Initiates collaboration; resolves minor disagreements; listens actively',
                'sky' => 'Leads the group productively; mediates conflict; motivates discouraged peers',
            ],
            'ncdg' => ['PS2'],
            'h5p' => ['documentation_tool'],
        ],
        'responsible_decision_making' => [
            'label' => 'Responsible Decision Making',
            'description' => 'Make ethical, constructive choices about personal and social behaviour.',
            'hpc_lens' => 'Awareness',
            'data_sources' => ['Branching scenario paths and steps to optimal', 'Sky-level conditional branching results', 'NCDG CM1 career decisions', 'If/Then task performance'],
            'hpc_rubric' => [
                'stream' => 'Chooses between two options with guidance',
                'mountain' => 'Evaluates consequences before deciding',
                'sky' => 'Analyses ethical dimensions; makes a principled choice independently',
            ],
            'ncdg' => ['CM1'],
            'h5p' => ['branching_scenario'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | §3 — NGSS science & engineering practices
    |----------------------------------------------------------------------
    */
    'ngss_practices' => [
        'asking_questions' => ['label' => 'Asking Questions and Defining Problems', 'ncert_cg' => 'CG-7 Observation & Logical Thinking', 'bloom_level' => 'analyze', 'h5p' => ['questionnaire', 'essay'], 'hpc_lens' => ['Awareness']],
        'developing_models' => ['label' => 'Developing and Using Models', 'ncert_cg' => 'CG-7 + CG-8', 'bloom_level' => 'create', 'h5p' => ['documentation_tool', 'interactive_video'], 'hpc_lens' => ['Creativity']],
        'investigation' => ['label' => 'Planning and Carrying Out Investigations', 'ncert_cg' => 'CG-6 Natural Environment', 'bloom_level' => 'apply', 'h5p' => ['documentation_tool', 'image_sequencing'], 'hpc_lens' => ['Awareness', 'Creativity']],
        'data_analysis' => ['label' => 'Analysing and Interpreting Data', 'ncert_cg' => 'CG-8 Math Understanding', 'bloom_level' => 'analyze', 'h5p' => ['image_hotspot'], 'hpc_lens' => ['Awareness']],
        'math_computation' => ['label' => 'Using Mathematics and Computational Thinking', 'ncert_cg' => 'CG-8', 'bloom_level' => 'apply', 'h5p' => ['fill_in_the_blanks', 'drag_and_drop', 'arithmetic_quiz'], 'hpc_lens' => ['Awareness']],
        'explanation' => ['label' => 'Constructing Explanations', 'ncert_cg' => 'CG-7 Logical Thinking', 'bloom_level' => 'evaluate', 'h5p' => ['essay', 'fill_in_the_blanks'], 'hpc_lens' => ['Awareness']],
        'argumentation' => ['label' => 'Engaging in Argument from Evidence', 'ncert_cg' => 'CG-13 Habits for formal learning', 'bloom_level' => 'evaluate', 'h5p' => ['essay', 'branching_scenario'], 'hpc_lens' => ['Awareness', 'Sensitivity']],
        'communication' => ['label' => 'Obtaining, Evaluating and Communicating Information', 'ncert_cg' => 'CG-12 Language & Literacy', 'bloom_level' => 'understand', 'h5p' => ['summary', 'course_presentation'], 'hpc_lens' => ['Awareness']],
    ],

    /*
    |----------------------------------------------------------------------
    | §4 — NCDG career development goals
    |----------------------------------------------------------------------
    */
    'ncdg_goals' => [
        'PS1' => ['label' => 'PS1 — Self-Knowledge', 'ncdg_domain' => 'personal_social', 'activation_grade' => 1, 'data_sources' => ['9D student model', 'Self-assessment AI', 'RIASEC signals'], 'output_report' => 'Annual Self-Portrait Report'],
        'PS2' => ['label' => 'PS2 — Interpersonal Skills', 'ncdg_domain' => 'personal_social', 'activation_grade' => 3, 'data_sources' => ['Peer assessment', 'Collaboration data', 'CASEL relationship skills'], 'output_report' => 'HPC Sensitivity report'],
        'EDL1' => ['label' => 'EDL1 — Academic Achievement', 'ncdg_domain' => 'educational', 'activation_grade' => 1, 'data_sources' => ['BKT mastery', 'Fluency trends', 'Subject aptitude profiles'], 'output_report' => 'Academic Aptitude Profile (Grade 5, 8, 10)'],
        'EDL2' => ['label' => 'EDL2 — Lifelong Learning', 'ncdg_domain' => 'educational', 'activation_grade' => 3, 'data_sources' => ['Engagement trends', 'Self-directed exploration', 'Return frequency'], 'output_report' => 'Learning Disposition Score'],
        'CM1' => ['label' => 'CM1 — Decision Making', 'ncdg_domain' => 'career_management', 'activation_grade' => 6, 'data_sources' => ['Branching scenario performance', 'Ethical reasoning tasks'], 'output_report' => 'Decision Quality Index'],
        'CM2' => ['label' => 'CM2 — Career Information', 'ncdg_domain' => 'career_management', 'activation_grade' => 6, 'data_sources' => ['RIASEC accumulation', 'Vocational stream exploration', 'NEP pathway data'], 'output_report' => 'Career Cluster Recommendations'],
        'CM3' => ['label' => 'CM3 — Employment Skills', 'ncdg_domain' => 'career_management', 'activation_grade' => 8, 'data_sources' => ['Project rubric scores', 'Soft skill signals', 'Portfolio evidence'], 'output_report' => 'Employability Readiness Score'],
        'CM4' => ['label' => 'CM4 — Career Planning', 'ncdg_domain' => 'career_management', 'activation_grade' => 8, 'data_sources' => ['All NCDG goals above', 'Student interest declaration'], 'output_report' => 'Career Pathway Recommendation Report'],
    ],

    /*
    |----------------------------------------------------------------------
    | §5 / §6 / §7 — the three interest-based pathways
    |----------------------------------------------------------------------
    */
    'music_domains' => [
        'music_theory' => ['label' => 'Music Theory', 'description' => 'Notation, rhythm, raga structure, tala, solfège.', 'hpc_domain' => 'aesthetic_cultural', 'casel' => 'self_awareness', 'h5p' => ['flash_cards', 'interactive_video']],
        'music_performance' => ['label' => 'Performance', 'description' => 'Vocal and instrumental technique; practice and refinement.', 'hpc_domain' => 'aesthetic_cultural, physical', 'casel' => 'self_management', 'h5p' => ['audio_recorder', 'interactive_video']],
        'music_composition' => ['label' => 'Composition', 'description' => 'Creating original pieces, arrangements and variations.', 'hpc_domain' => 'aesthetic_cultural', 'casel' => 'self_awareness', 'h5p' => ['documentation_tool', 'audio_recorder']],
        'music_appreciation' => ['label' => 'Appreciation', 'description' => 'Cultural context, listening analysis, historical knowledge.', 'hpc_domain' => 'aesthetic_cultural, socio_emotional', 'casel' => 'social_awareness', 'h5p' => ['interactive_video', 'questionnaire']],
    ],

    'sports_domains' => [
        'sports_fitness' => ['label' => 'Physical Fitness', 'description' => 'Endurance, strength, flexibility, speed, coordination.', 'hpc_domain' => 'physical_development', 'casel' => 'self_management', 'h5p' => ['documentation_tool', 'interactive_video'], 'levels' => ['stream' => 'Meets basic norms', 'mountain' => 'Exceeds age norms', 'sky' => 'Trains others; designs a programme']],
        'sports_technical' => ['label' => 'Technical Skills', 'description' => 'Sport-specific technique: batting, swimming stroke, kabaddi raid, shot put.', 'hpc_domain' => 'physical_development', 'casel' => null, 'h5p' => ['interactive_video', 'image_hotspot'], 'levels' => ['stream' => 'Demonstrates basic form', 'mountain' => 'Consistent technique', 'sky' => 'Coaches others']],
        'sports_tactical' => ['label' => 'Tactical Understanding', 'description' => 'Game sense, decision-making under pressure, team strategy.', 'hpc_domain' => 'physical_development, cognitive', 'casel' => 'responsible_decision_making', 'h5p' => ['branching_scenario', 'drag_and_drop'], 'levels' => ['stream' => 'Follows instructions', 'mountain' => 'Reads game situations', 'sky' => 'Designs team strategy']],
        'sports_sportsmanship' => ['label' => 'Sportsmanship & Leadership', 'description' => 'Fair play, resilience after loss, motivating the team, respecting opponents.', 'hpc_domain' => 'socio_emotional', 'casel' => 'relationship_skills', 'h5p' => ['questionnaire'], 'levels' => ['stream' => 'Follows rules', 'mountain' => 'Supports teammates', 'sky' => 'Leads the team; models fair play']],
    ],

    'finance_levels' => [
        'finance_literacy' => [
            'label' => 'Level 1 — Financial Literacy',
            'description' => 'Budget, simple interest, banking basics, consumer protection.',
            'grade_min' => 5, 'grade_max' => 7, 'level' => 1,
            'concepts' => ['Budget: income, expenses, savings', 'Simple interest', 'Savings account and fixed deposit', 'Receipts, bills and UPI transactions'],
            'h5p' => ['fill_in_the_blanks', 'branching_scenario'],
            'pedagogy' => 'flashcard', 'ncdg' => ['CM2'], 'casel' => 'responsible_decision_making',
            'gate' => 'default_on_activation',
        ],
        'finance_products' => [
            'label' => 'Level 2 — Banking Products',
            'description' => 'Indian banking system; informed product comparison.',
            'grade_min' => 7, 'grade_max' => 9, 'level' => 2,
            'concepts' => ['Account types', 'Loans, interest rates and EMI', 'Insurance', 'Government schemes: Jan Dhan, PM Mudra, Kisan Credit Card', 'NEFT, IMPS, UPI and mobile banking safety'],
            'h5p' => ['course_presentation', 'branching_scenario'],
            'pedagogy' => 'scenario_based', 'ncdg' => ['CM2'], 'casel' => 'responsible_decision_making',
            'gate' => 'level_1_mastery > 0.70',
        ],
        'finance_planning' => [
            'label' => 'Level 3 — Financial Planning',
            'description' => 'Create and implement a basic financial plan; understand risk.',
            'grade_min' => 9, 'grade_max' => 11, 'level' => 3,
            'concepts' => ['Goal-based saving', 'Risk and return', 'Compound interest', 'Tax basics: income tax, TDS', 'Credit score'],
            'h5p' => ['interactive_video', 'documentation_tool'],
            'pedagogy' => 'project_based', 'ncdg' => ['CM3'], 'casel' => 'responsible_decision_making',
            'gate' => 'level_2_mastery > 0.70 AND grade >= 9',
        ],
        'finance_investing' => [
            'label' => 'Level 4 — Investing Basics',
            'description' => 'Investment markets and informed investing decisions.',
            'grade_min' => 11, 'grade_max' => 12, 'level' => 4,
            'concepts' => ['Shares, dividends, market capitalisation', 'Mutual funds: SIP, NAV', 'Bonds and government securities', 'Portfolio diversification', 'NSE, BSE, SEBI, RBI'],
            'h5p' => ['interactive_video', 'branching_scenario'],
            'pedagogy' => 'scenario_based', 'ncdg' => ['CM2', 'CM3'], 'casel' => 'responsible_decision_making',
            'gate' => 'level_3_mastery > 0.70 AND grade >= 11',
        ],
    ],

    /*
    | §7.1 — the finance pathway is interest-activated, not compulsory.
    */
    'finance_activation' => [
        'riasec' => ['C', 'E'],
        'grade_min' => 5,
        'triggers' => ['riasec_CE_high', 'explicit_student_interest', 'teacher_assign', 'parent_request'],
    ],

    /*
    |----------------------------------------------------------------------
    | Legacy code aliases
    |----------------------------------------------------------------------
    | The registry already carried NGSS and NCDG rows under earlier spellings
    | before the PAL V4 codes landed. Deleting them would break anything
    | already tagged with them, so they stay as rows and are marked
    | `metadata.alias_of` instead: the canonical catalog hides them, and
    | H5PModelRegistry::normalize() still resolves them to the canonical code.
    |
    | `null` means the legacy code has no PAL V4 equivalent — it is retired
    | from the catalog but still resolvable to itself for old tags.
    */
    'legacy_aliases' => [
        'ngss_practices' => [
            'planning_investigations' => 'investigation',
            'analyzing_data' => 'data_analysis',
            'using_mathematics' => 'math_computation',
            'constructing_explanations' => 'explanation',
            'engaging_in_argument' => 'argumentation',
            'obtaining_evaluating_communicating' => 'communication',
        ],
        'ncdg_goals' => [
            'ED1' => 'EDL1',
            'PS3' => null,
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Cross-cutting signal vocabularies
    |----------------------------------------------------------------------
    */
    'gardner_intelligences' => [
        'linguistic' => ['label' => 'Linguistic'],
        'logical_mathematical' => ['label' => 'Logical-Mathematical'],
        'spatial' => ['label' => 'Spatial'],
        'musical' => ['label' => 'Musical'],
        'bodily_kinesthetic' => ['label' => 'Bodily-Kinesthetic'],
        'naturalist' => ['label' => 'Naturalist'],
        'interpersonal' => ['label' => 'Interpersonal'],
        'intrapersonal' => ['label' => 'Intrapersonal'],
    ],

    'riasec_signals' => [
        'R' => ['label' => 'Realistic'],
        'I' => ['label' => 'Investigative'],
        'A' => ['label' => 'Artistic'],
        'S' => ['label' => 'Social'],
        'E' => ['label' => 'Enterprising'],
        'C' => ['label' => 'Conventional'],
    ],

    'hpc_lenses' => [
        'Awareness' => ['label' => 'Awareness'],
        'Sensitivity' => ['label' => 'Sensitivity'],
        'Creativity' => ['label' => 'Creativity'],
    ],

    /*
    |----------------------------------------------------------------------
    | §8.2 — xAPI verb map
    |----------------------------------------------------------------------
    | code           short verb stored on pal_telemetry_events.verb
    | metadata.iri   the xAPI verb IRI H5P emits
    | metadata.pal_event_type   the PAL event this becomes
    | metadata.jobs  which downstream processors the event feeds
    */
    'xapi_verbs' => [
        'answered' => ['label' => 'Answered', 'iri' => 'http://adlnet.gov/expapi/verbs/answered', 'pal_event_type' => 'assessment_answer', 'jobs' => ['store_event', 'update_bkt', 'check_misconception', 'update_fluency']],
        'completed' => ['label' => 'Completed', 'iri' => 'http://adlnet.gov/expapi/verbs/completed', 'pal_event_type' => 'content_completed', 'jobs' => ['store_event', 'update_engagement', 'update_riasec']],
        'attempted' => ['label' => 'Attempted', 'iri' => 'http://adlnet.gov/expapi/verbs/attempted', 'pal_event_type' => 'content_attempted', 'jobs' => ['store_event']],
        'progressed' => ['label' => 'Progressed', 'iri' => 'http://adlnet.gov/expapi/verbs/progressed', 'pal_event_type' => 'content_progressed', 'jobs' => ['store_event', 'update_engagement']],
        'submitted' => ['label' => 'Submitted', 'iri' => 'http://adlnet.gov/expapi/verbs/submitted', 'pal_event_type' => 'portfolio_submitted', 'jobs' => ['store_event', 'update_engagement']],
        'initialized' => ['label' => 'Initialized', 'iri' => 'http://adlnet.gov/expapi/verbs/initialized', 'pal_event_type' => 'content_started', 'jobs' => ['store_event']],
        'interacted' => ['label' => 'Interacted', 'iri' => 'http://adlnet.gov/expapi/verbs/interacted', 'pal_event_type' => 'content_interacted', 'jobs' => ['store_event', 'update_engagement']],
        'passed' => ['label' => 'Passed', 'iri' => 'http://adlnet.gov/expapi/verbs/passed', 'pal_event_type' => 'content_passed', 'jobs' => ['store_event', 'update_bkt']],
        'failed' => ['label' => 'Failed', 'iri' => 'http://adlnet.gov/expapi/verbs/failed', 'pal_event_type' => 'content_failed', 'jobs' => ['store_event', 'update_bkt', 'check_misconception']],
        'experienced' => ['label' => 'Experienced', 'iri' => 'http://adlnet.gov/expapi/verbs/experienced', 'pal_event_type' => 'content_experienced', 'jobs' => ['store_event']],
        'suspended' => ['label' => 'Suspended', 'iri' => 'http://adlnet.gov/expapi/verbs/suspended', 'pal_event_type' => 'content_suspended', 'jobs' => ['store_event']],
        'resumed' => ['label' => 'Resumed', 'iri' => 'http://adlnet.gov/expapi/verbs/resumed', 'pal_event_type' => 'content_resumed', 'jobs' => ['store_event']],
        'video_paused' => ['label' => 'Paused', 'iri' => 'https://pedagogy.ai/verbs/paused', 'pal_event_type' => 'video_paused', 'jobs' => ['store_event'], 'important' => true],
        'video_replayed' => ['label' => 'Replayed', 'iri' => 'https://pedagogy.ai/verbs/replayed', 'pal_event_type' => 'video_replayed', 'jobs' => ['store_event'], 'important' => true],
        'hint_opened' => ['label' => 'Hint used', 'iri' => 'https://pedagogy.ai/verbs/hint_used', 'pal_event_type' => 'hint_opened', 'jobs' => ['store_event'], 'important' => true],
        'rapid_guessing' => ['label' => 'Guessed rapidly', 'iri' => 'https://pedagogy.ai/verbs/guessed_rapidly', 'pal_event_type' => 'rapid_guessing', 'jobs' => ['store_event'], 'important' => true],
        'repeated_failure' => ['label' => 'Failed repeatedly', 'iri' => 'https://pedagogy.ai/verbs/failed_repeatedly', 'pal_event_type' => 'repeated_failure', 'jobs' => ['store_event', 'check_misconception'], 'important' => true],
        'inactivity' => ['label' => 'Became inactive', 'iri' => 'https://pedagogy.ai/verbs/became_inactive', 'pal_event_type' => 'inactivity', 'jobs' => ['store_event', 'update_engagement'], 'important' => true],
        'section_revisit' => ['label' => 'Revisited', 'iri' => 'https://pedagogy.ai/verbs/revisited', 'pal_event_type' => 'section_revisit', 'jobs' => ['store_event'], 'important' => true],
    ],

    /*
    |----------------------------------------------------------------------
    | Engagement composition (PAL_V4_AI_Pedagogy_Engine §engagement score)
    |----------------------------------------------------------------------
    | Weights sum to 1.0. H5PEngagementService reads these from the registry
    | so a deployment can re-weight without a code change.
    */
    'engagement_signals' => [
        'time_on_task' => ['label' => 'Time on task', 'weight' => 0.30],
        'interaction_rate' => ['label' => 'Interaction rate', 'weight' => 0.25],
        'session_return' => ['label' => 'Session return', 'weight' => 0.25],
        'voluntary_extension' => ['label' => 'Voluntary extension', 'weight' => 0.20],
    ],

    /*
    |----------------------------------------------------------------------
    | AI tagging
    |----------------------------------------------------------------------
    | Used only when a node cannot be tagged from the database. Anything the
    | model returns is stamped tagged_by = 'ai', quality_status = 'draft'
    | (CONTENT LAW C5 — a machine may propose, never approve).
    */
    'ai' => [
        'enabled' => (bool) env('PAL_H5P_AI', true),
        'max_nodes_per_call' => 8,
        'cache_kind' => 'h5p_model_tagging',
    ],

    /*
    |----------------------------------------------------------------------
    | DeepSeek insight layer over the xAPI event stream
    |----------------------------------------------------------------------
    | Sits ON TOP of the xAPI pipeline — it does not replace it. The pipeline
    | keeps ingesting statements and producing the measured engagement
    | numbers; H5PInsightService aggregates those events into an evidence
    | pack (pure SQL, no model) and asks DeepSeek what the pack means.
    |
    | The model narrates; it never supplies a number and never names a node,
    | pedagogy or H5P type that is not in the evidence and the registry.
    | With no events in the window it is not called at all.
    |
    | Provider resolution is shared with the Content Model
    | (config/pal_content_model.php → llm.providers), where DeepSeek is
    | first in the chain. Set DEEPSEEK_API_KEY (or an `ai_api_keys` row with
    | api_type = DEEPSEEK_API_KEY) to call api.deepseek.com directly instead
    | of reaching deepseek/deepseek-chat through OpenRouter.
    */
    'insight' => [
        'enabled' => (bool) env('PAL_H5P_INSIGHT', true),
        'cache_kind' => 'h5p_insight',
        // Rolling window of events an insight is generated from.
        'window_days' => (int) env('PAL_H5P_INSIGHT_WINDOW_DAYS', 30),
        // Cap on how many nodes enter the evidence pack, so a 500-question
        // chapter does not blow the context window.
        'max_nodes_in_pack' => (int) env('PAL_H5P_INSIGHT_MAX_NODES', 25),
        // A node counts as "struggling" below this accuracy, once it has at
        // least this many judged attempts. Computed in SQL, not by the model —
        // which node is failing is arithmetic.
        'struggle_accuracy_below' => 0.6,
        'struggle_min_attempts' => 3,
    ],
];
