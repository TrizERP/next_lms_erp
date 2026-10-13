<?php

namespace App\Services\Graph;

use RuntimeException;

/**
 * Phase 7.1 — the family layer on top of GraphSchema's whitelist.
 *
 * GraphSchema is K-12's real vocabulary: it already whitelists every label and
 * relationship the sync pipeline may write, keyed to live Neo4j constraints.
 * This file does NOT replace or duplicate it — it exists because GraphSchema's
 * whitelist has no notion of what a label IS, only what it may be MERGEd as.
 * hpbrain_backend's GraphVocabulary::LABEL_FAMILY groups 14 labels into 5
 * families so its Graph Explorer can filter/colour by kind rather than by
 * label; K-12's ~116 labels need the same layer, reusing the same family
 * NAMES where the concept is shared (organization, people, student, academic)
 * and adding families for the domains K-12 covers that EB does not
 * (assessment, hr_ops, operations, finance, skills, platform). K-12 has no
 * `intelligence` family — none of GraphSchema's labels are Signal/Evidence/
 * Case/Recommendation/Decision; that loop runs over MySQL in
 * app/Brain/Intelligence, not over this Neo4j graph.
 *
 * WHY A GUARD, NOT JUST A MAP. The dead "EB Phase-1" port this replaces
 * (app/Brain/Intelligence/GraphVocabulary.php, deleted 2026-10-06) declared
 * its own 12-label family map that never validated itself against anything,
 * so it silently fell behind GraphSchema's real label set and was never
 * caught. assertComplete() below throws if GraphSchema ever gains a label
 * this file hasn't categorised, so that drift fails loudly instead of again
 * going unnoticed for a month.
 */
class GraphVocabulary
{
    public const FAMILY_ORGANIZATION = 'organization';

    public const FAMILY_PEOPLE = 'people';

    public const FAMILY_STUDENT = 'student';

    public const FAMILY_ACADEMIC = 'academic';

    /** Exams, results, questions, co-scholastic and counselling records. */
    public const FAMILY_ASSESSMENT = 'assessment';

    /** Staff-operational records (leave, payroll, shifts) — distinct from the Teacher/Staff node itself. */
    public const FAMILY_HR_OPS = 'hr_ops';

    /** Transport, hostel, library, inventory, front-desk, visitor, circulars. */
    public const FAMILY_OPERATIONS = 'operations';

    public const FAMILY_FINANCE = 'finance';

    /** O*NET / SQAA / skill-taxonomy labels — K-12's own skills graph, not G2G's employee competency ratings. */
    public const FAMILY_SKILLS = 'skills';

    /** Calendar, tasks, communication, gamification. */
    public const FAMILY_PLATFORM = 'platform';

