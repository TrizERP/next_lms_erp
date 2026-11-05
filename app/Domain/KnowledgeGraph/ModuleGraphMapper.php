<?php

namespace App\Domain\KnowledgeGraph;

use App\Services\Graph\GraphSchema;

/**
 * Maps a real ERP submodule (a `tblmenumaster` level-2 row — "Exam &
 * Assesment", "Fees Late Master", "Hostel"...) onto the GraphSchema labels
 * that actually represent it in Neo4j.
 *
 * There is no existing table linking tblmenumaster to GraphSchema — this is
 * that link, built once, by keyword against the submodule's own name (and,
 * where the name alone is too generic, its level-3 screens' names too). Every
 * value on the right is a real, live GraphSchema label; nothing here invents
 * a label or a relationship. A submodule whose name matches nothing returns
 * an empty label set — the caller renders "not mapped to the graph yet"
 * rather than guessing.
 *
 * Keyed by keyword rather than by submodule, because ~100+ submodules share
 * a much smaller set of real domains (fees, hostel, transport, exam...) —
 * one dictionary entry covers every submodule whose name contains that word,
 * instead of hand-listing every submodule individually.
 */
class ModuleGraphMapper
{
    /**
     * keyword => GraphSchema labels. Checked as a case-insensitive substring
     * of the submodule's name (and its screens' names, as a second pass).
     * Order matters only for readability; matches are unioned, not first-wins.
     */
    private const KEYWORD_LABELS = [
        // -- academic / curriculum / PAL --------------------------------------
        'lms' => ['Standard', 'Subject', 'Chapter', 'Topic', 'Concept', 'Unit', 'Content', 'TeachingResource'],
        // 'pal' / 'teach/learn' — PAL is LMS + PAL's own adaptive-learning engine over
        // this same curriculum spine (confirmed live: menu id 531's real name is "PAL",
        // not "LMS" — see GraphModuleBrowser::menuLabel()'s docblock). "teach/learn" is
        // the exact phrase, not the bare substring 'teach', so it never also matches an
        // unrelated "Teacher" (staff) submodule.
        'pal' => ['Standard', 'Subject', 'Chapter', 'Topic', 'Concept', 'Unit', 'Content', 'TeachingResource'],
        'teach/learn' => ['Standard', 'Subject', 'Chapter', 'Topic', 'Concept', 'Unit', 'Content', 'TeachingResource'],
        'curriculum' => ['Standard', 'Subject', 'Chapter', 'Topic', 'Concept', 'Unit', 'Curriculum'],
        'engagement' => ['Content', 'TeachingResource', 'Concept'],
        'interaction' => ['Content', 'TeachingResource', 'Concept'],
        'lo ' => ['CompetencyStandards', 'ChapterStandardMap'],
        'indicator' => ['CompetencyStandards'],
        'topic' => ['Topic', 'Chapter'],
        'lesson' => ['Lesson', 'Chapter'],
        'timetable' => ['TimeSlot'],

        // -- assessment / exam --------------------------------------------------
        'exam' => ['Examination', 'ExamType', 'ExamTypeCategory', 'ExamSchedule', 'Grade'],
        'assesment' => ['Assessment', 'Question', 'QuestionType', 'AssessmentTypology'],
        'assessment' => ['Assessment', 'Question', 'QuestionType', 'AssessmentTypology'],
        'result' => ['Result', 'Examination', 'Grade'],
        'question' => ['Question', 'QuestionType'],
        'counselling' => ['CounsellingCourse', 'CounsellingQuestion', 'CounsellingResult'],

        // -- people / HR ----------------------------------------------------
        'student' => ['Student', 'StuDetail'],
        // :Teacher is gone (see StaffGraphProjection/GraphSchema docblocks,
        // 2026-11-03) — :Staff is the single canonical label, with
        // `is_teacher` as a property rather than a separate label.
        'staff' => ['Staff'],
        'teacher' => ['Staff'],
        'user' => ['Staff', 'Role'],
        'hrit' => ['Staff', 'Department', 'Role'],
        'payroll' => ['PayrollType', 'SalaryStructure', 'SalaryCertificate'],
        'leave' => ['LeaveType', 'Holiday'],
        'talent' => ['CompetencyFramework', 'Competency'],
        'skill' => ['Skill', 'JobRole', 'JobTask', 'SkillAssessment'],
        'competency' => ['CompetencyFramework', 'Competency', 'CompetencyStandards'],
        'capability' => ['CompetencyFramework', 'Competency'],
        'organization' => ['Department', 'Role'],
        'task' => ['Task'],
        'shift' => ['StaffShift'],

        // -- operations -------------------------------------------------------
        'hostel' => ['Hostel', 'HostelBuilding', 'HostelFloor', 'HostelRoom', 'HostelType', 'HostelVisitor'],
        'transport' => ['Route', 'Stop', 'Vehicle', 'VehicleType', 'Driver', 'TransportShift'],
        'library' => ['Book', 'BookCopy'],
        'inventory' => ['InventoryItem', 'ItemCategory', 'ItemSubCategory', 'ItemType', 'Vendor'],
        'visitor' => ['Visitor', 'VisitorType'],
        'inward' => ['InwardDocument'],
        'outward' => ['OutwardDocument'],
        'complaint' => ['Complaint'],
        'front desk' => ['FrontDeskEntry'],
        'circular' => ['Circular', 'CircularType', 'Announcement'],

        // -- finance ------------------------------------------------------------
        'fee' => ['FeeHead', 'FeeTitle', 'FeeTitleMaster', 'FeeOtherHead', 'FeeConfig', 'LateFeeRule', 'FeeMonth', 'FeeCircular', 'FeeCancelType', 'ReceiptBook'],
        'bank' => ['Bank'],
        'petty cash' => ['PettyCashHead'],
        'donation' => ['Donation'],

        // -- organisation / admin -----------------------------------------------
        'institute' => ['Institute'],
        'school' => ['Institute'],
        'sqaa' => ['SQAAStandard', 'SQAADocument'],
        'career' => ['JobRole', 'Industry', 'CareerCluster'],
        'calendar' => ['CalendarEvent', 'TimeSlot'],

        // -- Knowledge Graph coverage pass (added 2026-11-03) ------------------
        // Five real submodules that previously matched nothing at all — see
        // GraphSchema's own docblock for the entities behind each label.
        'admission' => ['AdmissionEnquiry'],
        'consent' => ['Consent'],
        'ptm' => ['PtmBooking', 'PtmTimeSlot'],
        // Deliberately the specific phrase, not a bare 'communication' —
        // that would also match the unrelated, intentionally-unmapped
        // "Communication Report" submodule (an aggregate-log screen, see
        // the coverage report).
        'parent communication' => ['ParentCommunication'],
    ];

