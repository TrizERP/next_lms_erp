<?php

namespace App\Services\Graph;

use InvalidArgumentException;

/**
 * The whitelist of node labels, their unique key properties, and the
 * relationship types the live-sync pipeline is allowed to write.
 *
 * WHY A WHITELIST AND NOT A LOOKUP. Neo4j cannot parameterise a label or a
 * relationship type — they have to be interpolated into the Cypher string. Any
 * value reaching that interpolation from `sync_log.table_name` or
 * `neo4j_sync_queue.rel_type` is therefore an injection vector. Sanitising with
 * a regex (as the migration report's skeleton does) stops syntax breakage but
 * still lets an attacker who can write an outbox row pick ANY label in the
 * graph. Matching against this fixed map is what actually closes it.
 *
 * KEYS COME FROM THE LIVE CONSTRAINTS, NOT FROM GUESSWORK. Every entry below
 * mirrors a `REQUIRE ... IS UNIQUE` constraint in the k12 ingest script and was
 * re-verified against the running database on 2026-08-17.
 *
 * NOTE: the migration report's drain skeleton MERGEs on `{id: $id}`. That is
 * WRONG for this graph — no node uses `id` as its key — and running it would
 * mint a parallel set of nodes that the 5,409 existing :Student and every
 * ENROLLED_IN edge would not match. Hence this map.
 */
