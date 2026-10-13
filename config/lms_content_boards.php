<?php

/*
|--------------------------------------------------------------------------
| Board profiles for the Content Design System
|--------------------------------------------------------------------------
|
| WHY THIS FILE EXISTS
|
| Generated content has to be board-appropriate, and nothing in the estate
| could say what a board expects. There were two things named "assessment
| blueprint" and neither could do it:
|
|   1. `semantic_intelligence.full_intelegance_json.concepts[].assessment_blueprint`
|      - per-concept suggested questions. Populated on every chapter and
|        grounded in the textbook, which makes it the best raw material we
|        have. But it is CBSE-shaped by construction: its assessment_type
|        values are "Assertion Reason", "HOTS", "Case Study" - CBSE paper
|        furniture - and there is NO board field anywhere in it. You cannot
|        express a Cambridge structured question or an IB criterion in it,
|        and nothing records that it is CBSE in the first place.
|
|   2. The `assessment_blueprint` TABLE (app/Domain/Exam/AssessmentBlueprint.php)
|      - a whole paper's design. Explicitly multi-board: eight boards, the
|        field left free text, and competency bands deliberately "a free list
|        of {code, label, weight} so a school can load CBSE's own bands,
|        Bloom's levels, or a foreign standard set, without any of them being
|        hardcoded". Right instincts - but it designs EXAM PAPERS, not chapter
|        content, and as of this writing the table holds zero rows.
|
| So neither is "better": they answer different questions, and the question
| the content system actually asks - "what does THIS board expect a question
| to look like?" - was asked by neither. That is what this file answers.
|
| WHERE BOARD COMES FROM
| `lms_curriculum.board` + `.framework` is the authority (39 rows today, all
| CBSE, tenant 1 only). Falls back to a board prefix on the standard name
| ("CBSE-6"), the way contentController::storeGammaContent already does. Note
| standards 39/40/42 are named plainly "6"/"7"/"9" with no prefix, so for the
| current target chapters the curriculum row is the only real source.
|
| HONESTY RULE
| Only CBSE is filled in, because CBSE is the only board this estate has
| evidence for. The others are declared with `profile_complete => false` and
| MUST NOT be silently treated as CBSE - a generator that quietly emits
| Assertion-Reason questions for an IB school is worse than one that stops and
| says it does not know. Fill a profile in from that board's own published
| assessment guidance before generating against it.
|
*/