    public const LABEL_FAMILY = [
        // -- organization --------------------------------------------------
        'Institute' => self::FAMILY_ORGANIZATION,
        'Division' => self::FAMILY_ORGANIZATION,
        'Department' => self::FAMILY_ORGANIZATION,
        'Role' => self::FAMILY_ORGANIZATION,
        'GradeScheme' => self::FAMILY_ORGANIZATION,

        // -- student --------------------------------------------------------
        'StuDetail' => self::FAMILY_STUDENT,
        'Student' => self::FAMILY_STUDENT,

        // -- people -----------------------------------------------------------
        'Teacher' => self::FAMILY_PEOPLE,
        'Staff' => self::FAMILY_PEOPLE,

        // -- academic ---------------------------------------------------------
        'Standard' => self::FAMILY_ACADEMIC,
        'Subject' => self::FAMILY_ACADEMIC,
        'Chapter' => self::FAMILY_ACADEMIC,
        'Concept' => self::FAMILY_ACADEMIC,
        'Curriculum' => self::FAMILY_ACADEMIC,
        'Unit' => self::FAMILY_ACADEMIC,
        'Misconception' => self::FAMILY_ACADEMIC,
        'Lesson' => self::FAMILY_ACADEMIC,
        'CompetencyStandards' => self::FAMILY_ACADEMIC,
        'ChapterStandardMap' => self::FAMILY_ACADEMIC,
        'LearningContent' => self::FAMILY_ACADEMIC,
        'LearningObjects' => self::FAMILY_ACADEMIC,
        'Content' => self::FAMILY_ACADEMIC,
        'Topic' => self::FAMILY_ACADEMIC,
        'MappingType' => self::FAMILY_ACADEMIC,
        'AcademicYear' => self::FAMILY_ACADEMIC,
        'Period' => self::FAMILY_ACADEMIC,

        // -- assessment ---------------------------------------------------------
        'Assessment' => self::FAMILY_ASSESSMENT,
        'Result' => self::FAMILY_ASSESSMENT,
        'Question' => self::FAMILY_ASSESSMENT,
        'AssessmentTypology' => self::FAMILY_ASSESSMENT,
        'Examination' => self::FAMILY_ASSESSMENT,
        'ExamType' => self::FAMILY_ASSESSMENT,
        'ExamTypeCategory' => self::FAMILY_ASSESSMENT,
        'Grade' => self::FAMILY_ASSESSMENT,
        'CoScholasticArea' => self::FAMILY_ASSESSMENT,
        'CoScholasticParent' => self::FAMILY_ASSESSMENT,
        'CoScholasticGradeBand' => self::FAMILY_ASSESSMENT,
        'Skillset' => self::FAMILY_ASSESSMENT,
        'Activity' => self::FAMILY_ASSESSMENT,
        'ActivityGroup' => self::FAMILY_ASSESSMENT,
        'RemarkTemplate' => self::FAMILY_ASSESSMENT,
        'ExamSchedule' => self::FAMILY_ASSESSMENT,
        'QuestionType' => self::FAMILY_ASSESSMENT,
        'CounsellingCourse' => self::FAMILY_ASSESSMENT,
        'CounsellingQuestion' => self::FAMILY_ASSESSMENT,
        'CounsellingResult' => self::FAMILY_ASSESSMENT,
        'OfflineExam' => self::FAMILY_ASSESSMENT,
        'MbtiPaper' => self::FAMILY_ASSESSMENT,

        // -- hr_ops -------------------------------------------------------------
        'Holiday' => self::FAMILY_HR_OPS,
        'LeaveType' => self::FAMILY_HR_OPS,
        'PayrollType' => self::FAMILY_HR_OPS,
        'StaffShift' => self::FAMILY_HR_OPS,
        'SalaryStructure' => self::FAMILY_HR_OPS,
        'SalaryCertificate' => self::FAMILY_HR_OPS,

        // -- operations -----------------------------------------------------
        'Book' => self::FAMILY_OPERATIONS,
        'BookCopy' => self::FAMILY_OPERATIONS,
        'Route' => self::FAMILY_OPERATIONS,
        'Stop' => self::FAMILY_OPERATIONS,
        'Vehicle' => self::FAMILY_OPERATIONS,
        'VehicleType' => self::FAMILY_OPERATIONS,
        'Driver' => self::FAMILY_OPERATIONS,
        'TransportShift' => self::FAMILY_OPERATIONS,
        'Hostel' => self::FAMILY_OPERATIONS,
        'HostelBuilding' => self::FAMILY_OPERATIONS,
        'HostelFloor' => self::FAMILY_OPERATIONS,
        'HostelRoom' => self::FAMILY_OPERATIONS,
        'HostelType' => self::FAMILY_OPERATIONS,
        'RoomType' => self::FAMILY_OPERATIONS,
        'HostelVisitor' => self::FAMILY_OPERATIONS,
        'InventoryItem' => self::FAMILY_OPERATIONS,
        'ItemCategory' => self::FAMILY_OPERATIONS,
        'ItemSubCategory' => self::FAMILY_OPERATIONS,
        'ItemType' => self::FAMILY_OPERATIONS,
        'Vendor' => self::FAMILY_OPERATIONS,
        'FileLocation' => self::FAMILY_OPERATIONS,
        'Visitor' => self::FAMILY_OPERATIONS,
        'VisitorType' => self::FAMILY_OPERATIONS,
        'InwardDocument' => self::FAMILY_OPERATIONS,
        'OutwardDocument' => self::FAMILY_OPERATIONS,
        'FrontDeskEntry' => self::FAMILY_OPERATIONS,
        'Complaint' => self::FAMILY_OPERATIONS,
        'Circular' => self::FAMILY_OPERATIONS,
        'CircularType' => self::FAMILY_OPERATIONS,
        'Announcement' => self::FAMILY_OPERATIONS,

        // -- finance ----------------------------------------------------------
        'FeeHead' => self::FAMILY_FINANCE,
        'FeeTitle' => self::FAMILY_FINANCE,
        'FeeTitleMaster' => self::FAMILY_FINANCE,
        'FeeOtherHead' => self::FAMILY_FINANCE,
        'FeeConfig' => self::FAMILY_FINANCE,
        'LateFeeRule' => self::FAMILY_FINANCE,
        'FeeMonth' => self::FAMILY_FINANCE,
        'FeeCircular' => self::FAMILY_FINANCE,
        'FeeCancelType' => self::FAMILY_FINANCE,
        'Bank' => self::FAMILY_FINANCE,
        'ReceiptBook' => self::FAMILY_FINANCE,
        'PettyCashHead' => self::FAMILY_FINANCE,
        'Donation' => self::FAMILY_FINANCE,

        // -- skills -----------------------------------------------------------
        'Skill' => self::FAMILY_SKILLS,
        'JobRole' => self::FAMILY_SKILLS,
        'JobTask' => self::FAMILY_SKILLS,
        'Industry' => self::FAMILY_SKILLS,
        'SkillAssessment' => self::FAMILY_SKILLS,
        'SQAAStandard' => self::FAMILY_SKILLS,
        'SQAADocument' => self::FAMILY_SKILLS,
        'OnetOccupation' => self::FAMILY_SKILLS,
        'OnetElement' => self::FAMILY_SKILLS,
        'OnetScale' => self::FAMILY_SKILLS,
        'OnetTask' => self::FAMILY_SKILLS,
        'JobZone' => self::FAMILY_SKILLS,
        'UnspscCategory' => self::FAMILY_SKILLS,
        'CareerCluster' => self::FAMILY_SKILLS,
        'WorkContextCategory' => self::FAMILY_SKILLS,

        // -- platform ---------------------------------------------------------
        'CalendarEvent' => self::FAMILY_PLATFORM,
        'Task' => self::FAMILY_PLATFORM,
        'TimeSlot' => self::FAMILY_PLATFORM,
        'LeaderboardRule' => self::FAMILY_PLATFORM,
    ];

    public static function family(string $label): string
    {
        return self::LABEL_FAMILY[$label] ?? self::FAMILY_ORGANIZATION;
    }

    public static function isKnownLabel(string $label): bool
    {
        return isset(self::LABEL_FAMILY[$label]);
    }

    /** @return string[] every family the client may filter by */
    public static function families(): array
    {
        return array_values(array_unique(self::LABEL_FAMILY));
    }

    /**
     * Every label GraphSchema will MERGE must have a family here. Call this
     * from a test or a boot-time check, not per-request — it is the
     * anti-drift guard this file exists for, not hot-path validation.
     *
     * @throws RuntimeException naming every label missing a family
     */
    public static function assertComplete(): void
    {
        $missing = array_diff(GraphSchema::labels(), array_keys(self::LABEL_FAMILY));

        if ($missing !== []) {
            throw new RuntimeException(
                'GraphVocabulary::LABEL_FAMILY is missing a family for: ' . implode(', ', $missing)
                . ' — GraphSchema has moved ahead of this file.'
            );
        }
    }
}
