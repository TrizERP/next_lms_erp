<?php

namespace App\Domain\KnowledgeGraph;

use App\Services\Graph\GraphSchema;

/**
 * Maps a real ERP submodule (a `tblmenumaster` level-2 row — "Exam &
 * Assesment", "Teach/Learn", "Hostel", "Transport"...) onto the GraphSchema labels
 * that actually represent it in Neo4j.
 *
 * Each submodule is mapped to its own distinct, actual data flow in the Neo4j
 * knowledge graph, ensuring every module and submodule visualizes only its
 * own real connected entities and relationships.
 */
class ModuleGraphMapper
{
    /**
     * keyword => GraphSchema labels. Checked as a case-insensitive substring
     * of the submodule's name (and its screens' names, as a second pass).
     * Specific multi-word keywords are ordered before generic single-word keywords.
     */
    private const KEYWORD_LABELS = [
        // -- LMS + PAL (Module ID: 230) ---------------------------------------
        // Teach/Learn: Academic delivery from Standard down to Topic, Concept, and Teaching Resources
        'teach/learn'          => ['Standard', 'Subject', 'Chapter', 'Topic', 'Concept', 'TeachingResource', 'Content'],
        'teach'                => ['Standard', 'Subject', 'Chapter', 'Topic', 'Concept', 'TeachingResource', 'Content'],
        // PAL: Personalized Adaptive Learning & student mastery across concepts and outcomes
        'pal'                  => ['Student', 'StuDetail', 'Concept', 'Chapter', 'Lesson'],
        // Curriculum Planning: Curriculum framework, units, outcomes and subjects
        'curriculum'           => ['Curriculum', 'Subject', 'Unit', 'Chapter', 'Concept'],
        // Engagement: Student activity with interactive content and lessons
        'engagement'           => ['Student', 'Lesson', 'Concept', 'TeachingResource', 'Content', 'Chapter'],
        'interaction'          => ['Student', 'Lesson', 'Concept', 'TeachingResource', 'Content'],
        // Exam & Assessment under LMS+PAL
        'exam & assesment'     => ['Assessment', 'Question', 'QuestionType', 'Concept', 'Chapter', 'Student', 'Result'],
        'assessment'           => ['Assessment', 'Question', 'QuestionType', 'Concept', 'Chapter', 'Student', 'Result'],
        'assesment'            => ['Assessment', 'Question', 'QuestionType', 'Concept', 'Chapter', 'Student', 'Result'],
        'question'             => ['Question', 'QuestionType', 'Concept', 'Chapter'],

        // -- Institute ERP (Module ID: 1) -------------------------------------
        // Core student enrollment and classroom division spine
        'student'              => ['Student', 'StuDetail', 'Standard', 'Division'],
        // Student attendance tracking
        'attendance'           => ['Student', 'StuDetail', 'AcademicYear', 'Standard', 'Division'],
        // School-wide examinations, exam types, categories, schedules, and grades
        'exam'                 => ['Examination', 'ExamType', 'ExamTypeCategory', 'ExamSchedule', 'Standard', 'Subject', 'GradeScheme', 'Grade', 'StuDetail'],
        'result'               => ['Result', 'Examination', 'Grade', 'Student'],
        // School fee structure, fee records, circulars, heads, and payments
        'fee'                  => ['FeeTitle', 'FeeTitleMaster', 'FeeCircular', 'LateFeeRule', 'Standard', 'FeeRecord', 'FeeOtherHead', 'FeePayment', 'StuDetail'],
        // Timetable and scheduling
        'timetable'            => ['TimeSlot', 'Standard', 'Division', 'Subject', 'Staff'],
        // Transportation fleet, routes, stops, and student boarding
        'transport'            => ['Vehicle', 'Route', 'Stop', 'Driver', 'TransportShift', 'VehicleType', 'StuDetail'],
        // Hostel facilities, buildings, floors, rooms, and allocations
        'hostel'               => ['Hostel', 'HostelBuilding', 'HostelFloor', 'HostelRoom', 'HostelType', 'StuDetail'],
        // Library assets and borrowing
        'library'              => ['Book', 'BookCopy', 'StuDetail'],
        // Inventory stock, categories, vendors, and staff requisitions
        'inventory'            => ['InventoryItem', 'ItemCategory', 'ItemSubCategory', 'ItemType', 'Vendor', 'Staff'],
        // Parent-Teacher meetings
        'ptm'                  => ['PtmBooking', 'PtmTimeSlot', 'Standard', 'Division', 'StuDetail'],
        // Student admission enquiries
        'admission'            => ['AdmissionEnquiry', 'Standard', 'Staff', 'StuDetail'],
        // Student consents
        'consent'              => ['Consent', 'Standard', 'Division', 'StuDetail'],
        // Administrative notices and circulars
        'circular'             => ['Circular', 'CircularType', 'Announcement', 'Institute'],
        // Campus visitors
        'visitor'              => ['Visitor', 'VisitorType', 'Institute'],
        // Front desk visitor and student logs
        'front desk'           => ['FrontDeskEntry', 'StuDetail', 'Student'],
        // Grievances and complaints
        'complaint'            => ['Complaint', 'Institute'],
        // Document management
        'inward'               => ['InwardDocument', 'FileLocation', 'Institute'],
        'outward'              => ['OutwardDocument', 'FileLocation', 'Institute'],
        // Quality assurance
        'sqaa'                 => ['SQAAStandard', 'SQAADocument', 'Institute'],
        // Direct parent messaging
        'parent communication' => ['ParentCommunication', 'StuDetail'],
        // Petty cash
        'petty cash'           => ['PettyCashHead', 'Staff'],
        // Alumni and donations
        'alumni'               => ['Donation', 'Institute'],
        'donation'             => ['Donation', 'Institute'],
        // Users & roles
        'user'                 => ['Staff', 'Role', 'Department', 'Institute'],
        'utility'              => ['Standard', 'Division', 'AcademicYear', 'Student'],

        // -- People & Competency (Module ID: 350) ------------------------------
        'hrit'                 => ['Staff', 'Department', 'Role', 'LeaveType', 'SalaryStructure', 'PayrollType'],
        'payroll'              => ['Staff', 'PayrollType', 'SalaryStructure', 'SalaryCertificate'],
        'leave'                => ['Staff', 'LeaveType', 'Holiday'],
        'talent'               => ['CompetencyFramework', 'Competency', 'CompetencyStandards'],
        'capability'           => ['JobRole', 'Skill', 'JobTask'],
        'competency'           => ['CompetencyFramework', 'Competency', 'CompetencyStandards'],
        'skill'                => ['Skill', 'JobRole', 'JobTask', 'SkillAssessment'],
        'task'                 => ['Staff', 'Task'],
        'organization'         => ['Institute', 'Department', 'Role', 'Staff'],
        'shift'                => ['Staff', 'StaffShift'],
        // Teacher classroom teaching allocation under People & Competency
        'lms'                  => ['Staff', 'Subject', 'Division', 'Standard'],

        // -- Career Counseling (Module ID: 455) --------------------------------
        'career awareness'     => ['CareerCluster', 'JobRole', 'JobTask', 'Industry'],
        'career counseling'    => ['CounsellingCourse', 'CounsellingQuestion', 'MappingType'],
        'career explorer'      => ['JobRole', 'Skill', 'CareerCluster'],
        'career intelligence'  => ['JobRole', 'Skill', 'JobTask', 'CareerCluster'],
        'counselling'          => ['CounsellingCourse', 'CounsellingQuestion', 'CounsellingResult'],
        'career'               => ['JobRole', 'Skill', 'JobTask', 'CareerCluster'],

        // -- Enterprise Brain (Module ID: 854) ---------------------------------
        'overview'             => ['Institute', 'Department', 'Role', 'Staff'],
        'foundation'           => ['Institute', 'Standard', 'Division', 'AcademicYear'],
        'knowledge'            => ['Book', 'BookCopy', 'InwardDocument', 'FileLocation'],
        'automation'           => ['Staff', 'Task'],
        'analytics'            => ['Examination', 'Subject', 'Student', 'Result', 'Assessment'],
        'intelligence loop'    => ['Assessment', 'Question', 'Concept', 'Student'],
        'account'              => ['Institute', 'AcademicYear', 'Holiday', 'CalendarEvent'],
        'setting'              => ['Institute', 'AcademicYear', 'Holiday', 'CalendarEvent'],
    ];

    /**
     * The real, live GraphSchema labels this submodule maps to.
     *
     * @param  string[]  $screenNames
     * @return string[] GraphSchema labels, deduplicated, or [] if nothing matched.
     */
    public function labelsFor(string $submoduleName, array $screenNames = []): array
    {
        $labels = $this->matchKeywords($submoduleName);

        if ($labels === []) {
            foreach ($screenNames as $screenName) {
                $matched = $this->matchKeywords($screenName);
                if (! empty($matched)) {
                    $labels = array_merge($labels, $matched);
                    break;
                }
            }
        }

        $labels = array_values(array_unique($labels));

        return array_values(array_filter($labels, fn ($label) => GraphSchema::knowsLabel($label)));
    }

    /** @return string[] */
    private function matchKeywords(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $haystack = mb_strtolower(trim($text));

        // Longest / exact keyword matching first
        foreach (self::KEYWORD_LABELS as $keyword => $keywordLabels) {
            if (str_contains($haystack, $keyword)) {
                return $keywordLabels;
            }
        }

        return [];
    }
}