return [

    /*
    | The board used when a chapter resolves to no board at all.
    |
    | Deliberately NOT 'cbse'. Defaulting to a real board would make every
    | unlabelled chapter silently CBSE, which is how the estate ended up with
    | CBSE assumptions baked into a field that never declared them.
    */
    'default' => 'generic',

    'boards' => [

        'generic' => [
            'label' => 'Board-neutral',
            'profile_complete' => true,
            // Safe everywhere: these forms exist under every board this
            // platform serves, under one name or another.
            'question_types' => [
                'mcq' => 'Multiple choice',
                'short_answer' => 'Short answer',
                'long_answer' => 'Long answer',
                'application' => 'Application / problem solving',
            ],
            'cognitive_bands' => ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'],
            'cognitive_band_source' => "Bloom's revised taxonomy",
            'difficulty_labels' => ['easy', 'medium', 'hard'],
            'notes' => 'Used when no board is recorded. Avoids board-specific question forms entirely.',
        ],

        'cbse' => [
            'label' => 'CBSE',
            'profile_complete' => true,
            /*
            | Read off the assessment_blueprint data this estate actually
            | holds, cross-checked against AssessmentBlueprint::QUESTION_TYPES.
            | Assertion-Reason, HOTS and Case Based are the three that make a
            | paper recognisably CBSE.
            */
            'question_types' => [
                'mcq' => 'Multiple choice',
                'assertion_reason' => 'Assertion-Reason',
                'very_short_answer' => 'Very short answer',
                'short_answer' => 'Short answer',
                'long_answer' => 'Long answer',
                'case_based' => 'Case based / source based',
                'hots' => 'Higher order thinking skills',
                'competency' => 'Competency based',
            ],
            'cognitive_bands' => ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'],
            'cognitive_band_source' => "Bloom's revised taxonomy, as used in CBSE competency framing",
            'difficulty_labels' => ['easy', 'medium', 'hard'],
            'frameworks' => ['NEP 2020', 'NCF-SE 2023', 'NCERT Learning Outcomes at the Elementary Stage'],
            'notes' => 'The only board with real data in this estate. The per-concept assessment_blueprint in semantic_intelligence is already shaped to this profile.',
        ],

        /*
        | Declared so the system can NAME a board it cannot yet generate for,
        | instead of falling through to CBSE. Each needs its question forms and
        | cognitive bands filled in from that board's published assessment
        | guidance - not guessed.
        */
        'icse' => [
            'label' => 'ICSE / CISCE',
            'profile_complete' => false,
            'question_types' => [],
            'cognitive_bands' => [],
            'difficulty_labels' => ['easy', 'medium', 'hard'],
            'notes' => 'Not yet profiled. Needs CISCE assessment guidance.',
        ],

        'cambridge' => [
            'label' => 'Cambridge (CAIE)',
            'profile_complete' => false,
            'question_types' => [],
            'cognitive_bands' => [],
            'difficulty_labels' => ['easy', 'medium', 'hard'],
            'notes' => 'Not yet profiled. Tenant 341 carries Cambridge chapters (32, Mathematics) with no curriculum rows.',
        ],

        'ib' => [
            'label' => 'International Baccalaureate',
            'profile_complete' => false,
            'question_types' => [],
            'cognitive_bands' => [],
            'difficulty_labels' => ['easy', 'medium', 'hard'],
            'notes' => 'Not yet profiled. IB assesses against published criteria rather than a question-type list, so this profile will need a different shape.',
        ],

        'state_board' => [
            'label' => 'State Board',
            'profile_complete' => false,
            'question_types' => [],
            'cognitive_bands' => [],
            'difficulty_labels' => ['easy', 'medium', 'hard'],
            'notes' => 'Not yet profiled. Varies by state; likely needs one profile per state rather than one shared entry.',
        ],
    ],

    /*
    | Which board a tenant's content library belongs to.
    |
    | In this estate a content-owning tenant IS a board: sub_institute 1 is the
    | platform library every CBSE school consumes (its own name is "Triz
    | International School", which is why the board cannot be read off the
    | name), and 341 is the Cambridge library.
    |
    | This mapping exists nowhere in the database. `institute_detail` carries no
    | board, `lms_data_neo4j` names tenants but not 341, and
    | `pal_curriculum_versions` - which has a board column - is empty. Without
    | this map a Cambridge chapter resolves to board-neutral, which is safe but
    | wrong: we DO know what it is.
    |
    | Consulted after lms_curriculum and the standard-name prefix, because both
    | of those are per-standard/subject declarations and therefore more specific
    | than a whole-tenant default.
    |
    | Add a row when a board's content library is onboarded. A tenant that is a
    | single school rather than a content library does not belong here - it
    | consumes a library and inherits that library's board.
    */
    'tenant_boards' => [
        1 => 'cbse',
        341 => 'cambridge',
    ],

    /*
    | Maps what is actually stored in lms_curriculum.board (and board prefixes
    | on standard names) onto the keys above. Case-insensitive on lookup.
    |
    | This is the same reconciliation job config/pal_content_model.php does for
    | Bloom and difficulty - see docs/content-design-system/vocabulary.md.
    */
    'aliases' => [
        'cbse' => 'cbse',
        'central board of secondary education' => 'cbse',
        'ncert' => 'cbse',
        'icse' => 'icse',
        'cisce' => 'icse',
        'cambridge' => 'cambridge',
        'caie' => 'cambridge',
        'cie' => 'cambridge',
        'ib' => 'ib',
        'ibdp' => 'ib',
        'myp' => 'ib',
        'state board' => 'state_board',
        'state' => 'state_board',
        'gseb' => 'state_board',
        'delhi doe' => 'state_board',
    ],
];
