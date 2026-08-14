<?php

/*
|--------------------------------------------------------------------------
| Neo4j graph registry — generated Phase 2, 2026-08-10
|--------------------------------------------------------------------------
|
| One entry per table in `vivek_erp` (488). Generated from the approved Phase 1
| classification in docs/neo4j-table-classification.md — edit that document and
| regenerate rather than hand-editing decisions here.
|
| PROJECTION LAW (docs/neo4j-full-erp-graph-master-prompt.md):
|   L1 uid = "<Label>:<sub_institute_id>:<syear>:<pk>" — the ONLY MERGE key
|   L2 every node carries a tenant; no edge crosses tenants
|   L3 ledgers are edges, never nodes
|   L4 aggregate in SQL before projecting
|   L7 MariaDB is the source of truth
|
| tenant.mode:  column  read sub_institute_id from this column
|               derive  join to a parent table that has it
|               self    the table's PK *is* the institute (school_setup)
|               global  reference data shared by all tenants -> sub_institute_id 0
|                       + scope='global'. This is the ONE documented exception to L2;
|                       neo4j:verify exempts declared-global labels and nothing else.
|
| Approved decisions carried in here (2026-08-10):
|   CHAPTER-SOURCE seed :Chapter from docs/neo4j-backup-2026-08-10/nodes_Chapter.csv
|   CONCEPT-LINK   MASTERS aggregates to :Chapter, not :Concept
|   FEES-MODEL     LIABLE_FOR from fees_breakoff_other only
|   JOBROLE-KEY    resolve name strings to ids in SQL at export; drop + count misses
|
*/

