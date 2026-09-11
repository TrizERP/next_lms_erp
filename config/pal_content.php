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
    | Blueprint categories — the board's question-type taxonomy
    |--------------------------------------------------------------------------
    |
    | The BOARD_COMPLIANCE half of an item's dual fitness (see the note on
    | PAL calibration in QuestionMetadata). A board blueprint is written in
    | these terms — "20 marks of short answer, 15 of case-based" — so an item
    | cannot be placed in a paper without one, however well calibrated it is.
    |
    | `typical_marks` is the customary weighting for the category, kept as
    | guidance for authoring rather than as a constraint: the marks actually
    | carried by an item live on the row, because boards vary the weighting
    | between papers and years. An item whose marks differ from the typical
    | value is not invalid.
    |
    | CBSE terminology, since that is the board in scope. Other boards get
    | their own entries here rather than a parallel column.
    |
    */
    'blueprint_categories' => [
        'mcq'                 => ['label' => 'Multiple choice',        'typical_marks' => 1],
        'assertion_reason'    => ['label' => 'Assertion & reason',     'typical_marks' => 1],
        'very_short_answer'   => ['label' => 'Very short answer',      'typical_marks' => 2],
        'short_answer'        => ['label' => 'Short answer',           'typical_marks' => 3],
        'long_answer'         => ['label' => 'Long answer',            'typical_marks' => 5],
        'case_based'          => ['label' => 'Case / source based',    'typical_marks' => 4],
        'competency_based'    => ['label' => 'Competency based',       'typical_marks' => 4],
    ],

    /*
    |--------------------------------------------------------------------------
    | Learning purpose — what a content object is FOR
    |--------------------------------------------------------------------------
    |
    | Orthogonal to content_type and format. Those say what a thing IS; this
    | says what job it does. Without it PAL's Corrective Micro-Lesson step can
    | only ask "what else exists on this concept?", which is how a student who
    | has just failed a question gets handed the assessment item they failed,
    | or a stretch activity, as their remediation.
    |
    | `phase` groups purposes by where they sit in the loop. `corrective`
    | marks the ones that may be served as an alternate explanation after a
    | failure — deliberately NOT the same as "everything that teaches":
    |
    |   - assess purposes are excluded because serving an assessment item as a
    |     micro-lesson shows the student the thing being measured;
    |   - `enrich` is excluded because a student who just failed needs the
    |     concept again, not an extension beyond it;
    |   - `prerequisite` IS included: the honest answer to some failures is
    |     that the gap is upstream of the concept being taught.
    |
    */
    'learning_purposes' => [
        'understand'   => ['label' => 'Understand',   'phase' => 'teach',    'corrective' => true,  'description' => 'Build the initial schema for a concept the learner has not met.'],
        'prerequisite' => ['label' => 'Prerequisite', 'phase' => 'teach',    'corrective' => true,  'description' => 'Cover the upstream concept this one depends on.'],
        'explain'      => ['label' => 'Explain',      'phase' => 'teach',    'corrective' => true,  'description' => 'Restate the concept a different way for a learner who did not follow the first.'],
        'demonstrate'  => ['label' => 'Demonstrate',  'phase' => 'teach',    'corrective' => true,  'description' => 'Show the concept worked through end to end.'],
        'practice'     => ['label' => 'Practice',     'phase' => 'practice', 'corrective' => false, 'description' => 'Repetition to build fluency at a known level.'],
        'apply'        => ['label' => 'Apply',        'phase' => 'practice', 'corrective' => false, 'description' => 'Use the concept in a familiar problem context.'],
        'transfer'     => ['label' => 'Transfer',     'phase' => 'practice', 'corrective' => false, 'description' => 'Use the concept in an unfamiliar context.'],
        'remediate'    => ['label' => 'Remediate',    'phase' => 'support',  'corrective' => true,  'description' => 'Address a specific, identified misconception.'],
        'enrich'       => ['label' => 'Enrich',       'phase' => 'support',  'corrective' => false, 'description' => 'Extend beyond the concept for a learner who already has it.'],
        'recall'       => ['label' => 'Recall',       'phase' => 'assess',   'corrective' => false, 'description' => 'Spaced retrieval of previously demonstrated material.'],
        'assess'       => ['label' => 'Assess',       'phase' => 'assess',   'corrective' => false, 'description' => 'Measure mastery. Never served as teaching.'],
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
    | Diagnostic item selection — what "calibrated" means at serve time
    |--------------------------------------------------------------------------
    |
    | quality_status = approved is an EDITORIAL gate: a human agreed the item
    | is fit to publish. It says nothing about whether the item separates
    | learners, which is what a diagnostic needs. An item is treated as
    | calibrated here only when its psychometrics were actually derived from
    | responses by `pal:derive-irt` — reusing the same two thresholds that
    | command already applies, so "calibrated" means one thing in this codebase.
    |
    | `require_calibrated` is the policy switch. Left FALSE, the diagnostic
    | prefers calibrated items and falls back to approved-only ones rather than
    | serving an empty diagnostic — today almost nothing is calibrated, so
    | flipping this true would take the loop offline for most concepts. Turn it
    | on per tenant once coverage is real; the response's `calibration` block
    | reports how close that is.
    |
    */
    'diagnostic' => [
        'require_calibrated' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Tutor — grounding and governance
    |--------------------------------------------------------------------------
    |
    | Alpha School / TimeBack disabled chat outright on the stated grounds that
    | "90% of kids use chatbots to cheat". The response taken here is narrower
    | than a ban: the tutor stays available, but it may not hand over an answer
    | a student has not yet tried to reach.
    |
    | `min_genuine_attempts_for_direct_answer` is the number of UNASSISTED
    | attempts (hint_used = false) a learner must have logged on a concept
    | before the tutor will explain directly rather than Socratically. It
    | gates explanation only.
    |
    | Answers to assessment items are never unlocked by attempt count — see
    | AiTutorContextService, where that clause is unconditional. Raising this
    | number makes the tutor more Socratic; there is no value that turns the
    | assessment-answer rule off.
    |
    */
    'ai_tutor' => [
        'min_genuine_attempts_for_direct_answer' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pedagogy Engine — per-tier rollout switches
    |--------------------------------------------------------------------------
    |
    | Each authored tier can be turned off independently, without a deploy.
    |
    | These exist because the tiers were dark for a long time (52 authored rules,
    | none reachable from a student flow) and are now being switched on one at a
    | time. A tier that starts making bad decisions in front of students must be
    | stoppable in seconds, and stopping ONE tier must not take the others down
    | with it.
    |
    | Turning a tier off returns the selector to its previous behaviour for that
    | tier — the hardcoded path is still there and still correct — so this
    | degrades rather than breaking.
    |
    | Only the tiers actually wired appear here. Tiers 2, 3 and 5 are not
    | listed because they are not yet executable: their thresholds live only in
    | the prose `condition` column, and tier 2's input signal is the
    | mis-derived `engagement_score` (see tracker #32).
    |
    */
    'pedagogy' => [
        'tiers' => [
            'tier-1' => true,   // mastery bands -> content type
            'tier-2' => false,  // engagement state — disabled until real engagement_score exists (tracker #32)
            'tier-4' => true,   // learning style -> format order
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capability Confidence — "does this learner actually have it?"
    |--------------------------------------------------------------------------
    |
    | One of the three scores in the Evidence & Confidence framework, and the
    | only one that is about a person rather than about content. Built here
    | because the worked ESO example (tracker #25) specifies its bands as
    | CONFIRMED and requires them to be configurable rather than hardcoded.
    |
    | It is deliberately NOT the BKT mastery estimate. The spec is explicit that
    | capability must be evidence-driven — attempt variety, hint dependence,
    | independent versus assisted performance — because a learner can reach a
    | high score by repeating one question with hints, and that is not the same
    | as being able to do it.
    |
    | `weights` must sum to 1.0; the service asserts this rather than silently
    | renormalising, since a typo would otherwise shift every band quietly.
    |
    |   mastery      the BKT estimate. Still the largest single input — what
    |                they can do matters most — but never the only one.
    |   independence share of attempts made without a hint, in independent mode.
    |   variety      breadth of distinct questions the evidence covers, against
    |                `variety_target`. Answering one item ten times is one piece
    |                of evidence repeated, not ten.
    |
    */
    'capability_confidence' => [
        'weights' => [
            'mastery' => 0.60,
            'independence' => 0.25,
            'variety' => 0.15,
        ],

        // Distinct questions at which variety is considered fully evidenced.
        'variety_target' => 5,

        // Below this many attempts there is not enough to judge capability at
        // all, and the service returns null rather than a number built from
        // one data point.
        'min_attempts' => 3,

        /*
        | CONFIRMED bands (tracker #25). Ordered high to low; the first whose
        | `min` is met wins.
        |
        | NOTE: the source sheet specifies <0.60 relearn, 0.80-0.90 independent
        | application, >0.90 mastery verification, >0.95 delayed retrieval — and
        | says nothing about 0.60-0.80. That gap is named `consolidating` here
        | rather than folded into a neighbouring band, because silently
        | extending `relearn` up to 0.80 would send a learner back through
        | content they had largely demonstrated. Confirm the intended label.
        */
        'bands' => [
            ['key' => 'stable_mastery', 'min' => 0.95, 'action' => 'delayed_retrieval'],
            ['key' => 'mastery_verification', 'min' => 0.90, 'action' => 'verify_mastery'],
            ['key' => 'independent_application', 'min' => 0.80, 'action' => 'apply_independently'],
            ['key' => 'consolidating', 'min' => 0.60, 'action' => 'continue_practice'],
            ['key' => 'relearn', 'min' => 0.0, 'action' => 'relearn'],
        ],
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