class GraphSchema
{
    /**
     * label => [
     *   'key'        => unique property the label is MERGEd on,
     *   'uid_syear'  => uid fallback: the syear segment, or null for no fallback,
     *   'uid_tenant' => 'node'   -> take tenant from the related node
     *                   'global' -> tenant segment is always 0
     * ]
     *
     * The uid fallback exists because the graph carries TWO key conventions.
     * Measured 2026-08-17 — legacy / uid node counts:
     *   Standard 48/826 · Subject 830/1961 · Chapter 7533/5625 · Concept 9/1372
     *   Curriculum 31/10 · Unit 6/60 · Misconception 1/2 · Lesson 719/1807
     * Labels with no uid column (Student, StuDetail, Result, Question,
     * Assessment, Teacher, ...) are legacy-only and need no fallback.
     *
     * Lesson is deliberately fallback-less: its uid is YEAR-scoped
     * (`Lesson:195:2023:1`), and neither outbox table carries a syear, so a
     * fallback could only be built by guessing the year. Better to create no
     * edge than a wrong one.
     */
    private const LABELS = [
        'StuDetail'           => ['key' => 'sdId',                 'uid_syear' => null, 'uid_tenant' => null],
        'Student'             => ['key' => 'stuId',                'uid_syear' => null, 'uid_tenant' => null],
        'Standard'            => ['key' => 'stId',                 'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Subject'             => ['key' => 'subId',                'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Chapter'             => ['key' => 'chId',                 'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Concept'             => ['key' => 'conceptId',            'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Curriculum'          => ['key' => 'curriculumId',         'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Unit'                => ['key' => 'unitId',               'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Misconception'       => ['key' => 'misconceptionId',      'uid_syear' => 0,    'uid_tenant' => 'global'],
        'Lesson'              => ['key' => 'lessonId',             'uid_syear' => null, 'uid_tenant' => null],
        'Assessment'          => ['key' => 'assId',                'uid_syear' => null, 'uid_tenant' => null],
        'Result'              => ['key' => 'resultId',             'uid_syear' => null, 'uid_tenant' => null],
        'Question'            => ['key' => 'qId',                  'uid_syear' => null, 'uid_tenant' => null],
        'Teacher'             => ['key' => 'teacherId',            'uid_syear' => null, 'uid_tenant' => null],
        'LearningContent'     => ['key' => 'contentId',            'uid_syear' => null, 'uid_tenant' => null],
        'LearningObjects'     => ['key' => 'learningobjectId',     'uid_syear' => null, 'uid_tenant' => null],
        'CompetencyStandards' => ['key' => 'competencystandardsId','uid_syear' => null, 'uid_tenant' => null],
        'ChapterStandardMap'  => ['key' => 'chapterstandardmapId', 'uid_syear' => null, 'uid_tenant' => null],
        'AssessmentTypology'  => ['key' => 'assessmenttypologyId', 'uid_syear' => null, 'uid_tenant' => null],

        // ------------------------------------------------------------------
        // Added 2026-09-04 with the eight module scripts. See
        // docs/neo4j-graph-modules.md.
        // ------------------------------------------------------------------

        // -- uid-only parents ------------------------------------------------
        // These have NO native key: the batch pipeline created them and every
        // one is keyed `Label:<tenant>:0:<id>`. The `key` below therefore never
        // matches anything, which is deliberate — the legacy MATCH finds
        // nothing and the uid fallback resolves the node. Naming a key anyway
        // keeps one code path for every label instead of a special case.
        'Institute'           => ['key' => 'instituteId',          'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Division'            => ['key' => 'divisionId',           'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Department'          => ['key' => 'departmentId',         'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Role'                => ['key' => 'roleId',               'uid_syear' => 0,    'uid_tenant' => 'node'],
        'GradeScheme'         => ['key' => 'gradeschemeId',        'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Content'             => ['key' => 'contentNodeId',        'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Topic'               => ['key' => 'topicId',              'uid_syear' => 0,    'uid_tenant' => 'node'],
        'MappingType'         => ['key' => 'mappingtypeId',        'uid_syear' => 0,    'uid_tenant' => 'global'],
        // AcademicYear and Period are YEAR-scoped (`AcademicYear:1:2024:5`), and
        // neither outbox table carries a syear — the same reason :Lesson has no
        // fallback. Every edge this work builds onto them is an aggregate, which
        // is recomputed by `neo4j:refresh-aggregates` rather than trigger-synced.
        'AcademicYear'        => ['key' => 'academicyearId',       'uid_syear' => null, 'uid_tenant' => null],
        'Period'              => ['key' => 'periodId',             'uid_syear' => null, 'uid_tenant' => null],

        // -- hr ---------------------------------------------------------------
        // :Staff falls back to :Teacher. `tbluser` is one table and a person is
        // one node, but the reference script had already claimed 118 of those
        // rows as :Teacher, so :Staff holds the other 4,653. An HR edge naming
        // tbluser.id must reach whichever label actually holds that person —
        // without the sibling it would silently drop every edge for those 118.
        'Staff'               => ['key' => 'staffId',              'uid_syear' => null, 'uid_tenant' => null, 'sibling' => 'Teacher'],
        'Holiday'             => ['key' => 'holidayId',            'uid_syear' => null, 'uid_tenant' => null],
        'LeaveType'           => ['key' => 'leavetypeId',          'uid_syear' => null, 'uid_tenant' => null],
        'PayrollType'         => ['key' => 'payrolltypeId',        'uid_syear' => null, 'uid_tenant' => null],
        'StaffShift'          => ['key' => 'staffshiftId',         'uid_syear' => null, 'uid_tenant' => null],
        'SalaryStructure'     => ['key' => 'salarystructureId',    'uid_syear' => null, 'uid_tenant' => null],
        'SalaryCertificate'   => ['key' => 'salarycertificateId',  'uid_syear' => null, 'uid_tenant' => null],

        // -- result -----------------------------------------------------------
        'Examination'         => ['key' => 'examinationId',        'uid_syear' => null, 'uid_tenant' => null],
        'ExamType'            => ['key' => 'examtypeId',           'uid_syear' => null, 'uid_tenant' => null],
        'ExamTypeCategory'    => ['key' => 'examtypecategoryId',   'uid_syear' => null, 'uid_tenant' => null],
        'Grade'               => ['key' => 'gradeId',              'uid_syear' => null, 'uid_tenant' => null],
        'CoScholasticArea'    => ['key' => 'coscholasticareaId',   'uid_syear' => null, 'uid_tenant' => null],
        'CoScholasticParent'  => ['key' => 'coscholasticparentId', 'uid_syear' => null, 'uid_tenant' => null],
        'CoScholasticGradeBand' => ['key' => 'coscholasticgradebandId', 'uid_syear' => null, 'uid_tenant' => null],
        'Skillset'            => ['key' => 'skillsetId',           'uid_syear' => null, 'uid_tenant' => null],
        'Activity'            => ['key' => 'activityId',           'uid_syear' => null, 'uid_tenant' => null],
        'ActivityGroup'       => ['key' => 'activitygroupId',      'uid_syear' => null, 'uid_tenant' => null],
        'RemarkTemplate'      => ['key' => 'remarktemplateId',     'uid_syear' => null, 'uid_tenant' => null],
        'ExamSchedule'        => ['key' => 'examscheduleId',       'uid_syear' => null, 'uid_tenant' => null],

        // -- assessment -------------------------------------------------------
        'QuestionType'        => ['key' => 'questiontypeId',       'uid_syear' => null, 'uid_tenant' => null],
        'CounsellingCourse'   => ['key' => 'counsellingcourseId',  'uid_syear' => null, 'uid_tenant' => null],
        'CounsellingQuestion' => ['key' => 'counsellingquestionId','uid_syear' => null, 'uid_tenant' => null],
        'CounsellingResult'   => ['key' => 'counsellingresultId',  'uid_syear' => null, 'uid_tenant' => null],
        'OfflineExam'         => ['key' => 'offlineexamId',        'uid_syear' => null, 'uid_tenant' => null],
        'MbtiPaper'           => ['key' => 'mbtipaperId',          'uid_syear' => null, 'uid_tenant' => null],

        // -- operations -------------------------------------------------------
        'Book'                => ['key' => 'bookId',               'uid_syear' => null, 'uid_tenant' => null],
        'BookCopy'            => ['key' => 'bookcopyId',           'uid_syear' => null, 'uid_tenant' => null],
        'Route'               => ['key' => 'routeId',              'uid_syear' => null, 'uid_tenant' => null],
        'Stop'                => ['key' => 'stopId',               'uid_syear' => null, 'uid_tenant' => null],
        'Vehicle'             => ['key' => 'vehicleId',            'uid_syear' => null, 'uid_tenant' => null],
        'VehicleType'         => ['key' => 'vehicletypeId',        'uid_syear' => null, 'uid_tenant' => null],
        'Driver'              => ['key' => 'driverId',             'uid_syear' => null, 'uid_tenant' => null],
        'TransportShift'      => ['key' => 'transportshiftId',     'uid_syear' => null, 'uid_tenant' => null],
        'Hostel'              => ['key' => 'hostelId',             'uid_syear' => null, 'uid_tenant' => null],
        'HostelBuilding'      => ['key' => 'hostelbuildingId',     'uid_syear' => null, 'uid_tenant' => null],
        'HostelFloor'         => ['key' => 'hostelfloorId',        'uid_syear' => null, 'uid_tenant' => null],
        'HostelRoom'          => ['key' => 'hostelroomId',         'uid_syear' => null, 'uid_tenant' => null],
        'HostelType'          => ['key' => 'hosteltypeId',         'uid_syear' => null, 'uid_tenant' => null],
        'RoomType'            => ['key' => 'roomtypeId',           'uid_syear' => null, 'uid_tenant' => null],
        'HostelVisitor'       => ['key' => 'hostelvisitorId',      'uid_syear' => null, 'uid_tenant' => null],
        'InventoryItem'       => ['key' => 'inventoryitemId',      'uid_syear' => null, 'uid_tenant' => null],
        'ItemCategory'        => ['key' => 'itemcategoryId',       'uid_syear' => null, 'uid_tenant' => null],
        'ItemSubCategory'     => ['key' => 'itemsubcategoryId',    'uid_syear' => null, 'uid_tenant' => null],
        'ItemType'            => ['key' => 'itemtypeId',           'uid_syear' => null, 'uid_tenant' => null],
        'Vendor'              => ['key' => 'vendorId',             'uid_syear' => null, 'uid_tenant' => null],
        'FileLocation'        => ['key' => 'filelocationId',       'uid_syear' => null, 'uid_tenant' => null],
        'Visitor'             => ['key' => 'visitorId',            'uid_syear' => null, 'uid_tenant' => null],
        'VisitorType'         => ['key' => 'visitortypeId',        'uid_syear' => null, 'uid_tenant' => null],
        'InwardDocument'      => ['key' => 'inwarddocumentId',     'uid_syear' => null, 'uid_tenant' => null],
        'OutwardDocument'     => ['key' => 'outwarddocumentId',    'uid_syear' => null, 'uid_tenant' => null],
        'FrontDeskEntry'      => ['key' => 'frontdeskentryId',     'uid_syear' => null, 'uid_tenant' => null],
        'Complaint'           => ['key' => 'complaintId',          'uid_syear' => null, 'uid_tenant' => null],
        'Circular'            => ['key' => 'circularId',           'uid_syear' => null, 'uid_tenant' => null],
        'CircularType'        => ['key' => 'circulartypeId',       'uid_syear' => null, 'uid_tenant' => null],
        'Announcement'        => ['key' => 'announcementId',       'uid_syear' => null, 'uid_tenant' => null],

        // -- finance (every node also carries authoritative:false) -------------
        'FeeHead'             => ['key' => 'feeheadId',            'uid_syear' => null, 'uid_tenant' => null],
        'FeeTitle'            => ['key' => 'feetitleId',           'uid_syear' => null, 'uid_tenant' => null],
        'FeeTitleMaster'      => ['key' => 'feetitlemasterId',     'uid_syear' => null, 'uid_tenant' => null],
        'FeeOtherHead'        => ['key' => 'feeotherheadId',       'uid_syear' => null, 'uid_tenant' => null],
        'FeeConfig'           => ['key' => 'feeconfigId',          'uid_syear' => null, 'uid_tenant' => null],
        'LateFeeRule'         => ['key' => 'latefeeruleId',        'uid_syear' => null, 'uid_tenant' => null],
        'FeeMonth'            => ['key' => 'feemonthId',           'uid_syear' => null, 'uid_tenant' => null],
        'FeeCircular'         => ['key' => 'feecircularId',        'uid_syear' => null, 'uid_tenant' => null],
        'FeeCancelType'       => ['key' => 'feecanceltypeId',      'uid_syear' => null, 'uid_tenant' => null],
        'Bank'                => ['key' => 'bankId',               'uid_syear' => null, 'uid_tenant' => null],
        'ReceiptBook'         => ['key' => 'receiptbookId',        'uid_syear' => null, 'uid_tenant' => null],
        'PettyCashHead'       => ['key' => 'pettycashheadId',      'uid_syear' => null, 'uid_tenant' => null],
        'Donation'            => ['key' => 'donationId',           'uid_syear' => null, 'uid_tenant' => null],

        // -- skills / SQAA / O*NET --------------------------------------------
        'Skill'               => ['key' => 'skillId',              'uid_syear' => null, 'uid_tenant' => null],
        'JobRole'             => ['key' => 'jobroleId',            'uid_syear' => null, 'uid_tenant' => null],
        'JobTask'             => ['key' => 'jobtaskKey',           'uid_syear' => null, 'uid_tenant' => null],
        'Industry'            => ['key' => 'industryId',           'uid_syear' => null, 'uid_tenant' => null],
        'SkillAssessment'     => ['key' => 'skillassessmentId',    'uid_syear' => null, 'uid_tenant' => null],
        'SQAAStandard'        => ['key' => 'sqaastandardId',       'uid_syear' => null, 'uid_tenant' => null],
        'SQAADocument'        => ['key' => 'sqaadocumentId',       'uid_syear' => null, 'uid_tenant' => null],
        'OnetOccupation'      => ['key' => 'onetsocCode',          'uid_syear' => null, 'uid_tenant' => null],
        'OnetElement'         => ['key' => 'elementId',            'uid_syear' => null, 'uid_tenant' => null],
        'OnetScale'           => ['key' => 'scaleId',              'uid_syear' => null, 'uid_tenant' => null],
        'OnetTask'            => ['key' => 'taskId',               'uid_syear' => null, 'uid_tenant' => null],
        'JobZone'             => ['key' => 'jobzoneId',            'uid_syear' => null, 'uid_tenant' => null],
        'UnspscCategory'      => ['key' => 'commodityCode',        'uid_syear' => null, 'uid_tenant' => null],
        'CareerCluster'       => ['key' => 'careerclusterId',      'uid_syear' => null, 'uid_tenant' => null],
        'WorkContextCategory' => ['key' => 'workcontextcategoryKey','uid_syear' => null, 'uid_tenant' => null],

        // -- platform ----------------------------------------------------------
        'CalendarEvent'       => ['key' => 'calendareventId',      'uid_syear' => null, 'uid_tenant' => null],
        'Task'                => ['key' => 'taskId',               'uid_syear' => null, 'uid_tenant' => null],
        'TimeSlot'            => ['key' => 'timeslotId',           'uid_syear' => null, 'uid_tenant' => null],
        'LeaderboardRule'     => ['key' => 'leaderboardruleId',    'uid_syear' => null, 'uid_tenant' => null],
    ];

    /**
     * Relationship types the sync may write. Everything the k12 ingest script
     * authors, plus the four already present in neo4j_sync_queue's history.
     *
     * HAS_CONCEPT is not in the ingest script but is the edge the data actually
     * supports: `lms_concept` has `chapter_id` and no `lesson_id`, so the
     * script's `(:Lesson)-[:COVERS]->(:Concept)` could never be built here. The
     * live graph shows the result — 1,372 `(:Chapter)-[:HAS_CONCEPT]->(:Concept)`
     * against 9 COVERS. Kept alongside COVERS rather than replacing it, so the
     * handful of genuine COVERS edges stay writable.
     */
    private const RELATIONSHIPS = [
        'HAS_STUDENT', 'ENROLLED_IN', 'HAS_SUBJECT', 'HAS_ASSESSMENT',
        'HAS_RESULT', 'FOR_ASSESSMENT', 'HAS_QUESTION', 'BELONGS_TO',
        'HAS_CHAPTER', 'HAS_LESSON', 'HAS_UNIT', 'COVERS', 'ASSESSES',
        'ASSESSES_CHAPTER', 'OCCURS_IN', 'TEACHES', 'REMEDIATES',
        'ATTEMPTED', 'ATTENDED', 'MASTERS', 'HAS_MISCONCEPTION',
        'INCLUDES', 'BELONGS_TO_CURRICULUM', 'PREREQUISITE_OF',
        'HAS_CONCEPT',

        // ------------------------------------------------------------------
        // Added 2026-09-04 with the eight module scripts. Every one of these is
        // a NEW type — none overloads a type the reference layer already uses,
        // which is what lets the drain write them without the protected counts
        // moving. See docs/neo4j-graph-modules.md §3.
        // ------------------------------------------------------------------

        // people
        'IN_DIVISION', 'STUDIES', 'SIBLING_OF', 'GUARDIAN_OF', 'HAS_INCIDENT',

        // hr
        'HAS_ROLE', 'IN_DEPARTMENT', 'WORKS_AT', 'REPORTS_TO', 'HAS_HOLIDAY',
        'TOOK_LEAVE', 'ALLOCATED_LEAVE', 'APPLIED_FOR_LEAVE',
        'HAS_SALARY_STRUCTURE', 'HAS_SALARY_CERTIFICATE', 'MAPPED_TO',
        'CLASS_TEACHER_OF', 'TEACHES_SUBJECT', 'TEACHES_CLASS', 'SCHEDULED',
        'TEACHES_SUBJECT_DECLARED', 'SUBSTITUTED_FOR', 'ATTENDANCE_MONTH',
        'DEDUCTION', 'PAYROLL_YEAR',

        // result
        'OF_EXAM_TYPE', 'HAS_EXAMINATION', 'EXAMINES_SUBJECT', 'IN_PART',
        'HAS_GRADE_BAND', 'IN_SKILLSET', 'HAS_GRADE', 'USES_GRADE_SCHEME',
        'HAS_EXAM_SCHEDULE', 'SCORED', 'REPORTCARD',

        // assessment
        'TAGGED_AS', 'OF_QUESTION_TYPE', 'HAS_COUNSELLING_QUESTION',
        'FOR_COURSE', 'TOOK_COUNSELLING_TEST', 'HAS_OFFLINE_EXAM',
        'FOR_OFFLINE_ASSESSMENT', 'ASSIGNED_EXAM', 'MASTERS_CHAPTER',

        // operations
        'COPY_OF', 'BORROWED', 'HAS_STOP', 'SERVES', 'DRIVEN_BY',
        'OF_VEHICLE_TYPE', 'RUNS_IN_SHIFT', 'BOARDS_AT', 'OF_HOSTEL_TYPE',
        'HAS_BUILDING', 'HAS_FLOOR', 'HAS_ROOM', 'ALLOCATED_ROOM',
        'IN_CATEGORY', 'IN_SUB_CATEGORY', 'OF_ITEM_TYPE', 'UNDER_CATEGORY',
        'REQUISITIONED', 'ALLOCATED_ITEM', 'SUPPLIED', 'PURCHASE_ORDER',
        'QUOTED', 'FILED_AT', 'OF_VISITOR_TYPE', 'OF_CIRCULAR_TYPE',
        'HAS_INWARD', 'HAS_OUTWARD', 'HAS_VISITOR', 'HAS_COMPLAINT',
        'HAS_CIRCULAR', 'HAS_ANNOUNCEMENT', 'HAS_HOSTEL', 'HAS_ROUTE',
        'CIRCULATED_TO',

        // finance
        'OF_TITLE_MASTER', 'APPLIES_TO_STANDARD', 'FOR_STANDARD', 'FEE_YEAR',
        'RECEIVED_DONATION', 'APPLIES_TO', 'LIABLE_FOR', 'PAID', 'PAID_FEES',
        'PETTY_CASH',

        // skills / SQAA / O*NET
        'REQUIRES_SKILL', 'INVOLVES_TASK', 'HAS_SKILL', 'PARENT_OF_STANDARD',
        'REQUIRES_DOCUMENT', 'SUBMITTED', 'SCORED_SQAA', 'IN_JOB_ZONE',
        'CLUSTERS_OCCUPATION', 'HAS_TASK', 'FOR_ELEMENT',
        'REQUIRES_SKILL_ELEMENT', 'REQUIRES_ABILITY', 'REQUIRES_KNOWLEDGE',
        'INVOLVES_ACTIVITY', 'HAS_WORK_STYLE', 'HAS_INTEREST',
        'HAS_WORK_VALUE', 'HAS_WORK_CONTEXT', 'PERFORMS_TASK',
        'USES_TECHNOLOGY', 'USES_TOOL',

        // platform
        'HAS_CALENDAR_EVENT', 'ASSIGNED_TASK', 'CREATED_BY', 'BOOKED_PTM',
        'IN_SLOT', 'VIEWED', 'COMMUNICATION', 'SENT_COMMUNICATION',
        'EARNED_POINTS',
    ];

    public static function knowsLabel(string $label): bool
    {
        return isset(self::LABELS[$label]);
    }

    public static function knowsRelationship(string $type): bool
    {
        return in_array($type, self::RELATIONSHIPS, true);
    }

    /** The unique property this label is MERGEd on. */
    public static function key(string $label): string
    {
        self::assertLabel($label);

        return self::LABELS[$label]['key'];
    }

    /** True when this label also has uid-keyed nodes worth falling back to. */
    public static function hasUidFallback(string $label): bool
    {
        self::assertLabel($label);

        return self::LABELS[$label]['uid_syear'] !== null;
    }

    /**
     * The other label that may hold the same real-world entity, or null.
     *
     * Only :Staff has one. `tbluser` is a single table and a person should be a
     * single node, but the reference ingest had already claimed 118 of those
     * rows as :Teacher before :Staff existed, so :Staff deliberately holds only
     * the other 4,653. An HR edge carries `tbluser.id` and cannot know which of
     * the two labels that person landed in; without this the drain would look
     * only under :Staff and silently drop every edge belonging to those 118.
     *
     * This is NOT a uid fallback — both labels are natively keyed, on different
     * property names — so it is resolved by picking the label before the Cypher
     * is built, rather than by a coalesce inside it.
     */
    public static function siblingOf(string $label): ?string
    {
        self::assertLabel($label);

        return self::LABELS[$label]['sibling'] ?? null;
    }

    /**
     * Cypher expression that builds the uid for this label, given a Cypher
     * variable holding the tenant. Returns null when there is no fallback.
     *
     * e.g. Standard, tenant from `s`  ->
     *   'Standard:' + toString(s.sub_institute_id) + ':0:' + toString($tid)
     */
    public static function uidExpression(string $label, string $tenantVar, string $idParam): ?string
    {
        self::assertLabel($label);
        $spec = self::LABELS[$label];

        if ($spec['uid_syear'] === null) {
            return null;
        }

        $tenant = $spec['uid_tenant'] === 'global'
            ? "'0'"
            : "toString({$tenantVar}.sub_institute_id)";

        return "'{$label}:' + {$tenant} + ':{$spec['uid_syear']}:' + toString(\${$idParam})";
    }

    /**
     * Guard before a label is interpolated into Cypher.
     *
     * @throws InvalidArgumentException
     */
    public static function assertLabel(string $label): void
    {
        if (! isset(self::LABELS[$label])) {
            throw new InvalidArgumentException("Unknown graph label '{$label}' — refusing to interpolate it into Cypher");
        }
    }

    /**
     * Guard before a relationship type is interpolated into Cypher.
     *
     * @throws InvalidArgumentException
     */
    public static function assertRelationship(string $type): void
    {
        if (! in_array($type, self::RELATIONSHIPS, true)) {
            throw new InvalidArgumentException("Unknown relationship type '{$type}' — refusing to interpolate it into Cypher");
        }
    }
}