return [

    'meta' => [
        'generated'      => '2026-08-10',
        'source_db'      => 'vivek_erp',
        'classification' => 'docs/neo4j-table-classification.md',
        'table_count'    => 488,
        'neo4j_version'  => '4.4.40 Community',
    ],

    /*
     | The rescue export is the ONLY surviving record of 5,521 :Chapter nodes and the
     | 86,265 question->chapter edges that Phase 3 deleted; MariaDB has 99 chapters.
     | It lives OUTSIDE the repo because the in-repo copy was silently deleted four
     | times on 2026-08-10 while every other untracked file survived. Point
     | NEO4J_RESCUE_DIR at wherever you keep it.
     */
    'rescue' => [
        'dir'   => env('NEO4J_RESCUE_DIR', base_path('docs/neo4j-backup-2026-08-10')),
        'files' => [
            'Chapter' => [
                'csv'    => 'nodes_Chapter.csv',
                'id_col' => 'chId',
                'source' => 'graph-rescue-2026-08-10',
                'lines'  => 5537,
                'md5'    => '3f81662cf695b1e8',
                'note'   => '5,521 chapters absent from MariaDB; repairs 70.9% of the F1 break',
            ],
            'BELONGS_TO' => [
                'csv'   => 'rels_BELONGS_TO.csv',
                'lines' => 86266,
                'md5'   => '7866123c886cd9d9',
                'note'  => 'question->chapter mapping; MariaDB lost 95.3% of these refs',
            ],
        ],
    ],

    /*
     | Hierarchy edges derived from FK COLUMNS ON ENTITY TABLES, not from junction
     | tables. The Phase 1 classification only modelled junction tables as EDGE
     | sources, which left every Foundation dimension orphaned — Phase 4's verify
     | gate (G8) caught it. `fk` is a column on `table`; the parent uid is built from
     | that value. A row whose parent does not exist is skipped and counted, never
     | MERGEd into existence (that is how defect D9 happened).
     */
    'hierarchy' => [
        ['table' => 'academic_section'      , 'fk' => 'sub_institute_id'  , 'parent' => 'Institute' , 'rel' => 'HAS_SECTION'         , 'child' => 'AcademicSection'   , 'soft' => false],
        ['table' => 'academic_year'         , 'fk' => 'sub_institute_id'  , 'parent' => 'Institute' , 'rel' => 'HAS_ACADEMIC_YEAR'   , 'child' => 'AcademicYear'      , 'soft' => false],
        ['table' => 'standard'              , 'fk' => 'sub_institute_id'  , 'parent' => 'Institute' , 'rel' => 'HAS_STANDARD'        , 'child' => 'Standard'          , 'soft' => false],
        ['table' => 'division'              , 'fk' => 'sub_institute_id'  , 'parent' => 'Institute' , 'rel' => 'HAS_DIVISION_POOL'   , 'child' => 'Division'          , 'soft' => false],
        ['table' => 'subject'               , 'fk' => 'sub_institute_id'  , 'parent' => 'Institute' , 'rel' => 'OFFERS_SUBJECT'      , 'child' => 'Subject'           , 'soft' => false],
        ['table' => 'school_sections'       , 'fk' => 'school_id'         , 'parent' => 'Institute' , 'rel' => 'HAS_SCHOOL_SECTION'  , 'child' => 'SchoolSection'     , 'soft' => false],
        ['table' => 'hrms_departments'      , 'fk' => 'sub_institute_id'  , 'parent' => 'Institute' , 'rel' => 'HAS_DEPARTMENT'      , 'child' => 'Department'        , 'soft' => false],
        ['table' => 'tbluserprofilemaster'  , 'fk' => 'sub_institute_id'  , 'parent' => 'Institute' , 'rel' => 'HAS_ROLE'            , 'child' => 'Role'              , 'soft' => false],
        ['table' => 'batch'                 , 'fk' => 'division_id'       , 'parent' => 'Division'  , 'rel' => 'HAS_BATCH'           , 'child' => 'Batch'             , 'soft' => false],
        ['table' => 'lms_units'             , 'fk' => 'curriculum_id'     , 'parent' => 'Curriculum', 'rel' => 'HAS_UNIT'            , 'child' => 'Unit'              , 'soft' => false],
        ['table' => 'chapter_master'        , 'fk' => 'unit_id'           , 'parent' => 'Unit'      , 'rel' => 'HAS_CHAPTER'         , 'child' => 'Chapter'           , 'soft' => false],
        ['table' => 'chapter_master'        , 'fk' => 'subject_id'        , 'parent' => 'Subject'   , 'rel' => 'COVERS_CHAPTER'      , 'child' => 'Chapter'           , 'soft' => false],
        ['table' => 'topic_master'          , 'fk' => 'chapter_id'        , 'parent' => 'Chapter'   , 'rel' => 'HAS_TOPIC'           , 'child' => 'Topic'             , 'soft' => true],
        ['table' => 'lms_concept'           , 'fk' => 'chapter_id'        , 'parent' => 'Chapter'   , 'rel' => 'HAS_CONCEPT'         , 'child' => 'Concept'           , 'soft' => true],
        ['table' => 'content_master'        , 'fk' => 'chapter_id'        , 'parent' => 'Chapter'   , 'rel' => 'HAS_CONTENT'         , 'child' => 'Content'           , 'soft' => true],
        ['table' => 'lms_lesson_plan'       , 'fk' => 'chapter_id'        , 'parent' => 'Chapter'   , 'rel' => 'HAS_LESSON'          , 'child' => 'Lesson'            , 'soft' => true],
        ['table' => 'lms_learning_outcomes' , 'fk' => 'chapter_id'        , 'parent' => 'Chapter'   , 'rel' => 'HAS_LEARNING_OUTCOME', 'child' => 'LearningObjective' , 'soft' => true],
        ['table' => 'lms_mapping_type'      , 'fk' => 'parent_id'         , 'parent' => 'MappingType', 'rel' => 'PARENT_OF'           , 'child' => 'MappingType'       , 'soft' => false],
        ['table' => 'document_extractions'  , 'fk' => 'subject_id'        , 'parent' => 'Subject'   , 'rel' => 'HAS_EXTRACTION'      , 'child' => 'Extraction'        , 'soft' => false],
        ['table' => 'lo_master'             , 'fk' => 'subject_id'        , 'parent' => 'Subject'   , 'rel' => 'HAS_LO_CATEGORY'     , 'child' => 'LOCategory'        , 'soft' => false],
        ['table' => 'lo_category'           , 'fk' => 'subject_id'        , 'parent' => 'Subject'   , 'rel' => 'HAS_LO_CATEGORY_ALT' , 'child' => 'LOCategoryAlt'     , 'soft' => false],
        ['table' => 'lo_indicator'          , 'fk' => 'lomaster_id'       , 'parent' => 'LOCategory', 'rel' => 'HAS_INDICATOR'       , 'child' => 'LearningOutcome'   , 'soft' => false],
        ['table' => 'pal_misconceptions'    , 'fk' => 'concept_id'        , 'parent' => 'Concept'   , 'rel' => 'HAS_MISCONCEPTION'   , 'child' => 'Misconception'     , 'soft' => false],
        ['table' => 'lms_mapping_type'      , 'fk' => 'chapter_id'        , 'parent' => 'Chapter'   , 'rel' => 'SCOPED_TO'           , 'child' => 'MappingType'       , 'soft' => true, 'by_id' => true],
        ['table' => 'lms_mapping_type'      , 'fk' => 'topic_id'          , 'parent' => 'Topic'     , 'rel' => 'SCOPED_TO'           , 'child' => 'MappingType'       , 'soft' => false, 'by_id' => true],
    ],

    'tables' => [

        '10_05_question_master' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 9,
            'reason' => '9 rows, 0 code refs — dated backup table',
        ],

        'academic_section' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 237,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'AcademicSection',
            'uid' => 'AcademicSection:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'academic_year' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 522,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'AcademicYear',
            'uid' => 'AcademicYear:{tenant}:{syear}:{pk}',
            'note' => 'Referenced 105× in code; every time-scoped edge hangs off it',
        ],

        'access_log' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Audit log (empty)',
        ],

        'access_log_route' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1421,
            'reason' => 'Audit log',
        ],

        'activity_log' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Audit log (empty)',
        ],

        'admission_age_validation' => [
            'module' => 'people',
            'domain' => 'Admission',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6,
            'reason' => 'Validation config',
        ],

        'admission_category_master' => [
            'module' => 'people',
            'domain' => 'Admission',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 6,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'AdmissionCategory',
            'uid' => 'AdmissionCategory:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'admission_enquiry' => [
            'module' => 'people',
            'domain' => 'Admission',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 12,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Enquiry',
            'uid' => 'Enquiry:{tenant}:{syear}:{pk}',
            'note' => '12 rows, q82',
        ],

        'admission_form' => [
            'module' => 'people',
            'domain' => 'Admission',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'admission_registration' => [
            'module' => 'people',
            'domain' => 'Admission',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 5,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Applicant',
            'uid' => 'Applicant:{tenant}:{syear}:{pk}',
            'note' => '5 rows — legacy v0',
        ],

        'admission_registration_v1' => [
            'module' => 'people',
            'domain' => 'Admission',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 836,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Applicant',
            'uid' => 'Applicant:{tenant}:{syear}:{pk}',
            'note' => '836 rows',
        ],

        'ai_api_keys' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4,
            'reason' => 'Secrets',
        ],

        'ai_daily_used_api' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Usage counter',
        ],

        'ai_interaction_logs' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 53,
            'reason' => 'LLM call log',
        ],

        'ai_sops' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 7,
            'reason' => 'Prompt config',
        ],

        'announcement' => [
            'module' => 'operations',
            'domain' => 'FrontDesk',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 33,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Announcement',
            'uid' => 'Announcement:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'answer_master' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 6,
            'tier' => 'A',
            'decision' => 'PROP',
            'rows_at_classification' => 444838,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'option_count + correct_answer_id on :Question',
            'note' => '**Question OPTIONS, not student answers** (3.88 per question). Master prompt misread this. See F4',
        ],

        'api_details' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Config',
        ],

        'application_forms' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'app_language' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 189,
            'reason' => 'i18n strings',
        ],

        'app_notification' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 199,
            'reason' => 'Notification log',
        ],

        'app_notification_teacher' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'attendance_json_result' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 30,
            'reason' => 'JSON cache',
        ],

        'attendance_student' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 18,
            'reason' => '18 rows, q26',
        ],

        'bank_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 176,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'label' => 'Bank',
            'uid' => 'Bank:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'batch' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 3000,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Batch',
            'uid' => 'Batch:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'biomatrix' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty biometric config',
        ],

        'blogs' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 11,
            'reason' => '11 rows, CMS content',
        ],

        'blood_group' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 9,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'BloodGroup',
            'uid' => 'BloodGroup:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'book_list' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 559,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'TextBook',
            'uid' => 'TextBook:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'calendar_events' => [
            'module' => 'platform',
            'domain' => 'Calendar',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 3941,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'CalendarEvent',
            'uid' => 'CalendarEvent:{tenant}:{syear}:{pk}',
            'note' => '3,941 rows',
        ],

        'cast' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 15,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Caste',
            'uid' => 'Caste:{tenant}:{syear}:{pk}',
            'note' => '`cast` and `caste` are two live tables — see finding F9',
        ],

        'caste' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 16,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Caste',
            'uid' => 'Caste:{tenant}:{syear}:{pk}',
            'note' => 'Duplicate of `cast`; pick one at Phase 2',
        ],

        'certificate_history' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Issue log',
        ],

        'chapter_master' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 99,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Chapter',
            'uid' => 'Chapter:{tenant}:{syear}:{pk}',
            'note' => '**99 rows only, ids 1012-8677.** Gen-2 extraction pipeline. See finding F1',
        ],

        'chapter_topic_master' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 330,
            'reason' => '330 rows, **0 code references**, chapter_ids in the dead id space. Probably Gen-1 residue',
        ],

        'circular' => [
            'module' => 'operations',
            'domain' => 'FrontDesk',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 1,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Circular',
            'uid' => 'Circular:{tenant}:{syear}:{pk}',
            'note' => '1 row, q34',
        ],

        'circular_type' => [
            'module' => 'operations',
            'domain' => 'FrontDesk',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 2,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'CircularType',
            'uid' => 'CircularType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'classwork_attachment' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 5401,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'attachment_count on :Lesson',
            'note' => '5,401 rows — file metadata only',
        ],

        'class_teacher' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 10,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 2924,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'CLASS_TEACHER_OF',
            'from' => [
                'label' => 'Staff',
                'key' => 'teacher_id',
            ],
            'to' => [
                'label' => 'Division',
                'key' => 'division_id',
            ],
            'note' => '2,924 rows',
        ],

        'college_timetable' => [
            'module' => 'hr',
            'domain' => 'Timetable',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'complaint' => [
            'module' => 'operations',
            'domain' => 'FrontDesk',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 34,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'SUB_INSTITUTE_ID',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'label' => 'Complaint',
            'uid' => 'Complaint:{tenant}:{syear}:{pk}',
            'note' => 'Uses UPPERCASE SUB_INSTITUTE_ID',
        ],

        'complaint_status' => [
            'module' => 'operations',
            'domain' => 'FrontDesk',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'PROP',
            'rows_at_classification' => 4,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'status lookup',
            'note' => '',
        ],

        'consent_master' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 18,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'ConsentType',
            'uid' => 'ConsentType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'contents' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 48,
            'reason' => '48 rows, q31 — distinct from content_master; needs a human call',
        ],

        'content_mapping_type' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 1909,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'content_id',
                'table' => 'content_master',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'content_id',
                'table' => 'content_master',
                'key' => 'id',
            ],
            'rel' => 'TAGGED_AS',
            'from' => [
                'label' => 'Content',
                'key' => 'content_id',
            ],
            'to' => [
                'label' => 'MappingType',
                'key' => 'mapping_type_id',
            ],
            'note' => 'No tenancy — derive via content_id',
        ],

        'content_master' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 31362,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Content',
            'uid' => 'Content:{tenant}:{syear}:{pk}',
            'note' => '99.0% of chapter_id values dangle — see F1',
        ],

        'counselling_answer_master' => [
            'module' => 'assessment',
            'domain' => 'Counselling',
            'phase' => 6,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 40,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :Question',
            'note' => '',
        ],

        'counselling_course' => [
            'module' => 'assessment',
            'domain' => 'Counselling',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 5,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Course',
            'uid' => 'Course:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'counselling_online_exam' => [
            'module' => 'assessment',
            'domain' => 'Counselling',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 35,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Result',
            'uid' => 'Result:{tenant}:{syear}:{pk}',
            'note' => '35 rows',
        ],

        'counselling_online_exam_answer' => [
            'module' => 'assessment',
            'domain' => 'Counselling',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 45,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'online_exam_id',
                'table' => 'counselling_online_exam',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'online_exam_id',
                'table' => 'counselling_online_exam',
                'key' => 'id',
            ],
            'rel' => 'MASTERS',
            'from' => [
                'label' => 'Student',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Chapter',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '45 rows. Aggregates to :Chapter per CONCEPT-LINK',
        ],

        'counselling_question_mapping' => [
            'module' => 'assessment',
            'domain' => 'Counselling',
            'phase' => 6,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 13,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'questionmaster_id',
                'table' => 'counselling_question_master',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'questionmaster_id',
                'table' => 'counselling_question_master',
                'key' => 'id',
            ],
            'rel' => 'TAGGED_AS',
            'from' => [
                'label' => 'Question',
                'key' => 'questionmaster_id',
            ],
            'to' => [
                'label' => 'MappingType',
                'key' => 'mapping_type_id',
            ],
            'note' => '',
        ],

        'counselling_question_master' => [
            'module' => 'assessment',
            'domain' => 'Counselling',
            'phase' => 6,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 11,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Question',
            'uid' => 'Question:{tenant}:{syear}:{pk}',
            'note' => 'Separate counselling question bank',
        ],

        'create_timetable' => [
            'module' => 'hr',
            'domain' => 'Timetable',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 54,
            'reason' => '54 rows, generator config',
        ],

        'csv_data' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 280,
            'reason' => 'Import staging',
        ],

        'custom_module_tables' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 8,
            'reason' => 'Dynamic-table metadata',
        ],

        'custom_module_table_columns' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 49,
            'reason' => 'Dynamic-table metadata',
        ],

        'dashboard_master' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'dicipline' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 19642,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'HAS_INCIDENT',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'DisciplineCategory',
                'key' => 'dicipline',
            ],
            'note' => '19,642 rows. Master prompt said Student→Student — that is wrong, the target is a category',
        ],

        'dicipline_dd' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 42,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'DisciplineDropdown',
            'uid' => 'DisciplineDropdown:{tenant}:{syear}:{pk}',
            'note' => '42 rows. **Split from :DisciplineCategory 2026-08-10** — 16 (tenant,pk) pairs collide with dicipline_master, so sharing the label silently lost rows',
        ],

        'dicipline_master' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 62,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'DisciplineCategory',
            'uid' => 'DisciplineCategory:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'division' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 655,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Division',
            'uid' => 'Division:{tenant}:{syear}:{pk}',
            'note' => '`section_id` in enrollment refers here',
        ],

        'division_capacity_master' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 496,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'capacity on :Division',
            'note' => '',
        ],

        'document_extractions' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 108,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Extraction',
            'uid' => 'Extraction:{tenant}:{syear}:{pk}',
            'note' => 'The provenance of the Gen-2 chapters — 108 rows, 98 used by chapter_master',
        ],

        'document_templates' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'document_template_versions' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'donation_collection' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 26,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'rel' => 'RECEIVED_DONATION',
            'from' => [
                'label' => 'Institute',
                'key' => 'sub_institute_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**authoritative=false**',
        ],

        'dynamic_dashboard' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 181,
            'reason' => 'UI config',
        ],

        'email_sent_parents' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 2761,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'rel' => 'COMMUNICATION',
            'from' => [
                'label' => 'Staff',
                'key' => 'user_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '2,761 rows. Keys on USER_ID (staff sender); there is no student_id, so this cannot hang off :Student',
        ],

        'employee_monthly_salary_data' => [
            'module' => 'hr',
            'domain' => 'Payroll',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 4263,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'rel' => 'PAYROLL_MONTH',
            'from' => [
                'label' => 'Staff',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**authoritative=false.** 4,263 rows',
        ],

        'employee_salary_structures' => [
            'module' => 'hr',
            'domain' => 'Payroll',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 1128,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'rel' => 'HAS_SALARY_STRUCTURE',
            'from' => [
                'label' => 'Staff',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'PayrollType',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '**authoritative=false.** Structure/eligibility only',
        ],

        'enrollment_prefix_master' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6,
            'reason' => 'Numbering config, no traversal value',
        ],

        'erptour' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1973,
            'reason' => 'UI tour state',
        ],

        'erp_status' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 19,
            'reason' => 'Status lookup',
        ],

        'ERR_LOG' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 5312,
            'reason' => 'Error log',
        ],

        'exam_schedule' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 5215,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'ExamSchedule',
            'uid' => 'ExamSchedule:{tenant}:{syear}:{pk}',
            'note' => '5,215 rows',
        ],

        'fees_aggre_pay' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Gateway config',
        ],

        'fees_axis' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Gateway config, empty',
        ],

        'fees_breackoff' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 182333,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'rel' => 'APPLIES_TO',
            'from' => [
                'label' => 'FeeSchedule',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Standard',
                'key' => 'standard_id',
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**182,333 rows and NO student_id.** It is a fee *schedule* per (grade,standard,quota,month), not a student ledger. Master prompt was wrong — see F5',
        ],

        'fees_breackoff_logs' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4731,
            'reason' => 'Audit log',
        ],

        'fees_breakoff_other' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 46861,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'rel' => 'LIABLE_FOR',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'FeeHead',
                'key' => 'fee_type_id',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '46,861 rows — **this one does have student_id**. authoritative=false',
        ],

        'fees_cancel' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 7837,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'target' => 'status on the LIABLE_FOR edge',
            'note' => '7,837 rows',
        ],

        'fees_cancel_type' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 4,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'label' => 'FeeCancelType',
            'uid' => 'FeeCancelType:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'fees_circular_log' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 2454,
            'reason' => 'Audit log',
        ],

        'fees_circular_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 134,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'label' => 'FeeCircular',
            'uid' => 'FeeCircular:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'fees_collect' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 13,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'rel' => 'PAID',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'FeeTitle',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**Only 13 rows** despite q112. The collection ledger is effectively empty here — see F6',
        ],

        'fees_config_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 150,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'label' => 'FeeConfig',
            'uid' => 'FeeConfig:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'fees_hdfcrazorpay' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Gateway config',
        ],

        'fees_hdffc' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 2,
            'reason' => 'Gateway config',
        ],

        'fees_head_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 66,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'label' => 'FeeHead',
            'uid' => 'FeeHead:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'fees_icici' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6,
            'reason' => 'Gateway config',
        ],

        'fees_late_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 86,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'label' => 'LateFeeRule',
            'uid' => 'LateFeeRule:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'fees_map_years' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 176,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'rel' => 'FOR_YEAR',
            'from' => [
                'label' => 'FeeConfig',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'fees_month_header' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 62,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'label' => 'FeeMonth',
            'uid' => 'FeeMonth:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'fees_online_maping' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 13,
            'reason' => 'Gateway config',
        ],

        'fees_online_split' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'fees_other_cancel' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 15,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'target' => 'status',
            'note' => '',
        ],

        'fees_other_collection' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 2098,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'rel' => 'PAID',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'FeeHead',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '2,098 rows. authoritative=false',
        ],

        'fees_other_head' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 36,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'label' => 'FeeOtherHead',
            'uid' => 'FeeOtherHead:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false.** **Split from :FeeHead 2026-08-10** — 2 (tenant,pk) pairs collide with fees_head_master',
        ],

        'fees_paid_other' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 21,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'rel' => 'PAID',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'FeeHead',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '21 rows',
        ],

        'fees_payment' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '**0 rows but 60 code refs** — see F7',
        ],

        'fees_payphi' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Gateway config',
        ],

        'fees_razorpay' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4,
            'reason' => 'Gateway config',
        ],

        'fees_receipt' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 22,
            'reason' => '22 rows, print config',
        ],

        'fees_receipt_book_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 9734,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'label' => 'ReceiptBook',
            'uid' => 'ReceiptBook:{tenant}:{syear}:{pk}',
            'note' => '9,734 rows — receipt *stationery* config, not receipts. No student_id',
        ],

        'fees_receipt_css' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4,
            'reason' => 'Stylesheet config',
        ],

        'fees_reconciliation' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6721,
            'reason' => '**Never project.** Reconciliation must not have a second copy (6,721 rows)',
        ],

        'fees_refund' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 6,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'target' => 'refund flag',
            'note' => '6 rows',
        ],

        'fees_title' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 823,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'authoritative' => false,
            'label' => 'FeeTitle',
            'uid' => 'FeeTitle:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false.** q210',
        ],

        'fees_title_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 24,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'label' => 'FeeTitleMaster',
            'uid' => 'FeeTitleMaster:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'follow_up' => [
            'module' => 'operations',
            'domain' => 'FrontDesk',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4,
            'reason' => '4 rows',
        ],

        'form_builder' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 5,
            'reason' => 'Form metadata',
        ],

        'form_submit_data' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 8,
            'reason' => 'Form submissions',
        ],

        'front_desk' => [
            'module' => 'operations',
            'domain' => 'FrontDesk',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 1,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'SUB_INSTITUTE_ID',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'label' => 'FrontDeskEntry',
            'uid' => 'FrontDeskEntry:{tenant}:{syear}:{pk}',
            'note' => '1 row. Uses UPPERCASE SUB_INSTITUTE_ID',
        ],

        'gamma_ppt' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'gcm_users' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 26199,
            'reason' => '**26,199-row push-token registry** — no traversal value',
        ],

        'general_data' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'REVIEW',
            'rows_at_classification' => 189,
            'reason' => 'q40, 189 rows, generic name — needs a human to say what it holds',
        ],

        'grade_master' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 32,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'GradeScheme',
            'uid' => 'GradeScheme:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'grade_master_data' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 394,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Grade',
            'uid' => 'Grade:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'h5p_interactive_video' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'h5p_scenarios' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 11,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'H5PScenario',
            'uid' => 'H5PScenario:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'h5p_scenario_points' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 60,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :H5PScenario',
            'note' => '',
        ],

        'h5p_video_interactions' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'homework' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 94,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Homework',
            'uid' => 'Homework:{tenant}:{syear}:{pk}',
            'note' => 'q98',
        ],

        'hostel_building_master' => [
            'module' => 'operations',
            'domain' => 'Hostel',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 3,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'HostelBuilding',
            'uid' => 'HostelBuilding:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hostel_floor_master' => [
            'module' => 'operations',
            'domain' => 'Hostel',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 6,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'HostelFloor',
            'uid' => 'HostelFloor:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hostel_master' => [
            'module' => 'operations',
            'domain' => 'Hostel',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 4,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Hostel',
            'uid' => 'Hostel:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hostel_room_allocation' => [
            'module' => 'operations',
            'domain' => 'Hostel',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 9,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'ALLOCATED_ROOM',
            'from' => [
                'label' => 'Student',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'HostelRoom',
                'key' => 'room_id',
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Only 9 rows',
        ],

        'hostel_room_master' => [
            'module' => 'operations',
            'domain' => 'Hostel',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 97,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'HostelRoom',
            'uid' => 'HostelRoom:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hostel_type_master' => [
            'module' => 'operations',
            'domain' => 'Hostel',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 5,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'HostelType',
            'uid' => 'HostelType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hostel_visitor_master' => [
            'module' => 'operations',
            'domain' => 'Hostel',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 5,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'VISITED',
            'from' => [
                'label' => 'Visitor',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Student',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '5 rows',
        ],

        'house_master' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 43,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'House',
            'uid' => 'House:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hp_tblmenumaster' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 153,
            'reason' => 'Menu definitions, 0 refs',
        ],

        'hrms_attendances' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 354874,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'ATTENDANCE',
            'from' => [
                'label' => 'Staff',
                'key' => 'user_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**354,874 rows → ~monthly aggregate.** Date column is `day`; no syear — derive',
        ],

        'hrms_departments' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 113,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Department',
            'uid' => 'Department:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hrms_departments_mapping' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 53,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'IN_DEPARTMENT',
            'from' => [
                'label' => 'Staff',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Department',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'hrms_emp_leaves' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 28406,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'TOOK_LEAVE',
            'from' => [
                'label' => 'Staff',
                'key' => 'user_id',
            ],
            'to' => [
                'label' => 'LeaveType',
                'key' => 'leave_type_id',
            ],
            'note' => '28,406 rows',
        ],

        'hrms_emp_payroll_deduction' => [
            'module' => 'hr',
            'domain' => 'Payroll',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 7562,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'rel' => 'DEDUCTION',
            'from' => [
                'label' => 'Staff',
                'key' => 'created_by',
            ],
            'to' => [
                'label' => 'PayrollType',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**authoritative=false.** 7,562 rows — no amounts as node properties',
        ],

        'hrms_holidays' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 147,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Holiday',
            'uid' => 'Holiday:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hrms_in_out_times' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 18,
            'reason' => '18 rows, shift config',
        ],

        'hrms_job_titles' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'hrms_leave_allocation' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 877,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'ALLOCATED',
            'from' => [
                'label' => 'Staff',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'LeaveType',
                'key' => 'leave_type_id',
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'hrms_leave_types' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 19,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'LeaveType',
            'uid' => 'LeaveType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'hrms_salary_certificate' => [
            'module' => 'hr',
            'domain' => 'Payroll',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 12,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'label' => 'SalaryCertificate',
            'uid' => 'SalaryCertificate:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false.** 12 rows',
        ],

        'hrms_weekdays' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'C',
            'decision' => 'PROP',
            'rows_at_classification' => 7,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'lookup',
            'note' => '',
        ],

        'implementation_master' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'import_table_fields' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 492,
            'reason' => 'Import mapping config',
        ],

        'imprest_fees_cancel' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 107,
            'reason' => '107 rows, cancellation log',
        ],

        'incoming_messages' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 104,
            'reason' => 'Inbound webhook log',
        ],

        'institute_detail' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 10,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :Institute',
            'note' => '',
        ],

        'inventory_allocation_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 12,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'SUB_INSTITUTE_ID',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'rel' => 'ALLOCATED_ITEM',
            'from' => [
                'label' => 'Staff',
                'key' => 'created_by',
            ],
            'to' => [
                'label' => 'InventoryItem',
                'key' => 'item_id',
            ],
            'note' => 'Uses UPPERCASE SUB_INSTITUTE_ID',
        ],

        'inventory_generate_po_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 4,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'PURCHASE_ORDER',
            'from' => [
                'label' => 'Vendor',
                'key' => 'vendor_id',
            ],
            'to' => [
                'label' => 'InventoryItem',
                'key' => 'item_id',
            ],
            'note' => '',
        ],

        'inventory_item_category_master' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 62,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'ItemCategory',
            'uid' => 'ItemCategory:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'inventory_item_defective_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'inventory_item_direct_purchase' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 48,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'SUPPLIED',
            'from' => [
                'label' => 'Vendor',
                'key' => 'vendor_id',
            ],
            'to' => [
                'label' => 'InventoryItem',
                'key' => 'item_id',
            ],
            'note' => '',
        ],

        'inventory_item_lost_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'inventory_item_master' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 321,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'InventoryItem',
            'uid' => 'InventoryItem:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'inventory_item_quotation_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 8,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'QUOTED',
            'from' => [
                'label' => 'Vendor',
                'key' => 'vendor_id',
            ],
            'to' => [
                'label' => 'InventoryItem',
                'key' => 'item_id',
            ],
            'note' => '',
        ],

        'inventory_item_receivable_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4,
            'reason' => '4 rows',
        ],

        'inventory_item_return_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'inventory_item_sub_category_master' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 476,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'ItemSubCategory',
            'uid' => 'ItemSubCategory:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'inventory_item_type' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 4,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ItemType',
            'uid' => 'ItemType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'inventory_master_setup' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 15,
            'reason' => 'Config',
        ],

        'inventory_negotiate_po_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4,
            'reason' => '4 rows, workflow detail',
        ],

        'inventory_requisition_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 139,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'REQUISITIONED',
            'from' => [
                'label' => 'Staff',
                'key' => 'created_by',
            ],
            'to' => [
                'label' => 'InventoryItem',
                'key' => 'item_id',
            ],
            'note' => '',
        ],

        'inventory_requisition_status_master' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'PROP',
            'rows_at_classification' => 4,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'status lookup',
            'note' => '',
        ],

        'inventory_tax_master' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 5,
            'reason' => 'Tax config',
        ],

        'inventory_vendor_master' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 32,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Vendor',
            'uid' => 'Vendor:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'inward' => [
            'module' => 'operations',
            'domain' => 'Operations',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 5773,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'InwardDocument',
            'uid' => 'InwardDocument:{tenant}:{syear}:{pk}',
            'note' => '5,773 rows',
        ],

        'item_scan_details' => [
            'module' => 'operations',
            'domain' => 'Inventory',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 32569,
            'reason' => '**32,569-row scan log.** Aggregate a scan_count property only',
        ],

        'knowledge_base' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 8,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'KnowledgeBase',
            'uid' => 'KnowledgeBase:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'knowledge_base_detail' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 2,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :KnowledgeBase',
            'note' => '',
        ],

        'lb_master' => [
            'module' => 'platform',
            'domain' => 'Leaderboard',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 5,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'LeaderboardRule',
            'uid' => 'LeaderboardRule:{tenant}:{syear}:{pk}',
            'note' => '5 rows — points rule per (grade, standard, module)',
        ],

        'lb_points' => [
            'module' => 'platform',
            'domain' => 'Leaderboard',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 2,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'EARNED_POINTS',
            'from' => [
                'label' => 'Staff',
                'key' => 'user_id',
            ],
            'to' => [
                'label' => 'LeaderboardRule',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '2 rows. `user_id` → tbluser, not tblstudent',
        ],

        'learning_outcome_exam_type_master' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => 9,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 4,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ExamTypeRef',
            'uid' => 'ExamTypeRef:{tenant}:{syear}:{pk}',
            'note' => '4 rows, columns are ID + EXAM_TYPE only. ORPHAN-LABELS 2026-08-11: split from :ExamType — that label is Tier A via result_exam_master, so these 4 parentless reference rows hard-failed G8 despite having no FK to attach to',
        ],

        'learning_outcome_indicator' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => 5,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 11,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'LOIndicatorRef',
            'uid' => 'LOIndicatorRef:{tenant}:{syear}:{pk}',
            'note' => '11 rows — structural pilot. ORPHAN-LABELS 2026-08-11: split from :LearningOutcome and demoted to C. Its STANDARD/SUBJECT columns hold names, not ids, so no parent FK is resolvable; as Tier A it hard-failed G8 with no available remedy',
        ],

        'learning_outcome_pdf' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 151,
            'reason' => 'Generated PDFs',
        ],

        'learning_outcome_question_master' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => 6,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 15,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'rel' => 'ASSESSED_BY',
            'from' => [
                'label' => 'LearningOutcome',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Question',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '15 rows',
        ],

        'learning_outcome_student_marks' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => 9,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 14,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'SUB_INSTITUTE_ID',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'rel' => 'ACHIEVED',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'LearningOutcome',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '14 rows',
        ],

        'leave_applications' => [
            'module' => 'hr',
            'domain' => 'Leave',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 20396,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'APPLIED_FOR_LEAVE',
            'from' => [
                'label' => 'Staff',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'LeaveType',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '**20,396 rows — not in the master prompt.** Separate leave module',
        ],

        'lessonplan' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 39,
            'reason' => '39 rows, q41 — legacy sibling of lms_lesson_plan',
        ],

        'lessonplan_execution' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 5,
            'reason' => '5 rows',
        ],

        'library_books' => [
            'module' => 'operations',
            'domain' => 'Library',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 35663,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'academic_year',
            ],
            'label' => 'Book',
            'uid' => 'Book:{tenant}:{syear}:{pk}',
            'note' => '35,663 rows. Uses `academic_year`, not syear',
        ],

        'library_book_circulations' => [
            'module' => 'operations',
            'domain' => 'Library',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 67487,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'BORROWED',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Book',
                'key' => 'book_id',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**67,487 rows → aggregate per (student, book).** Enables "students who read X also read Y"',
        ],

        'library_items' => [
            'module' => 'operations',
            'domain' => 'Library',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 36181,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'COPY_OF',
            'from' => [
                'label' => 'BookCopy',
                'key' => 'item_code',
            ],
            'to' => [
                'label' => 'Book',
                'key' => 'book_id',
            ],
            'note' => '36,181 physical copies',
        ],

        'lms_assignment' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 44,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Assignment',
            'uid' => 'Assignment:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_concept' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 1372,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Concept',
            'uid' => 'Concept:{tenant}:{syear}:{pk}',
            'note' => '1,372 rows, **100% tenant 1**, 0 dangling chapter_id. The one clean spine',
        ],

        'lms_content_category' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 21,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ContentCategory',
            'uid' => 'ContentCategory:{tenant}:{syear}:{pk}',
            'note' => 'Global reference data — all 21 rows carry sub_institute_id 0 (CATEGORY-SCOPE 2026-08-11)',
        ],

        'lms_curriculum' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 10,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Curriculum',
            'uid' => 'Curriculum:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_data_content_neo4j' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 13693,
            'reason' => '**Old migration staging table** (13,693 rows, q2). Do not re-import',
        ],

        'lms_data_neo4j' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4131,
            'reason' => 'Old migration staging (4,131 rows, q0)',
        ],

        'lms_data_question_neo4j' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Old migration staging, empty',
        ],

        'lms_doubt' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 3,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Doubt',
            'uid' => 'Doubt:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_doubt_conversation' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 24,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'HAS_REPLY',
            'from' => [
                'label' => 'Doubt',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Doubt',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'lms_flashcard' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 12,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Flashcard',
            'uid' => 'Flashcard:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_intelligence_lesson_plans' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 4,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Lesson',
            'uid' => 'Lesson:{tenant}:{syear}:{pk}',
            'note' => 'AI-generated variant; 4 rows',
        ],

        'lms_learning_outcomes' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 259,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'subject_id',
                'table' => 'subject',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'LearningObjective',
            'uid' => 'LearningObjective:{tenant}:{syear}:{pk}',
            'note' => 'No tenancy column — derive via subject_id (LO-TENANCY 2026-08-11; chapter_id path kept 0 of 259)',
        ],

        'lms_lessonplan_dayswise' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 3337,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'SCHEDULED_ON',
            'from' => [
                'label' => 'Lesson',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'lms_lesson_plan' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 1803,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Lesson',
            'uid' => 'Lesson:{tenant}:{syear}:{pk}',
            'note' => '1,803 rows; 99.3% chapter_id dangling',
        ],

        'lms_lesson_plan_concepts' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 537,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'lms_lesson_plan_periods_id',
                'table' => 'lms_lesson_plan_periods',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'lms_lesson_plan_periods_id',
                'table' => 'lms_lesson_plan_periods',
                'key' => 'id',
            ],
            'rel' => 'COVERS',
            'from' => [
                'label' => 'Lesson',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Concept',
                'key' => 'concept_id',
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Joins via lms_lesson_plan_periods_id, not lesson_id',
        ],

        'lms_lesson_plan_periods' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 329,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'lms_intelligence_lesson_plans_id',
                'table' => 'lms_intelligence_lesson_plans',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'lms_intelligence_lesson_plans_id',
                'table' => 'lms_intelligence_lesson_plans',
                'key' => 'id',
            ],
            'label' => 'LessonPeriod',
            'uid' => 'LessonPeriod:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_mapping_type' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 71532,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'MappingType',
            'uid' => 'MappingType:{tenant}:{syear}:{pk}',
            'note' => 'Self-referencing taxonomy tree (Blooms, DoK). `type` empty on 99.8%',
        ],

        'lms_offline_exam' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 2,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Result',
            'uid' => 'Result:{tenant}:{syear}:{pk}',
            'note' => '2 rows',
        ],

        'lms_offline_exam_answer' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 5,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'rel' => 'MASTERS',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Chapter',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '5 rows. Aggregates to :Chapter per CONCEPT-LINK',
        ],

        'lms_online_exam' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 147875,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'label' => 'Result',
            'uid' => 'Result:{tenant}:{syear}:{pk}',
            'note' => '147,875 rows but only **1,326 distinct students**. No tenancy column — derive via question_paper_id (21 orphans)',
        ],

        'lms_online_exam_answer' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'A',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 2418015,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'rel' => 'MASTERS',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Chapter',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**2,418,015 rows → ~24k-58k edges.** Cannot target :Concept — see F2',
        ],

        'lms_online_exam_answer_student' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 723,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'rel' => 'MASTERS',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Chapter',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '723 rows. Aggregates to :Chapter per CONCEPT-LINK',
        ],

        'lms_online_exam_student' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 78,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'question_paper_id',
                'table' => 'question_paper',
                'key' => 'id',
            ],
            'rel' => 'HAS_RESULT',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Result',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '78 rows',
        ],

        'lms_portfolio' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 9,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Portfolio',
            'uid' => 'Portfolio:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_question_mapping' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 6,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 516022,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'questionmaster_id',
                'table' => 'lms_question_master',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'questionmaster_id',
                'table' => 'lms_question_master',
                'key' => 'id',
            ],
            'rel' => 'TAGGED_AS',
            'from' => [
                'label' => 'Question',
                'key' => 'questionmaster_id',
            ],
            'to' => [
                'label' => 'MappingType',
                'key' => 'mapping_type_id',
            ],
            'note' => '**NOT chapter/concept mapping** — it is Blooms + Depth-of-Knowledge tagging. See F3',
        ],

        'lms_question_master' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 6,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 62209,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Question',
            'uid' => 'Question:{tenant}:{syear}:{pk}',
            'note' => '**concept_id empty on 99.92%** (62,162/62,209) — ASSESSES_CONCEPT cannot be built from here. See F2',
        ],

        'lms_syllabus' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 2,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Syllabus',
            'uid' => 'Syllabus:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_teacher_resource' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 2606,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'TeacherResource',
            'uid' => 'TeacherResource:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lms_units' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 60,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'curriculum_id',
                'table' => 'lms_curriculum',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Unit',
            'uid' => 'Unit:{tenant}:{syear}:{pk}',
            'note' => 'No tenancy column — derive via curriculum_id',
        ],

        'lms_virtual_classroom' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 25,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'VirtualClassroom',
            'uid' => 'VirtualClassroom:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'lo_category' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 1,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'LOCategoryAlt',
            'uid' => 'LOCategoryAlt:{tenant}:{syear}:{pk}',
            'note' => '1 row. **Split from :LOCategory 2026-08-10** — its single (tenant,pk) collides with lo_master',
        ],

        'lo_indicator' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 1,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'LearningOutcome',
            'uid' => 'LearningOutcome:{tenant}:{syear}:{pk}',
            'note' => '1 row',
        ],

        'lo_master' => [
            'module' => 'curriculum',
            'domain' => 'LearningOutcome',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 23,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'LOCategory',
            'uid' => 'LOCategory:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'mapped_teachers' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 11,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'MAPPED_TO',
            'from' => [
                'label' => 'Staff',
                'key' => 'teacher_id',
            ],
            'to' => [
                'label' => 'Subject',
                'key' => 'subject_id',
            ],
            'note' => '**Only 11 rows** — verify before relying on it',
        ],

        'master_compliance' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty compliance tracker',
        ],

        'master_fields' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 26,
            'reason' => 'Form-builder metadata',
        ],

        'master_fields_institute' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 28,
            'reason' => 'Form-builder metadata',
        ],

        'master_fields_table' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 2,
            'reason' => 'Form-builder metadata',
        ],

        'master_setup_select' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'PROP',
            'rows_at_classification' => 54,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'lookup',
            'note' => '',
        ],

        'master_skills' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 16239,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Skill',
            'uid' => 'Skill:{tenant}:{syear}:{pk}',
            'note' => '16,239 rows. sub_institute_id = 0 → reference data, not tenant-scoped',
        ],

        'MBTI_answer' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 16,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'answer key on :Assessment',
            'note' => '**16 rows; columns are only (id, ans_key, answer_html)** — no student and no FK to MBTI_paper, so it cannot be a mastery edge',
        ],

        'MBTI_paper' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 8,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 1,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Assessment',
            'uid' => 'Assessment:{tenant}:{syear}:{pk}',
            'note' => '1 row — MBTI pilot',
        ],

        'migrations' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 278,
            'reason' => 'Laravel framework table',
        ],

        'mobile_homescreen' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1142,
            'reason' => 'UI config',
        ],

        'mst_item_status' => [
            'module' => 'operations',
            'domain' => 'Operations',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'PROP',
            'rows_at_classification' => 7,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'status lookup',
            'note' => '',
        ],

        'NACH_ac_type' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 9,
            'reason' => 'Lookup',
        ],

        'NACH_MASTER' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 3,
            'reason' => 'Mandate config',
        ],

        'neo4j_sync_queue' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 12193,
            'reason' => '**12,193 rows, 0 code references.** Orphaned queue from a previous sync attempt — see F10',
        ],

        'new_admission_inquiry_registration' => [
            'module' => 'people',
            'domain' => 'Admission',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 356,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Enquiry',
            'uid' => 'Enquiry:{tenant}:{syear}:{pk}',
            'note' => '356 rows',
        ],

        'new_client_rights' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Permission config',
        ],

        'old_question_category_master' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6,
            'reason' => 'Legacy, superseded by lms_mapping_type',
        ],

        'old_question_level_master' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 3,
            'reason' => 'Legacy',
        ],

        'onboarding_module' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 40,
            'reason' => 'Onboarding checklist',
        ],

        'onboarding_progress' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 20,
            'reason' => 'Onboarding checklist',
        ],

        'onboarding_step' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 611,
            'reason' => 'Onboarding checklist',
        ],

        'onet_abilities' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 90792,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'Ability',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => 'Rating ledger — project top-20 per occupation as weighted edges, log dropped rows',
        ],

        'onet_career_cluster' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 1011,
            'pk' => 'career_id',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'CareerCluster',
            'uid' => 'CareerCluster:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_content_model_reference' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 627,
            'pk' => 'element_id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ONetElement',
            'uid' => 'ONetElement:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_employer' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 23,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Employer',
            'uid' => 'Employer:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_expert_advice' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 430,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ExpertAdvice',
            'uid' => 'ExpertAdvice:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_explore_sector' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 344,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Sector',
            'uid' => 'Sector:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_institute_courses' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 16,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Course',
            'uid' => 'Course:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_institute_data' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'REVIEW',
            'rows_at_classification' => 65738,
            'reason' => '**65,738 rows of Indian college listings (AICTE).** Not K12 and not occupation data — decide whether the careers module needs it',
        ],

        'onet_interests' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'EDGE',
            'rows_at_classification' => 8307,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'Interest',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Rating table — project as weighted edges',
        ],

        'onet_job_zones' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'EDGE',
            'rows_at_classification' => 923,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'JobZone',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Rating table — project as weighted edges',
        ],

        'onet_job_zone_reference' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 5,
            'pk' => 'job_zone',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'JobZone',
            'uid' => 'JobZone:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_knowledge' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 57618,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'Knowledge',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => 'Rating ledger — project top-20 per occupation as weighted edges, log dropped rows',
        ],

        'onet_occupation_data' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 1016,
            'pk' => 'onetsoc_code',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Occupation',
            'uid' => 'Occupation:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_scales_reference' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 29,
            'pk' => 'scale_id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ONetScale',
            'uid' => 'ONetScale:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_skills' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 61110,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'Skill',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => 'Rating ledger — project top-20 per occupation as weighted edges, log dropped rows',
        ],

        'onet_task_ratings' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 161847,
            'pk' => 'task_id',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'Task',
                'key' => 'task_id',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => 'Rating ledger — project top-20 per occupation as weighted edges, log dropped rows',
        ],

        'onet_task_statements' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'EDGE',
            'rows_at_classification' => 19281,
            'pk' => 'task_id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'Task',
                'key' => 'task_id',
            ],
            'note' => 'Rating table — project as weighted edges',
        ],

        'onet_technology_skills' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'EDGE',
            'rows_at_classification' => 32470,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'TechnologySkill',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Rating table — project as weighted edges',
        ],

        'onet_tools_used' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'EDGE',
            'rows_at_classification' => 41650,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'Tool',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Rating table — project as weighted edges',
        ],

        'onet_unspsc_reference' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 4262,
            'pk' => 'commodity_code',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'UNSPSCCategory',
            'uid' => 'UNSPSCCategory:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_work_activities' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 71586,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'WorkActivity',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => 'Rating ledger — project top-20 per occupation as weighted edges, log dropped rows',
        ],

        'onet_work_context' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 289173,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'WorkContext',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => 'Rating ledger — project top-20 per occupation as weighted edges, log dropped rows',
        ],

        'onet_work_context_categories' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 281,
            'pk' => 'element_id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'WorkContextCategory',
            'uid' => 'WorkContextCategory:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'onet_work_styles' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'EDGE',
            'rows_at_classification' => 13968,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'WorkStyle',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Rating table — project as weighted edges',
        ],

        'onet_work_values' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'EDGE',
            'rows_at_classification' => 7866,
            'pk' => 'onetsoc_code',
            'pk_is_natural' => true,
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES',
            'from' => [
                'label' => 'Occupation',
                'key' => 'onetsoc_code',
            ],
            'to' => [
                'label' => 'WorkValue',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => 'Rating table — project as weighted edges',
        ],

        'outward' => [
            'module' => 'operations',
            'domain' => 'Operations',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 149,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'OutwardDocument',
            'uid' => 'OutwardDocument:{tenant}:{syear}:{pk}',
            'note' => '149 rows',
        ],

        'o_net_data_categories' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 12,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ONetDataCategory',
            'uid' => 'ONetDataCategory:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'o_net_data_occupations' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 246,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ONetDataOccupation',
            'uid' => 'ONetDataOccupation:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'o_net_data_sub_categories' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 358,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ONetDataSubCategory',
            'uid' => 'ONetDataSubCategory:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'o_net_data_tables' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 209080,
            'reason' => 'Denormalised summary table, 9 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_data_table_lists' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 873,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ONetDataTable',
            'uid' => 'ONetDataTable:{tenant}:{syear}:{pk}',
            'note' => 'O*NET dimension — load once, no live sync',
        ],

        'o_net_occupation_details' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1230,
            'reason' => 'Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_abilities_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 16424,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_education_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 2452,
            'reason' => 'Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_interest_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 2214,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_job_zone_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 872,
            'reason' => 'Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_knowledge_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6668,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_lists' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 13515,
            'reason' => 'Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_list_summaries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 17592,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_skill_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 12442,
            'reason' => 'Denormalised summary table, 3 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_tech_skill_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 12028,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_work_activity_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 21580,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_work_style_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 15603,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'o_net_occupation_detail_work_value_summeries' => [
            'module' => 'skills',
            'domain' => 'ONET',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 2618,
            'reason' => 'Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask',
        ],

        'pal_assessment_results' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_classroom_activities' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_collaboration_activities' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_competencies' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_concepts' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 1,
            'reason' => 'PAL stub, 1 row(s) — schema exists, feature not in production',
        ],

        'pal_contents' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 1,
            'reason' => 'PAL stub, 1 row(s) — schema exists, feature not in production',
        ],

        'pal_content_recommendations' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_discussions' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_group_activities' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_learner_misconceptions' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 2,
            'reason' => 'PAL stub, 2 row(s) — schema exists, feature not in production',
        ],

        'pal_learner_preferences' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_learner_states' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_learning_events' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_learning_journals' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_learning_patterns' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_learning_plans' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_learning_sessions' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 1,
            'reason' => 'PAL stub, 1 row(s) — schema exists, feature not in production',
        ],

        'pal_misconceptions' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 2,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Misconception',
            'uid' => 'Misconception:{tenant}:{syear}:{pk}',
            'note' => '**2 rows.** The named source for (:Misconception) in Wave 2 — the shape is buildable but carries almost no data',
        ],

        'pal_pedagogy_effectiveness' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 1,
            'reason' => 'PAL stub, 1 row(s) — schema exists, feature not in production',
        ],

        'pal_reflections' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 3,
            'reason' => 'PAL stub, 3 row(s) — schema exists, feature not in production',
        ],

        'pal_remediations' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 1,
            'reason' => 'PAL stub, 1 row(s) — schema exists, feature not in production',
        ],

        'pal_remediation_sessions' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_self_corrections' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_session_events' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 18,
            'reason' => 'PAL stub, 18 row(s) — schema exists, feature not in production',
        ],

        'pal_strategy_selections' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_subjects' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'PAL stub, empty — schema exists, feature not in production',
        ],

        'pal_telemetry_events' => [
            'module' => 'platform',
            'domain' => 'PAL',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 5,
            'reason' => 'PAL stub, 5 row(s) — schema exists, feature not in production',
        ],

        'parent_communication' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 22729,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'COMMUNICATION',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '22,729 rows',
        ],

        'password_resets' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 496,
            'reason' => 'Framework table',
        ],

        'payroll_types' => [
            'module' => 'hr',
            'domain' => 'Payroll',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 46,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'label' => 'PayrollType',
            'uid' => 'PayrollType:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'period' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 307,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'academic_year_id',
            ],
            'label' => 'Period',
            'uid' => 'Period:{tenant}:{syear}:{pk}',
            'note' => 'Uses `academic_year_id`, not syear',
        ],

        'period_details' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 2034,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :Period',
            'note' => '',
        ],

        'personal_access_tokens' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 64,
            'reason' => 'Framework table',
        ],

        'petty_cash' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 214,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'rel' => 'PETTY_CASH',
            'from' => [
                'label' => 'Staff',
                'key' => 'user_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**authoritative=false**',
        ],

        'petty_cash_master' => [
            'module' => 'finance',
            'domain' => 'Fees',
            'phase' => 11,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 48,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'authoritative' => false,
            'label' => 'PettyCashHead',
            'uid' => 'PettyCashHead:{tenant}:{syear}:{pk}',
            'note' => '**authoritative=false**',
        ],

        'photo_video_gallary' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty (note the typo)',
        ],

        'physical_file_location' => [
            'module' => 'operations',
            'domain' => 'Operations',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 195,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'FileLocation',
            'uid' => 'FileLocation:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'place_master' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 112,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Place',
            'uid' => 'Place:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'proxy_master' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 6474,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'SUBSTITUTED_FOR',
            'from' => [
                'label' => 'Staff',
                'key' => 'teacher_id',
            ],
            'to' => [
                'label' => 'Staff',
                'key' => 'teacher_id',
            ],
            'note' => '6,474 rows — substitute teaching',
        ],

        'ptm_booking_master' => [
            'module' => 'platform',
            'domain' => 'PTM',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 789,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'SUB_INSTITUTE_ID',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'BOOKED',
            'from' => [
                'label' => 'Guardian',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Staff',
                'key' => 'teacher_id',
            ],
            'note' => '789 rows',
        ],

        'ptm_time_slots_master' => [
            'module' => 'platform',
            'domain' => 'PTM',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 152,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'TimeSlot',
            'uid' => 'TimeSlot:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'question_paper' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 6,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 5431,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Assessment',
            'uid' => 'Assessment:{tenant}:{syear}:{pk}',
            'note' => 'Has both sub_institute_id and syear — the tenancy anchor for lms_online_exam',
        ],

        'question_type_master' => [
            'module' => 'assessment',
            'domain' => 'Assessment',
            'phase' => 6,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 8,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'QuestionType',
            'uid' => 'QuestionType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'recommendations' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '**0 rows but 11 code refs** — see F7',
        ],

        'relation_table_fields' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 52,
            'reason' => 'Import mapping config',
        ],

        'religion' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 14,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Religion',
            'uid' => 'Religion:{tenant}:{syear}:{pk}',
            'note' => 'Demographic dimension',
        ],

        'report_dynamic' => [
            'module' => 'platform',
            'domain' => 'Report',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 7,
            'reason' => 'Report definitions — read path only',
        ],

        'report_module' => [
            'module' => 'platform',
            'domain' => 'Report',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 22,
            'reason' => 'Report definitions',
        ],

        'report_module_data' => [
            'module' => 'platform',
            'domain' => 'Report',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 25,
            'reason' => 'Report definitions',
        ],

        'report_module_fields' => [
            'module' => 'platform',
            'domain' => 'Report',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 26,
            'reason' => 'Report definitions',
        ],

        'requirement_gathering' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 5,
            'reason' => '5 rows',
        ],

        'result_activity_group' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 38,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ActivityGroup',
            'uid' => 'ActivityGroup:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'result_activity_marks' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '**0 rows but 21 code refs** — see F7',
        ],

        'result_activity_master' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 4664,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Activity',
            'uid' => 'Activity:{tenant}:{syear}:{pk}',
            'note' => '4,664 rows',
        ],

        'result_book_master' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 479,
            'reason' => 'Report-book config',
        ],

        'result_co_scholastic' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 1353,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'CoScholasticArea',
            'uid' => 'CoScholasticArea:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'result_co_scholastic_grades' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 1880,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'CO_SCHOLASTIC_GRADE',
            'from' => [
                'label' => 'Student',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'CoScholasticArea',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'result_co_scholastic_marks_entries' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '**0 rows but 11 code refs** — see F7',
        ],

        'result_co_scholastic_parent' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 23,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'CoScholasticArea',
            'uid' => 'CoScholasticArea:{tenant}:{syear}:{pk}',
            'note' => 'Parent category',
        ],

        'result_co_scholatic_range' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 34,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'grade band',
            'note' => 'Note the typo in the table name',
        ],

        'result_create_exam' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 33759,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Exam',
            'uid' => 'Exam:{tenant}:{syear}:{pk}',
            'note' => '33,759 rows',
        ],

        'result_exam_approve' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 6125,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'approval status on :Exam',
            'note' => '6,125 rows — workflow state',
        ],

        'result_exam_master' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 1227,
            'pk' => 'Id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'SubInstituteId',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ExamType',
            'uid' => 'ExamType:{tenant}:{syear}:{pk}',
            'note' => 'Uses **`SubInstituteId`** (PascalCase) and PK `Id` — loader must special-case',
        ],

        'result_exam_type_master' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 56,
            'pk' => 'Id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'SubInstituteId',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'ExamTypeCategory',
            'uid' => 'ExamTypeCategory:{tenant}:{syear}:{pk}',
            'note' => 'Also PascalCase `SubInstituteId`',
        ],

        'result_html' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '**0 rows but 39 code refs** — rendered HTML cache. See F7',
        ],

        'result_marks' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '**0 rows but 16 code refs** — see F7',
        ],

        'result_master_confrigration' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 392,
            'reason' => 'Report config (note the typo)',
        ],

        'result_oldyear_marks' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '0 rows, 0 refs',
        ],

        'result_personalize_marks' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'A',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 1308379,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'SCORED',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Exam',
                'key' => 'exam_id',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**1,308,379 rows → 54,712 edges** (measured). Only 4 tenants / 443 students',
        ],

        'result_remarks' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'A',
            'decision' => 'PROP',
            'rows_at_classification' => 10936,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'remark on the :SCORED edge',
            'note' => '',
        ],

        'result_remark_masters' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 398,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'RemarkTemplate',
            'uid' => 'RemarkTemplate:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'result_reportcard_marks' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 20865,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'REPORTCARD',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Exam',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '20,865 rows',
        ],

        'result_skillset' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 460,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Skillset',
            'uid' => 'Skillset:{tenant}:{syear}:{pk}',
            'note' => 'q41',
        ],

        'result_std_grd_maping' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 286,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'USES_GRADE_SCHEME',
            'from' => [
                'label' => 'Standard',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'GradeScheme',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'result_student_attendance_master' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'A',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 53032,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'ATTENDANCE',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => 'Aggregate per (student, syear, **term_id**) — there is no month column',
        ],

        'result_sub_activity' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => '**0 rows but 16 code refs** — see F7',
        ],

        'result_template_master' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 62,
            'reason' => 'Print templates',
        ],

        'result_trust_master' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 88,
            'reason' => 'Print header config',
        ],

        'result_working_day_master' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => 9,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 507,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'working days on :AcademicYear',
            'note' => '',
        ],

        'rightside_menumaster' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 274,
            'reason' => 'Menu definitions',
        ],

        'role_responsibility' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 7,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :Role',
            'note' => '',
        ],

        'room_type_master' => [
            'module' => 'operations',
            'domain' => 'Operations',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 5,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'RoomType',
            'uid' => 'RoomType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'S2_LOG' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 378,
            'reason' => 'Integration log',
        ],

        'school_detail' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 95,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :Institute',
            'note' => '',
        ],

        'school_sections' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 10,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'school_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'SchoolSection',
            'uid' => 'SchoolSection:{tenant}:{syear}:{pk}',
            'note' => 'Tenant is `school_id`, not the NULL sub_institute_id column (SCHOOL-SECTION-TENANCY 2026-08-11)',
        ],

        'school_setup' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 56,
            'pk' => 'Id',
            'tenant' => [
                'mode' => 'self',
                'column' => 'Id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Institute',
            'uid' => 'Institute:{tenant}:{syear}:{pk}',
            'note' => 'PK `Id`; no sub_institute_id — the PK *is* the tenant. uid Institute:{Id}:0:{Id}',
        ],

        'semantic_intelligence' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'REVIEW',
            'rows_at_classification' => 88,
            'reason' => '88 rows, q16 — unclear role',
        ],

        'sharebazar_margin' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 18985,
            'reason' => 'Stock-trading module — not K12. Confirm before deleting',
        ],

        'sharebazar_pnl' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 24829,
            'reason' => 'Stock-trading module — not K12',
        ],

        'sharebazar_position' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 77305,
            'reason' => '**77,305 rows.** Stock-trading module — not K12',
        ],

        'sms_api_details' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 34,
            'reason' => 'Gateway config',
        ],

        'sms_sent_parents' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 53447,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'rel' => 'COMMUNICATION',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '**53,447-row send log** — aggregate counts only',
        ],

        'sms_sent_staff' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 10,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'COMMUNICATION',
            'from' => [
                'label' => 'Staff',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'needs_endpoint_keys' => true,
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '10 rows',
        ],

        'smtp_details' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6,
            'reason' => 'Mail config',
        ],

        'sqaa_documant_master' => [
            'module' => 'skills',
            'domain' => 'SQAA',
            'phase' => 13,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 1534,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'SQAADocument',
            'uid' => 'SQAADocument:{tenant}:{syear}:{pk}',
            'note' => '1,534 rows (note the typo)',
        ],

        'sqaa_documents' => [
            'module' => 'skills',
            'domain' => 'SQAA',
            'phase' => 13,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 86,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'SUBMITTED',
            'from' => [
                'label' => 'Institute',
                'key' => 'sub_institute_id',
            ],
            'to' => [
                'label' => 'SQAADocument',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'sqaa_marks' => [
            'module' => 'skills',
            'domain' => 'SQAA',
            'phase' => 13,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 6,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'SCORED_SQAA',
            'from' => [
                'label' => 'Institute',
                'key' => 'sub_institute_id',
            ],
            'to' => [
                'label' => 'SQAAStandard',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '6 rows',
        ],

        'sqaa_master' => [
            'module' => 'skills',
            'domain' => 'SQAA',
            'phase' => 13,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 247,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'SQAAStandard',
            'uid' => 'SQAAStandard:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'staff_document' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 11282,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'document_count on :Staff',
            'note' => '11,282 rows of file metadata',
        ],

        'standard' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 1000,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Standard',
            'uid' => 'Standard:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'std_div_map' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 1966,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'HAS_DIVISION',
            'from' => [
                'label' => 'Standard',
                'key' => 'standard_id',
            ],
            'to' => [
                'label' => 'Division',
                'key' => 'division_id',
            ],
            'note' => 'NOT in the master prompt — this is how Standard reaches Division',
        ],

        'student_anacdotal' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 11749,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'ANECDOTAL_NOTES',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '11,749-row ledger',
        ],

        'student_capture_attendance' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'B',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 130,
            'reason' => '130 rows, photo-capture log',
        ],

        'student_capture_photos' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 190,
            'reason' => 'Photo metadata',
        ],

        'student_change_request' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 10,
            'reason' => 'Workflow log',
        ],

        'student_change_req_type' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Lookup',
        ],

        'STUDENT_CHANGE_REQ_TYPE' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 6,
            'reason' => 'Case-variant duplicate of student_change_req_type — see F9',
        ],

        'student_document_type' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 62,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'DocumentType',
            'uid' => 'DocumentType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'student_health' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 94,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'on :Student',
            'note' => '**Sensitive PII**',
        ],

        'student_height_weight' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 9415,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'BIOMETRIC',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '9,415-row ledger — aggregate latest per year',
        ],

        'student_infirmary' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 21373,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'INFIRMARY_VISITS',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '21,373-row ledger. **Sensitive PII**',
        ],

        'student_optional_subject' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 103678,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'STUDIES',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Subject',
                'key' => 'subject_id',
            ],
            'note' => '',
        ],

        'student_quota' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 873,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Quota',
            'uid' => 'Quota:{tenant}:{syear}:{pk}',
            'note' => 'q125 — heavily used; also the join key for the fee schedule',
        ],

        'student_vaccination' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 8,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'on :Student',
            'note' => '**Sensitive PII**, 8 rows',
        ],

        'subject' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 2025,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Subject',
            'uid' => 'Subject:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'subject_elective' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 74,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'HAS_ELECTIVE',
            'from' => [
                'label' => 'Standard',
                'key' => 'standard_id',
            ],
            'to' => [
                'label' => 'Subject',
                'key' => 'subject_id',
            ],
            'note' => '',
        ],

        'subject_optional_type' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 49,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'OptionalSubjectType',
            'uid' => 'OptionalSubjectType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'sub_std_map' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 6656,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'HAS_SUBJECT',
            'from' => [
                'label' => 'Standard',
                'key' => 'standard_id',
            ],
            'to' => [
                'label' => 'Subject',
                'key' => 'subject_id',
            ],
            'note' => '',
        ],

        'suggested_content' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 10,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'SUGGESTED_FOR',
            'from' => [
                'label' => 'Content',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Concept',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '',
        ],

        'syllabus' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 18,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Syllabus',
            'uid' => 'Syllabus:{tenant}:{syear}:{pk}',
            'note' => 'Second syllabus table — reconcile with lms_syllabus at Phase 2',
        ],

        'sync_log' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 9240,
            'reason' => '9,240 rows, 0 code refs',
        ],

        's_assessment_library' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 40,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'SkillAssessment',
            'uid' => 'SkillAssessment:{tenant}:{syear}:{pk}',
            'note' => '40 rows',
        ],

        's_industries' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 715,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Industry',
            'uid' => 'Industry:{tenant}:{syear}:{pk}',
            'note' => '715 rows, 0 code refs',
        ],

        's_jobrole' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 5805,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'JobRole',
            'uid' => 'JobRole:{tenant}:{syear}:{pk}',
            'note' => '5,805 rows',
        ],

        's_jobrole_skills' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 176460,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'REQUIRES_SKILL',
            'from' => [
                'label' => 'JobRole',
                'key' => 'jobrole',
            ],
            'to' => [
                'label' => 'Skill',
                'key' => 'skill',
            ],
            'note' => '**176,460 rows. `jobrole` and `skill` are NAME STRINGS, not ids** — violates L1. See F8',
        ],

        's_jobrole_task' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 34060,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'INVOLVES_TASK',
            'from' => [
                'label' => 'JobRole',
                'key' => 'jobrole',
            ],
            'to' => [
                'label' => 'Task',
                'key' => 'task',
            ],
            'note' => '34,060 rows',
        ],

        's_skill_map_k_a' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 138461,
            'reason' => '**138,461 rows / 79.7 MB, 0 code references.** Do not project — confirm with owner first',
        ],

        's_skill_matrix' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 3,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'derive',
                'fk' => 'user_id',
                'table' => 'tbluser',
                'key' => 'id',
            ],
            'syear' => [
                'mode' => 'derive',
                'fk' => 'user_id',
                'table' => 'tbluser',
                'key' => 'id',
            ],
            'rel' => 'HAS_SKILL',
            'from' => [
                'label' => 'Staff',
                'key' => 'user_id',
            ],
            'to' => [
                'label' => 'Skill',
                'key' => 'skill_id',
            ],
            'note' => '**Only 3 rows.** `user_id` → tbluser',
        ],

        's_user_jobrole' => [
            'module' => 'skills',
            'domain' => 'Skill',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 4909,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'TARGETS_JOBROLE',
            'from' => [
                'label' => 'Staff',
                'key' => 'created_by',
            ],
            'to' => [
                'label' => 'JobRole',
                'key' => 'jobrole',
            ],
            'note' => '4,909 rows',
        ],

        'table_relation' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 10,
            'reason' => 'Import mapping config',
        ],

        'task' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 887,
            'pk' => 'ID',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'SYEAR',
            ],
            'label' => 'Task',
            'uid' => 'Task:{tenant}:{syear}:{pk}',
            'note' => '887 rows, q51',
        ],

        'task_management_comments' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'task_management_dependencies' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'task_management_milestones' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'tblapplications' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'tblcity' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 609,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'City',
            'uid' => 'City:{tenant}:{syear}:{pk}',
            'note' => 'Geography dimension',
        ],

        'tblclient' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 148,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Client',
            'uid' => 'Client:{tenant}:{syear}:{pk}',
            'note' => 'Parent org above Institute',
        ],

        'tblcustom_fields' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 277,
            'reason' => 'Custom-field metadata',
        ],

        'tblemp_skills' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 13,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 96,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'HAS_SKILL',
            'from' => [
                'label' => 'Staff',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Skill',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '96 rows — the only staff→skill link',
        ],

        'tblfields_data' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 51,
            'reason' => 'Custom-field metadata',
        ],

        'tblgroupwise_rights' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 30557,
            'reason' => '30,557-row permission matrix — no traversal value',
        ],

        'tblindividual_rights' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 25060,
            'reason' => '25,060-row permission matrix',
        ],

        'tblmenumaster' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 501,
            'reason' => 'Menu definitions',
        ],

        'tblmenumaster_new' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'tblmenumaster_old' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'tblprofilewise_menu' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 48351,
            'reason' => '48,351-row menu matrix',
        ],

        'tblstate' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 36,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'State',
            'uid' => 'State:{tenant}:{syear}:{pk}',
            'note' => 'Geography dimension',
        ],

        'tblstudent' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 83715,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Student',
            'uid' => 'Student:{tenant}:{syear}:{pk}',
            'note' => '83,715 rows across 48 tenants. **PII** — fold all demographics here; do NOT create :StuDetail (defect D5)',
        ],

        'tblstudent_bank_detail' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 4933,
            'reason' => 'Bank PII, no traversal value',
        ],

        'tblstudent_bank_detail_log' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 417,
            'reason' => 'Audit log',
        ],

        'tblstudent_document' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 66492,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'document_count on :Student',
            'note' => '66,492 rows of file metadata — projecting as nodes duplicates PII for zero traversal value',
        ],

        'tblstudent_doc_std_mapping' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 19645,
            'reason' => 'Document/standard join, metadata only',
        ],

        'tblstudent_enrollment' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 176305,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'ENROLLED_IN',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Standard',
                'key' => 'standard_id',
            ],
            'note' => '`section_id` is the division FK',
        ],

        'tblstudent_family_history' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 10279,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Guardian',
            'uid' => 'Guardian:{tenant}:{syear}:{pk}',
            'note' => '10,279 rows — parent/guardian records. **PII**',
        ],

        'tblstudent_fees_failure' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 16786,
            'reason' => '16,786-row failure log',
        ],

        'tblstudent_parent_feedback' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 6,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'GAVE_FEEDBACK',
            'from' => [
                'label' => 'Guardian',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Institute',
                'key' => 'sub_institute_id',
            ],
            'note' => '6 rows',
        ],

        'tblstudent_past_education' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 5448,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :Student',
            'note' => '',
        ],

        'tblstudent_payment_method_mapping' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 175268,
            'reason' => '175,268-row ledger, q5. Payment-method history — MariaDB answers this',
        ],

        'tblstudent_siblings' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'A',
            'decision' => 'EDGE',
            'rows_at_classification' => 2,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'SIBLING_OF',
            'from' => [
                'label' => 'Student',
                'key' => NULL,
            ],
            'to' => [
                'label' => 'Student',
                'key' => NULL,
            ],
            'needs_endpoint_keys' => true,
            'note' => '2 rows',
        ],

        'tblstudent_tc_details' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => 7,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 7470,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'transfer-certificate fields on :Student',
            'note' => '',
        ],

        'tbluser' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 4763,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Staff',
            'uid' => 'Staff:{tenant}:{syear}:{pk}',
            'note' => '4,763 rows. **PII**',
        ],

        'tbluserprofilemaster' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => 4,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 596,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Role',
            'uid' => 'Role:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'tbluser_contact_details' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 1,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'target' => 'on :Staff',
            'note' => '',
        ],

        'tbluser_past_education' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'tbluser_shift_master' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 3,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Shift',
            'uid' => 'Shift:{tenant}:{syear}:{pk}',
            'note' => '3 rows',
        ],

        'tbluser_shift_records' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty',
        ],

        'teacher_mobile_homescreen' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1641,
            'reason' => 'UI config',
        ],

        'template_master' => [
            'module' => 'foundation',
            'domain' => 'Foundation',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 72,
            'reason' => 'Print templates',
        ],

        'temp_signup' => [
            'module' => 'people',
            'domain' => 'Student',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 612,
            'reason' => 'Signup staging',
        ],

        'timetable' => [
            'module' => 'hr',
            'domain' => 'Timetable',
            'phase' => 10,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 102652,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'TEACHES',
            'from' => [
                'label' => 'Staff',
                'key' => 'teacher_id',
            ],
            'to' => [
                'label' => 'Subject',
                'key' => 'subject_id',
            ],
            'note' => '102,652 rows. Full tenancy + teacher_id/subject_id/period_id/division_id',
        ],

        'topic_master' => [
            'module' => 'curriculum',
            'domain' => 'Curriculum',
            'phase' => 5,
            'tier' => 'A',
            'decision' => 'NODE',
            'rows_at_classification' => 13561,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Topic',
            'uid' => 'Topic:{tenant}:{syear}:{pk}',
            'note' => '99.4% of chapter_id values dangle — see F1',
        ],

        'transport_driver_detail' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 738,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Driver',
            'uid' => 'Driver:{tenant}:{syear}:{pk}',
            'note' => '**PII**',
        ],

        'transport_kilometer_rate' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'PROP',
            'rows_at_classification' => 388,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'target' => 'rate on :Route',
            'note' => '',
        ],

        'transport_map_student' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 30300,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'BOARDS_AT',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'Stop',
                'key' => 'from_stop',
            ],
            'note' => '30,300 rows. **Two edges per row** — from_stop and to_stop',
        ],

        'transport_route' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 379,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Route',
            'uid' => 'Route:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'transport_route_bus' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 1822,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'SERVES',
            'from' => [
                'label' => 'Vehicle',
                'key' => 'bus_id',
            ],
            'to' => [
                'label' => 'Route',
                'key' => 'route_id',
            ],
            'note' => '',
        ],

        'transport_route_stop' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 2794,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'HAS_STOP',
            'from' => [
                'label' => 'Route',
                'key' => 'route_id',
            ],
            'to' => [
                'label' => 'Stop',
                'key' => 'stop_id',
            ],
            'note' => '',
        ],

        'transport_school_shift' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 25,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Shift',
            'uid' => 'Shift:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'transport_stop' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 2810,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'label' => 'Stop',
            'uid' => 'Stop:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'transport_vehicle' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 690,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Vehicle',
            'uid' => 'Vehicle:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'transport_vehicle_type' => [
            'module' => 'operations',
            'domain' => 'Transport',
            'phase' => 12,
            'tier' => 'C',
            'decision' => 'NODE',
            'rows_at_classification' => 3,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'global',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'VehicleType',
            'uid' => 'VehicleType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'upload_result' => [
            'module' => 'result',
            'domain' => 'Result',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1948,
            'reason' => 'Import staging log',
        ],

        'users' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Laravel auth table, 1 row',
        ],

        'user_activities' => [
            'module' => 'platform',
            'domain' => 'Platform',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'EDGE',
            'rows_at_classification' => 24,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'rel' => 'VIEWED',
            'from' => [
                'label' => 'Staff',
                'key' => 'user_id',
            ],
            'to' => [
                'label' => 'Content',
                'key' => 'content_id',
            ],
            'note' => '24 rows — content view log',
        ],

        'user_experience_details' => [
            'module' => 'hr',
            'domain' => 'Staff',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Empty prior-employment table',
        ],

        'visitor_master' => [
            'module' => 'operations',
            'domain' => 'Visitor',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 867,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'Visitor',
            'uid' => 'Visitor:{tenant}:{syear}:{pk}',
            'note' => '**PII**',
        ],

        'visitor_master_settings' => [
            'module' => 'operations',
            'domain' => 'Visitor',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Config',
        ],

        'visitor_type' => [
            'module' => 'operations',
            'domain' => 'Visitor',
            'phase' => 12,
            'tier' => 'B',
            'decision' => 'NODE',
            'rows_at_classification' => 29,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'constant',
                'value' => 0,
            ],
            'label' => 'VisitorType',
            'uid' => 'VisitorType:{tenant}:{syear}:{pk}',
            'note' => '',
        ],

        'whatapp_user_details' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Gateway config (note the typo)',
        ],

        'whatsapp_sent_messages' => [
            'module' => 'platform',
            'domain' => 'Communication',
            'phase' => 14,
            'tier' => 'B',
            'decision' => 'AGG_EDGE',
            'rows_at_classification' => 11153,
            'pk' => 'id',
            'tenant' => [
                'mode' => 'column',
                'column' => 'sub_institute_id',
            ],
            'syear' => [
                'mode' => 'column',
                'column' => 'syear',
            ],
            'rel' => 'COMMUNICATION',
            'from' => [
                'label' => 'Student',
                'key' => 'student_id',
            ],
            'to' => [
                'label' => 'AcademicYear',
                'key' => 'syear',
            ],
            'aggregate' => [
                'group_by' => NULL,
                'note' => 'GROUP BY must be written in the export SQL (L4)',
            ],
            'note' => '11,153 rows',
        ],

        'wk_condition' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'wk_execute_schedule' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'wk_log' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'wk_mail' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'wk_main' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'wk_module' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'wk_sms' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'wk_updatequery' => [
            'module' => 'platform',
            'domain' => 'Workflow',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Workflow-engine table, empty (wk_main has 76 code refs but 0 rows)',
        ],

        'Z_customer_registrations' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 3,
            'reason' => 'Prefixed-Z scratch table, 0 refs',
        ],

        'Z_donarDetails' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 17,
            'reason' => 'Prefixed-Z scratch table',
        ],

        'Z_employee_details' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 0,
            'reason' => 'Prefixed-Z scratch table, empty',
        ],

        'Z_Seminar' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Prefixed-Z scratch table',
        ],

        'Z_Student_Details' => [
            'module' => 'platform',
            'domain' => 'NotK12',
            'phase' => NULL,
            'tier' => 'D',
            'decision' => 'EXCLUDE',
            'rows_at_classification' => 1,
            'reason' => 'Prefixed-Z scratch table',
        ],
    ],
];