    /**
     * The real, live GraphSchema labels this submodule maps to — the union
     * of every keyword dictionary entry whose key appears in the submodule's
     * own name, or (if that matches nothing) in one of its screen names.
     *
     * @param  string[]  $screenNames  The submodule's level-3 children's own
     *                                 names/labels — a second, more specific
     *                                 signal for submodules whose own name is
     *                                 too generic to match anything ("LMS" on
     *                                 its own matches the 'lms' keyword
     *                                 directly, so this mainly helps names
     *                                 that don't).
     * @return string[] GraphSchema labels, deduplicated, or [] if nothing matched.
     */
    public function labelsFor(string $submoduleName, array $screenNames = []): array
    {
        $labels = $this->matchKeywords($submoduleName);

        if ($labels === []) {
            foreach ($screenNames as $screenName) {
                $labels = array_merge($labels, $this->matchKeywords($screenName));
            }
        }

        $labels = array_values(array_unique($labels));

        // Every label returned must be one GraphSchema actually knows — the
        // dictionary above is hand-written and could drift from GraphSchema
        // over time; silently dropping an unknown one is safer than letting
        // it reach Cypher interpolation.
        return array_values(array_filter($labels, fn ($label) => GraphSchema::knowsLabel($label)));
    }

    /** @return string[] */
    private function matchKeywords(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $haystack = mb_strtolower($text);
        $labels = [];

        foreach (self::KEYWORD_LABELS as $keyword => $keywordLabels) {
            if (str_contains($haystack, $keyword)) {
                $labels = array_merge($labels, $keywordLabels);
            }
        }

        return $labels;
    }
}
