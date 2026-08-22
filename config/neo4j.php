<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Neo4j Database Connection
    |--------------------------------------------------------------------------
    */
    
    'host' => env('NEO4J_HOST', 'localhost'),
    'port' => env('NEO4J_PORT', 7687),
    'username' => env('NEO4J_USERNAME', 'neo4j'),
    'password' => env('NEO4J_PASSWORD', 'neo4j'),
    'uri' => env('NEO4J_URI', 'bolt://localhost:7687'),

    /*
    |--------------------------------------------------------------------------
    | Application writes to Neo4j  (decision RESIDUAL-WRITERS, 2026-08-10)
    |--------------------------------------------------------------------------
    |
    | Master switch for every application-path write to the graph. Defaults to
    | FALSE so the migration rebuild cannot be polluted by live traffic: three
    | routes still write to Neo4j (POST /lms/pal, POST /assessment_question/store,
    | POST /neo4j/assessment) and any of them firing during the rebuild seeds
    | nodes under the old key convention — that is defect D2, reintroduced.
    |
    | Reads are NOT affected. The artisan loader (neo4j:load) bypasses this flag
    | deliberately; it is the one component that is supposed to write.
    |
    | Turn back on at Phase 15 (live sync), not before.
    |
    */
    'writes_enabled' => filter_var(env('NEO4J_WRITES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Live application -> graph sync  (Phase 15)
    |--------------------------------------------------------------------------
    |
    | Deliberately a SEPARATE switch from `writes_enabled` above. That flag
    | gates the three *legacy* writer routes (POST /lms/pal,
    | POST /assessment_question/store, POST /neo4j/assessment), which still key
    | nodes under the pre-migration convention — turning it on reintroduces
    | defect D2. This flag gates only the App\Services\Graph projections, which
    | write the keys the live graph actually uses.
    |
    | Leave `writes_enabled` false and this true: new application data reaches
    | the graph, the legacy writers stay muted.
    |
    */
    'sync_enabled' => filter_var(env('NEO4J_SYNC_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | When a projection throws (Neo4j down, bolt timeout), record the entity in
    | the `neo4j_sync_outbox` table so `php artisan neo4j:sync-drain` can retry.
    | The originating HTTP request NEVER fails because of a graph error.
    */
    'outbox_enabled' => filter_var(env('NEO4J_OUTBOX_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Max retries the drain command will make before marking a row 'failed'.
    */
    'outbox_max_attempts' => (int) env('NEO4J_OUTBOX_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | K12 entity -> graph shape  (live sync projections)
    |--------------------------------------------------------------------------
    |
    | Read by App\Services\Graph\ProjectionRegistry and by the sync-trigger
    | migration. Kept here rather than in a config file of its own so there is
    | one place that describes the Neo4j integration.
    |
    */
    'projections' => [

    /**
     * -----------------------------------------------------------------------------
     * K12 entity -> graph shape, as data.
     * -----------------------------------------------------------------------------
     *
     * Each entry maps one MariaDB table onto one Neo4j label plus the edges that
     * table's own foreign keys support. `App\Services\Graph\TableGraphProjection`
     * executes them; `App\Services\Graph\ProjectionRegistry` resolves them.
     *
     * -----------------------------------------------------------------------------
     * THESE SPECS DESCRIBE THE COLUMNS THAT EXIST, NOT THE ONES k12_cypher.txt WANTS
     * -----------------------------------------------------------------------------
     * The ingest script was written against a schema this database does not have.
     * Verified column-by-column on 2026-08-21 against `vivek_erp`; every divergence
     * below is deliberate and is noted at the entity it affects. The headline ones:
     *
     *   - `lms_concept` has NO `lesson_id`, `difficulty_level`, `bloom_level` or
     *     `pedagogy_tag`. The script's `(:Lesson)-[:COVERS]->(:Concept)` therefore
     *     cannot be built, which is why the live graph has 9 COVERS edges against
     *     1,372 `(:Chapter)-[:HAS_CONCEPT]->(:Concept)`. Chapter is the join that
     *     works, so that is the edge these specs emit.
     *   - `question_paper` has NO `concept_id`, so `(:Assessment)-[:ASSESSES]->
     *     (:Concept)` has no source. `lms_question_master.concept_id` does exist,
     *     and the live graph carries 26 `(:Question)-[:ASSESSES]->(:Concept)`, so
     *     ASSESSES hangs off the question.
     *   - `lms_lesson_plan` has NO `lesson_title`, `teacher_id`, `pedagogy_tag` or
     *     `status`. The script's displayLabel for :Lesson referenced
     *     `row.curriculum_name`, a column of a different table entirely, which is
     *     why every live :Lesson node has no displayLabel at all. Fixed here.
     *   - `lms_misconceptions`, `lms_learning_objectives`, `lms_competency_standards`,
     *     `lms_chapter_standard_map` and `lms_assessment_typology` DO NOT EXIST in
     *     this database, so :Misconception, :LearningObjects, :CompetencyStandards,
     *     :ChapterStandardMap and :AssessmentTypology have no live source and are
     *     deliberately absent. (Misconceptions do exist, under `pal_misconceptions`
     *     / `pal_misconception_library`, but on the PAL model rather than the K12
     *     one — they are synced by the PAL coherence pass, not by this outbox.)
     *   - `suggested_content` exists but has none of the columns the script's
     *     :LearningContent block reads (no `concept_id`, `misconception_id`,
     *     `title`, `modality`, `bloom_level`). Projecting it would produce nodes
     *     made entirely of nulls, so it is left out.
     *
     * displayLabel prefixes match the LIVE nodes exactly — note `chapter:` is
     * lower-case where every other label is capitalised. That inconsistency is
     * reproduced on purpose: changing it would rewrite the label on all 7,626
     * existing :Chapter nodes for no gain.
     *
     * Spec keys:
     *   label          Neo4j label; its unique key comes from GraphSchema.
     *   key_column     column holding the node key, when it is NOT the row's PK.
     *   properties     nodeProperty => column.
     *   casts          force a property's type; `_id`-suffixed names and the unique
     *                  key are cast to int automatically.
     *   display_label  ['prefix' => ..., 'column' => ...].
     *   relationships  ['type' => ..., 'from' => [label, column], 'to' => [label, column]]
     *                  where `id` as the column means this row's node key, and
     *                  'list' => 'to'|'from' marks a comma-separated id column.
     */
        /*
        |--------------------------------------------------------------------------
        | Tables that carry a live sync trigger
        |--------------------------------------------------------------------------
        |
        | The trigger migration reads this list, so a table is only ever captured if
        | something here knows how to project it. `tbluser` is deliberately NOT in
        | it: it is the hottest table in the schema (every login writes last_login),
        | it holds all 4,506 users rather than the 118 that are teachers, and no K12
        | edge depends on it — `lms_lesson_plan` has no teacher_id in this database.
        | Its spec is still defined below so backfill and reconcile can reach it on
        | demand.
        |
        */
        'triggered' => [
            'tblstudent',
            'tblstudent_enrollment',
            'standard',
            'sub_std_map',
            'chapter_master',
            'lms_concept',
            'lms_lesson_plan',
            'lms_curriculum',
            'lms_units',
            'question_paper',
            'lms_question_master',
            'lms_online_exam',
        ],

        'entities' => [

            // ---------------------------------------------------------------
            // Standard  (1,000 rows)
            // ---------------------------------------------------------------
            'standard' => [
                'label' => 'Standard',
                'properties' => [
                    'standard_id'      => 'id',
                    'name'             => 'name',
                    'short_name'       => 'short_name',
                    'grade_id'         => 'grade_id',
                    'sub_institute_id' => 'sub_institute_id',
                ],
                'display_label' => ['prefix' => 'Standard:', 'column' => 'name'],
            ],

            // ---------------------------------------------------------------
            // Subject  (6,592 mapping rows -> 1,193 subjects)
            //
            // Keyed on `subject_id`, not on the row id: every subject_id foreign
            // key in the schema points at that value. See TableGraphProjection's
            // note for the measurements that settle it.
            // ---------------------------------------------------------------
            'sub_std_map' => [
                'label'      => 'Subject',
                'key_column' => 'subject_id',
                'properties' => [
                    'subject_id'       => 'subject_id',
                    'standard_id'      => 'standard_id',
                    'display_name'     => 'display_name',
                    'sort_order'       => 'sort_order',
                    'sub_institute_id' => 'sub_institute_id',
                ],
                'casts' => ['sort_order' => 'int'],
                'display_label' => ['prefix' => 'Subject:', 'column' => 'display_name'],
                'relationships' => [
                    ['type' => 'HAS_SUBJECT', 'from' => ['Standard', 'standard_id'], 'to' => ['Subject', 'id']],
                ],
            ],

            // ---------------------------------------------------------------
            // Chapter  (84 rows)
            // ---------------------------------------------------------------
            'chapter_master' => [
                'label' => 'Chapter',
                'properties' => [
                    'subject_id'       => 'subject_id',
                    'standard_id'      => 'standard_id',
                    'unit_id'          => 'unit_id',
                    'chapter_name'     => 'chapter_name',
                    'key_concepts'     => 'key_concepts',
                    'sort_order'       => 'sort_order',
                    'syear'            => 'syear',
                    'sub_institute_id' => 'sub_institute_id',
                ],
                'casts' => ['sort_order' => 'int', 'syear' => 'int', 'key_concepts' => 'string'],
                'display_label' => ['prefix' => 'chapter:', 'column' => 'chapter_name'],
                'relationships' => [
                    ['type' => 'HAS_CHAPTER', 'from' => ['Subject', 'subject_id'], 'to' => ['Chapter', 'id']],
                    ['type' => 'HAS_CHAPTER', 'from' => ['Unit', 'unit_id'],       'to' => ['Chapter', 'id']],
                ],
            ],

            // ---------------------------------------------------------------
            // Concept  (1,774 rows)
            //
            // HAS_CONCEPT, not the script's Lesson-COVERS-Concept: lms_concept
            // has chapter_id and no lesson_id.
            // ---------------------------------------------------------------
            'lms_concept' => [
                'label' => 'Concept',
                'properties' => [
                    'id'                        => 'id',
                    'name'                      => 'name',
                    'subject_id'                => 'subject_id',
                    'standard_id'               => 'standard_id',
                    'chapter_id'                => 'chapter_id',
                    'topic_id'                  => 'topic_id',
                    'mastery_threshold'         => 'mastery_threshold',
                    'estimated_mastery_minutes' => 'estimated_mastery_minutes',
                    'syear'                     => 'syear',
                    'sub_institute_id'          => 'sub_institute_id',
                ],
                'casts' => [
                    'id'                        => 'int',
                    'syear'                     => 'int',
                    'estimated_mastery_minutes' => 'int',
                    'mastery_threshold'         => 'string',
                ],
                'display_label' => ['prefix' => 'Concept:', 'column' => 'name'],
                'relationships' => [
                    ['type' => 'HAS_CONCEPT', 'from' => ['Chapter', 'chapter_id'], 'to' => ['Concept', 'id']],
                ],
            ],

            // ---------------------------------------------------------------
            // Lesson  (1,192 rows)
            //
            // Property set rebuilt from the columns this table really has. Every
            // live :Lesson currently has an ABSENT displayLabel because the ingest
            // script read `row.curriculum_name` here; this gives them one.
            // ---------------------------------------------------------------
            'lms_lesson_plan' => [
                'label' => 'Lesson',
                'properties' => [
                    'standard_id'        => 'standard_id',
                    'subject_id'         => 'subject_id',
                    'chapter_id'         => 'chapter_id',
                    'topic_id'           => 'topic_id',
                    'lesson_plan_number' => 'lesson_plan_number',
                    'learning_objective' => 'learningobjective',
                    'period_number'      => 'numberofperiod',
                    'syear'              => 'syear',
                    'sub_institute_id'   => 'sub_institute_id',
                ],
                'casts' => [
                    'syear'              => 'int',
                    'lesson_plan_number' => 'string',
                    'learning_objective' => 'string',
                    'period_number'      => 'string',
                ],
                'display_label' => ['prefix' => 'Lesson:', 'column' => 'lesson_plan_number'],
                'relationships' => [
                    ['type' => 'HAS_LESSON', 'from' => ['Chapter', 'chapter_id'], 'to' => ['Lesson', 'id']],
                ],
            ],

            // ---------------------------------------------------------------
            // Curriculum  (10 rows)
            // ---------------------------------------------------------------
            'lms_curriculum' => [
                'label' => 'Curriculum',
                'properties' => [
                    'curriculum_name'  => 'curriculum_name',
                    'board'            => 'board',
                    'framework'        => 'framework',
                    'total_marks'      => 'total_marks',
                    'internal_marks'   => 'internal_marks',
                    'subject_id'       => 'subject_id',
                    'standard_id'      => 'standard_id',
                    'syear'            => 'syear',
                    'status'           => 'status',
                    'sub_institute_id' => 'sub_institute_id',
                ],
                'casts' => [
                    'total_marks'    => 'int',
                    'internal_marks' => 'int',
                    'syear'          => 'int',
                    'status'         => 'string',
                    'board'          => 'string',
                    'framework'      => 'string',
                ],
                'display_label' => ['prefix' => 'Curriculum:', 'column' => 'curriculum_name'],
                'relationships' => [
                    ['type' => 'INCLUDES',              'from' => ['Curriculum', 'id'],         'to' => ['Subject', 'subject_id']],
                    ['type' => 'BELONGS_TO_CURRICULUM', 'from' => ['Subject', 'subject_id'],    'to' => ['Curriculum', 'id']],
                ],
            ],

            // ---------------------------------------------------------------
            // Unit  (60 rows)
            // ---------------------------------------------------------------
            'lms_units' => [
                'label' => 'Unit',
                'properties' => [
                    'curriculum_id'   => 'curriculum_id',
                    'unit_number'     => 'unit_number',
                    'name'            => 'name',
                    'total_marks'     => 'total_marks',
                    'planned_periods' => 'planned_periods',
                ],
                'casts' => ['unit_number' => 'int', 'total_marks' => 'int', 'planned_periods' => 'int'],
                'display_label' => ['prefix' => 'Unit:', 'column' => 'name'],
                'relationships' => [
                    ['type' => 'HAS_UNIT', 'from' => ['Curriculum', 'curriculum_id'], 'to' => ['Unit', 'id']],
                ],
            ],

            // ---------------------------------------------------------------
            // Assessment  (5,440 rows)
            //
            // `question_ids` is a comma-separated list, read exactly as the ingest
            // script's `UNWIND split(row.question_ids, ',')` does.
            // ---------------------------------------------------------------
            'question_paper' => [
                'label' => 'Assessment',
                'properties' => [
                    'paper_name'       => 'paper_name',
                    'grade_id'         => 'grade_id',
                    'standard_id'      => 'standard_id',
                    'subject_id'       => 'subject_id',
                    'total_marks'      => 'total_marks',
                    'total_ques'       => 'total_ques',
                    'question_ids'     => 'question_ids',
                    'exam_type'        => 'exam_type',
                    'syear'            => 'syear',
                    'sub_institute_id' => 'sub_institute_id',
                ],
                'casts' => [
                    'total_marks'  => 'int',
                    'total_ques'   => 'int',
                    'syear'        => 'int',
                    'question_ids' => 'string',
                    'exam_type'    => 'string',
                ],
                'display_label' => ['prefix' => 'Assessment:', 'column' => 'paper_name'],
                'relationships' => [
                    ['type' => 'HAS_ASSESSMENT', 'from' => ['Subject', 'subject_id'], 'to' => ['Assessment', 'id']],
                    ['type' => 'HAS_QUESTION',   'from' => ['Assessment', 'id'],      'to' => ['Question', 'question_ids'], 'list' => 'to'],
                ],
            ],

            // ---------------------------------------------------------------
            // Question  (64,594 rows)
            // ---------------------------------------------------------------
            'lms_question_master' => [
                'label' => 'Question',
                'properties' => [
                    'question_type_id' => 'question_type_id',
                    'concept_id'       => 'concept_id',
                    'standard_id'      => 'standard_id',
                    'subject_id'       => 'subject_id',
                    'chapter_id'       => 'chapter_id',
                    'question_title'   => 'question_title',
                    'points'           => 'points',
                    'sub_institute_id' => 'sub_institute_id',
                ],
                'casts' => ['points' => 'int', 'question_title' => 'string'],
                'display_label' => ['prefix' => 'Question:', 'column' => 'question_title'],
                'relationships' => [
                    ['type' => 'BELONGS_TO', 'from' => ['Question', 'id'], 'to' => ['Chapter', 'chapter_id']],
                    ['type' => 'ASSESSES',   'from' => ['Question', 'id'], 'to' => ['Concept', 'concept_id']],
                ],
            ],

            // ---------------------------------------------------------------
            // Teacher  (spec only — no trigger, see `triggered` above)
            // ---------------------------------------------------------------
            'tbluser' => [
                'label' => 'Teacher',
                'properties' => [
                    'user_profile_id'  => 'user_profile_id',
                    'subject_ids'      => 'subject_ids',
                    'sub_institute_id' => 'sub_institute_id',
                ],
                'casts' => ['subject_ids' => 'string'],
                'display_label' => ['prefix' => 'Teacher:', 'column' => 'user_profile_id'],
            ],
        ],
    ],
];
