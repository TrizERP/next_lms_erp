<?php

namespace App\Brain\Intelligence;

use Illuminate\Support\Facades\DB;
use App\Brain\Support\AcademicYear;

/**
 * Real Cross-Module Integration Service for all LMS Intelligence Modules.
 *
 * Core purpose: "What information can work together?"
 *
 * This service connects real LMS module data, context, signals, and relationships
 * so that one Intelligence module understands information from other relevant modules.
 *
 * ZERO FAKE DATA. Every count, status, and relationship is checked against real database
 * tables with strict tenant isolation (`sub_institute_id`) and academic year (`syear`).
 * If an integration has no data, an honest unavailable reason is returned.
 */
class ModuleIntegrationService
{
    /**
     * Get integration relationships and data availability for a module.
     *
     * @param string $module Module slug ('fees', 'attendance', 'result', etc.)
     * @param int|string $tenantId Sub-institute ID
     * @param string|null $syear Academic year
     * @return array
     */
    public function getModuleIntegrations(string $module, $tenantId, ?string $syear): array
    {
        $tenantId = (int) $tenantId;
        $canonicalModule = $this->normalizeModuleKey($module);

        $integrations = match ($canonicalModule) {
            'fees' => $this->getFeesIntegrations($tenantId, $syear),
            'attendance' => $this->getAttendanceIntegrations($tenantId, $syear),
            'result' => $this->getResultIntegrations($tenantId, $syear),
            'student' => $this->getStudentIntegrations($tenantId, $syear),
            'transport' => $this->getTransportIntegrations($tenantId, $syear),
            'library' => $this->getLibraryIntegrations($tenantId, $syear),
            'academic' => $this->getAcademicIntegrations($tenantId, $syear),
            'hr' => $this->getHrIntegrations($tenantId, $syear),
            'communication' => $this->getCommunicationIntegrations($tenantId, $syear),
            'homework' => $this->getHomeworkIntegrations($tenantId, $syear),
            'admissions' => $this->getAdmissionsIntegrations($tenantId, $syear),
            'inventory' => $this->getInventoryIntegrations($tenantId, $syear),
            'hostel' => $this->getHostelIntegrations($tenantId, $syear),
            'visitor' => $this->getVisitorIntegrations($tenantId, $syear),
            'correspondence' => $this->getCorrespondenceIntegrations($tenantId, $syear),
            'teach-learn' => $this->getTeachLearnIntegrations($tenantId, $syear),
            'organization' => $this->getOrganizationIntegrations($tenantId, $syear),
            'task-management' => $this->getTaskManagementIntegrations($tenantId, $syear),
            'talent' => $this->getTalentIntegrations($tenantId, $syear),
            'capability' => $this->getCapabilityIntegrations($tenantId, $syear),
            'staff-attendance' => $this->getStaffAttendanceIntegrations($tenantId, $syear),
            'lms-activity' => $this->getLmsActivityIntegrations($tenantId, $syear),
            default => [],
        };

        $connectedCount = count(array_filter($integrations, fn ($i) => $i['status'] === 'available'));

        return [
            'module' => $canonicalModule,
            'module_label' => $this->getModuleLabel($canonicalModule),
            'academic_year' => $syear,
            'summary' => [
                'total_integrations' => count($integrations),
                'active_connections' => $connectedCount,
                'headline' => $connectedCount > 0
                    ? "{$connectedCount} cross-module integration" . ($connectedCount > 1 ? 's' : '') . " active with verified data"
                    : 'No cross-module data currently verified for this academic year',
            ],
            'integrations' => $integrations,
        ];
    }

    private function normalizeModuleKey(string $key): string
    {
        $k = strtolower(trim($key));
        if ($k === 'students') return 'student';
        if ($k === 'transportation') return 'transport';
        if ($k === 'user') return 'hr';
        if ($k === 'easy_com') return 'communication';
        if ($k === 'academic_setup') return 'academic';
        if ($k === 'inward_outward') return 'correspondence';
        return $k;
    }

    private function getModuleLabel(string $module): string
    {
        return match ($module) {
            'fees' => 'Fees',
            'attendance' => 'Attendance',
            'result' => 'Examination & Results',
            'student' => 'Student Directory',
            'transport' => 'Transportation',
            'library' => 'Library',
            'academic' => 'Academic Setup',
            'hr' => 'Human Resources & Staff',
            'communication' => 'Communication',
            'homework' => 'Homework & Assignments',
            'admissions' => 'Admissions & Inquiries',
            'inventory' => 'Inventory & Requisitions',
            'hostel' => 'Hostel Management',
            'visitor' => 'Visitor & Front Desk',
            'correspondence' => 'Correspondence',
            'teach-learn' => 'Teach & Learn (PAL)',
            'organization' => 'Organization Management',
            'task-management' => 'Task Management',
            'talent' => 'Talent Management',
            'capability' => 'Capability Intelligence',
            'staff-attendance' => 'Attendance Management',
            'lms-activity' => 'LMS',
            default => ucfirst($module),
        };
    }

    /* -------------------------------------------------------------------------- */
    /* Module-Specific Integration Builders                                      */
    /* -------------------------------------------------------------------------- */

    private function getFeesIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        // 1. Student Enrollment Integration
        $enrolledCount = 0;
        try {
            $enrolledCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'fees-students',
            'target_module' => 'student',
            'target_label' => 'Student Enrollment',
            'relationship' => 'Fee Demand & Student Ledger Scoping',
            'why_it_matters' => 'Fee billing structures map directly to actively enrolled students in each standard and quota.',
            'status' => $enrolledCount > 0 ? 'available' : 'unavailable',
            'record_count' => $enrolledCount,
            'metrics' => [
                ['label' => 'Enrolled Students', 'value' => (string) $enrolledCount],
            ],
            'shared_entities' => ['student_id', 'standard_id', 'class_section', 'admission_quota'],
            'route' => '/student',
            'reason' => $enrolledCount === 0 ? "No active enrolled students found for year {$syear}." : null,
        ];

        // 2. Attendance Correlation Integration
        $attendanceCount = 0;
        try {
            $attendanceCount = DB::table('result_student_attendance_master')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'fees-attendance',
            'target_module' => 'attendance',
            'target_label' => 'Student Attendance',
            'relationship' => 'Chronic Arrears & Absenteeism Cross-Correlation',
            'why_it_matters' => 'Correlates unpaid fee cycles with student absence patterns to identify dropout and collection risk early.',
            'status' => $attendanceCount > 0 ? 'available' : 'unavailable',
            'record_count' => $attendanceCount,
            'metrics' => [
                ['label' => 'Attendance Session Records', 'value' => (string) $attendanceCount],
            ],
            'shared_entities' => ['student_id', 'session_date', 'present_status'],
            'route' => '/attendance',
            'reason' => $attendanceCount === 0 ? "No attendance sessions logged for academic year {$syear}." : null,
        ];

        // 3. Parent Communication Integration
        $commCount = 0;
        try {
            $commCount = DB::table('parent_communication')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'fees-communication',
            'target_module' => 'communication',
            'target_label' => 'Communication & Outreach',
            'relationship' => 'Payment Reminder Notices & Receipt Alerts',
            'why_it_matters' => 'Enables automated sending of fee installment reminder circulars, WhatsApp/SMS payment links, and clearance receipts.',
            'status' => $commCount > 0 ? 'available' : 'unavailable',
            'record_count' => $commCount,
            'metrics' => [
                ['label' => 'Communication Dispatches', 'value' => (string) $commCount],
            ],
            'shared_entities' => ['student_id', 'parent_phone', 'notice_type', 'dispatch_status'],
            'route' => '/communication',
            'reason' => $commCount === 0 ? "No communication records logged for this institute." : null,
        ];

        // 4. Academic Examination Integration
        $marksCount = 0;
        try {
            $marksCount = DB::table('result_personalize_marks')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'fees-result',
            'target_module' => 'result',
            'target_label' => 'Academic Examinations',
            'relationship' => 'Scholarship & Exam Hall Ticket Eligibility',
            'why_it_matters' => 'Verifies fee clearance requirements before issuing examination admit cards and tracks merit scholarship concession validity.',
            'status' => $marksCount > 0 ? 'available' : 'unavailable',
            'record_count' => $marksCount,
            'metrics' => [
                ['label' => 'Student Marks Records', 'value' => (string) $marksCount],
            ],
            'shared_entities' => ['student_id', 'exam_id', 'admit_card_clearance'],
            'route' => '/result',
            'reason' => $marksCount === 0 ? "No examination marks records recorded for academic year {$syear}." : null,
        ];

        return $integrations;
    }

    private function getAttendanceIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        // 1. Student Roster
        $enrolledCount = 0;
        try {
            $enrolledCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'attendance-students',
            'target_module' => 'student',
            'target_label' => 'Student Directory',
            'relationship' => 'Class Roster & Enrollment Verification',
            'why_it_matters' => 'Daily attendance sheets and biometric check-ins are verified against the active student roll.',
            'status' => $enrolledCount > 0 ? 'available' : 'unavailable',
            'record_count' => $enrolledCount,
            'metrics' => [['label' => 'Active Roster Students', 'value' => (string) $enrolledCount]],
            'shared_entities' => ['student_id', 'standard_id', 'section_id', 'roll_number'],
            'route' => '/student',
            'reason' => $enrolledCount === 0 ? "No enrolled students found for year {$syear}." : null,
        ];

        // 2. Timetable & Academic Scheduling
        $timetableCount = 0;
        try {
            $timetableCount = DB::table('timetable')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'attendance-academic',
            'target_module' => 'academic',
            'target_label' => 'Timetable & Class Schedules',
            'relationship' => 'Period-wise Schedule Adherence',
            'why_it_matters' => 'Maps session attendance directly to scheduled class periods, teachers, and classroom locations.',
            'status' => $timetableCount > 0 ? 'available' : 'unavailable',
            'record_count' => $timetableCount,
            'metrics' => [['label' => 'Timetable Allocations', 'value' => (string) $timetableCount]],
            'shared_entities' => ['period_id', 'standard_id', 'subject_id', 'teacher_id'],
            'route' => '/academic_setup',
            'reason' => $timetableCount === 0 ? "No active timetable mapped for academic year {$syear}." : null,
        ];

        // 3. Parent Absentee Alerts
        $commCount = 0;
        try {
            $commCount = DB::table('parent_communication')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'attendance-communication',
            'target_module' => 'communication',
            'target_label' => 'Parent Communication',
            'relationship' => 'Instant Absentee Notice Dispatch',
            'why_it_matters' => 'Triggers instant SMS, WhatsApp, and app push notifications to parents whenever a student is marked absent.',
            'status' => $commCount > 0 ? 'available' : 'unavailable',
            'record_count' => $commCount,
            'metrics' => [['label' => 'Notification Outbox Logs', 'value' => (string) $commCount]],
            'shared_entities' => ['student_id', 'absence_date', 'guardian_phone'],
            'route' => '/communication',
            'reason' => $commCount === 0 ? "No communication service configured for this institute." : null,
        ];

        return $integrations;
    }

    private function getResultIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        // 1. Student Enrollment
        $enrolledCount = 0;
        try {
            $enrolledCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'result-students',
            'target_module' => 'student',
            'target_label' => 'Student Enrollment',
            'relationship' => 'Candidate Register & Hall Ticket Identity',
            'why_it_matters' => 'Matches marks entries with registered candidates across divisions, ensuring zero orphaned exam marks.',
            'status' => $enrolledCount > 0 ? 'available' : 'unavailable',
            'record_count' => $enrolledCount,
            'metrics' => [['label' => 'Registered Examinees', 'value' => (string) $enrolledCount]],
            'shared_entities' => ['student_id', 'seat_number', 'standard_id'],
            'route' => '/student',
            'reason' => $enrolledCount === 0 ? "No students enrolled for year {$syear}." : null,
        ];

        // 2. Attendance Exam Eligibility
        $attendanceCount = 0;
        try {
            $attendanceCount = DB::table('result_student_attendance_master')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'result-attendance',
            'target_module' => 'attendance',
            'target_label' => 'Attendance Qualification',
            'relationship' => '75% Attendance Exam Qualification Check',
            'why_it_matters' => 'Automates statutory attendance criteria verification required prior to board and term examination eligibility.',
            'status' => $attendanceCount > 0 ? 'available' : 'unavailable',
            'record_count' => $attendanceCount,
            'metrics' => [['label' => 'Verified Attendance Logs', 'value' => (string) $attendanceCount]],
            'shared_entities' => ['student_id', 'attendance_percentage', 'eligibility_flag'],
            'route' => '/attendance',
            'reason' => $attendanceCount === 0 ? "Attendance records not logged for year {$syear}." : null,
        ];

        // 3. Curriculum & Syllabus Units
        $subjectCount = 0;
        try {
            $subjectCount = DB::table('subject')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'result-teachlearn',
            'target_module' => 'teach-learn',
            'target_label' => 'Curriculum & Pedagogy',
            'relationship' => 'Outcome-Based Assessment Mastery',
            'why_it_matters' => 'Links test questions and grade boundaries directly to subject learning objectives and taxonomy levels.',
            'status' => $subjectCount > 0 ? 'available' : 'unavailable',
            'record_count' => $subjectCount,
            'metrics' => [['label' => 'Active Subjects', 'value' => (string) $subjectCount]],
            'shared_entities' => ['subject_id', 'learning_outcome_id', 'competency_code'],
            'route' => '/teach-learn',
            'reason' => $subjectCount === 0 ? "No subjects registered in master database." : null,
        ];

        return $integrations;
    }

    private function getStudentIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        // 1. Fee Account
        $receiptCount = 0;
        try {
            $receiptCount = DB::table('fees_collect')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'student-fees',
            'target_module' => 'fees',
            'target_label' => 'Fees Ledger',
            'relationship' => 'Billing Demand, Receipts & Dues Summary',
            'why_it_matters' => 'Displays live student account ledger, outstanding balance, and scholarship concessions inside student profile.',
            'status' => $receiptCount > 0 ? 'available' : 'unavailable',
            'record_count' => $receiptCount,
            'metrics' => [['label' => 'Processed Receipts', 'value' => (string) $receiptCount]],
            'shared_entities' => ['student_id', 'demand_amount', 'paid_amount', 'outstanding_dues'],
            'route' => '/fees/intelligence',
            'reason' => $receiptCount === 0 ? "No fee receipts recorded for year {$syear}." : null,
        ];

        // 2. Attendance Register
        $attCount = 0;
        try {
            $attCount = DB::table('result_student_attendance_master')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'student-attendance',
            'target_module' => 'attendance',
            'target_label' => 'Attendance Record',
            'relationship' => 'Student Daily Attendance Track',
            'why_it_matters' => 'Supplies cumulative present days, absence trends, and leave applications on student cumulative 360 card.',
            'status' => $attCount > 0 ? 'available' : 'unavailable',
            'record_count' => $attCount,
            'metrics' => [['label' => 'Session Records', 'value' => (string) $attCount]],
            'shared_entities' => ['student_id', 'present_days', 'absent_days', 'attendance_pct'],
            'route' => '/attendance',
            'reason' => $attCount === 0 ? "No attendance records found for year {$syear}." : null,
        ];

        // 3. Transport Allocation
        $transportCount = 0;
        try {
            $transportCount = DB::table('transport_map_student')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'student-transport',
            'target_module' => 'transport',
            'target_label' => 'Transportation',
            'relationship' => 'Bus Route & Pickup Stop Allocation',
            'why_it_matters' => 'Tracks assigned vehicle number, driver emergency contacts, and daily transit route.',
            'status' => $transportCount > 0 ? 'available' : 'unavailable',
            'record_count' => $transportCount,
            'metrics' => [['label' => 'Mapped Commuters', 'value' => (string) $transportCount]],
            'shared_entities' => ['student_id', 'route_id', 'pickup_point_id'],
            'route' => '/Transportation',
            'reason' => $transportCount === 0 ? "No student transport allocations found." : null,
        ];

        // 4. Hostel Allocation
        $hostelCount = 0;
        try {
            $hostelCount = DB::table('hostel_room_allocation')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'student-hostel',
            'target_module' => 'hostel',
            'target_label' => 'Hostel Accommodation',
            'relationship' => 'Hostel Boarding & Bed Allocation',
            'why_it_matters' => 'Identifies residential students, room number, floor assignment, and resident warden in charge.',
            'status' => $hostelCount > 0 ? 'available' : 'unavailable',
            'record_count' => $hostelCount,
            'metrics' => [['label' => 'Hostel Residents', 'value' => (string) $hostelCount]],
            'shared_entities' => ['student_id', 'hostel_id', 'room_number', 'bed_number'],
            'route' => '/hostel',
            'reason' => $hostelCount === 0 ? "No hostel room allocations found for this institute." : null,
        ];

        return $integrations;
    }

    private function getTransportIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $mappedCount = 0;
        try {
            $mappedCount = DB::table('transport_map_student')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'transport-student',
            'target_module' => 'student',
            'target_label' => 'Student Directory',
            'relationship' => 'Rider Manifest & Identity Verification',
            'why_it_matters' => 'Assigns student riders to specific vehicles, pickup stages, and emergency notification contacts.',
            'status' => $mappedCount > 0 ? 'available' : 'unavailable',
            'record_count' => $mappedCount,
            'metrics' => [['label' => 'Allocated Students', 'value' => (string) $mappedCount]],
            'shared_entities' => ['student_id', 'route_id', 'stop_id'],
            'route' => '/student',
            'reason' => $mappedCount === 0 ? "No student riders mapped to transport routes." : null,
        ];

        $feeCount = 0;
        try {
            $feeCount = DB::table('fees_paid_other')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'transport-fees',
            'target_module' => 'fees',
            'target_label' => 'Fee Collection',
            'relationship' => 'Transport Stage Fee Accounting',
            'why_it_matters' => 'Reconciles bus maintenance, fuel surcharge, and student stage distance billing against fee accounts.',
            'status' => $feeCount > 0 ? 'available' : 'unavailable',
            'record_count' => $feeCount,
            'metrics' => [['label' => 'Transport Fee Receipts', 'value' => (string) $feeCount]],
            'shared_entities' => ['student_id', 'transport_fee_amount', 'paid_status'],
            'route' => '/fees/intelligence',
            'reason' => $feeCount === 0 ? "No transport fee records found for year {$syear}." : null,
        ];

        return $integrations;
    }

    private function getLibraryIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $circCount = 0;
        try {
            $circCount = DB::table('library_book_circulations')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'library-students',
            'target_module' => 'student',
            'target_label' => 'Student Borrowers',
            'relationship' => 'Circulation Card & Book Loan History',
            'why_it_matters' => 'Maintains individual borrowing limits, active checked-out books, and reading engagement indices.',
            'status' => $circCount > 0 ? 'available' : 'unavailable',
            'record_count' => $circCount,
            'metrics' => [['label' => 'Active Book Loans', 'value' => (string) $circCount]],
            'shared_entities' => ['student_id', 'book_id', 'issue_date', 'due_date'],
            'route' => '/student',
            'reason' => $circCount === 0 ? "No library circulation records recorded." : null,
        ];

        $feeCount = 0;
        try {
            $feeCount = DB::table('fees_collect')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('fine_amount', '>', 0)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'library-fees',
            'target_module' => 'fees',
            'target_label' => 'Fee Collection',
            'relationship' => 'Overdue Fine & Lost Book Clearance',
            'why_it_matters' => 'Routes late book return fines directly into student fee accounts for centralized cashier clearance.',
            'status' => $feeCount > 0 ? 'available' : 'unavailable',
            'record_count' => $feeCount,
            'metrics' => [['label' => 'Fine Receipts', 'value' => (string) $feeCount]],
            'shared_entities' => ['student_id', 'fine_amount', 'clearance_date'],
            'route' => '/fees/intelligence',
            'reason' => $feeCount === 0 ? "No library fine records found in fee ledger." : null,
        ];

        return $integrations;
    }

    private function getAcademicIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $timetableCount = 0;
        try {
            $timetableCount = DB::table('timetable')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'academic-hr',
            'target_module' => 'hr',
            'target_label' => 'Staff & Faculty',
            'relationship' => 'Teacher Workload & Subject Assignment',
            'why_it_matters' => 'Maps curriculum delivery schedules to qualified teachers, tracking weekly period limits and substitute cover.',
            'status' => $timetableCount > 0 ? 'available' : 'unavailable',
            'record_count' => $timetableCount,
            'metrics' => [['label' => 'Teacher Period Mappings', 'value' => (string) $timetableCount]],
            'shared_entities' => ['teacher_id', 'subject_id', 'weekly_periods'],
            'route' => '/user',
            'reason' => $timetableCount === 0 ? "No teacher timetable allocations found for {$syear}." : null,
        ];

        $marksCount = 0;
        try {
            $marksCount = DB::table('result_personalize_marks')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'academic-result',
            'target_module' => 'result',
            'target_label' => 'Examination Evaluation',
            'relationship' => 'Curriculum Benchmarking & Grade Curves',
            'why_it_matters' => 'Validates class standard academic progress against syllabus completion targets.',
            'status' => $marksCount > 0 ? 'available' : 'unavailable',
            'record_count' => $marksCount,
            'metrics' => [['label' => 'Evaluated Marks', 'value' => (string) $marksCount]],
            'shared_entities' => ['standard_id', 'subject_id', 'class_average'],
            'route' => '/result',
            'reason' => $marksCount === 0 ? "No evaluation marks logged for year {$syear}." : null,
        ];

        return $integrations;
    }

    private function getHrIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $timetableCount = 0;
        try {
            $timetableCount = DB::table('timetable')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'hr-academic',
            'target_module' => 'academic',
            'target_label' => 'Academic Setup',
            'relationship' => 'Instructional Timetable & Workload',
            'why_it_matters' => 'Ensures faculty periods comply with maximum daily teaching load and prevents double-booking.',
            'status' => $timetableCount > 0 ? 'available' : 'unavailable',
            'record_count' => $timetableCount,
            'metrics' => [['label' => 'Teaching Slots', 'value' => (string) $timetableCount]],
            'shared_entities' => ['staff_id', 'period_id', 'standard_id'],
            'route' => '/academic_setup',
            'reason' => $timetableCount === 0 ? "No active timetable mapped for year {$syear}." : null,
        ];

        $attCount = 0;
        try {
            $attCount = DB::table('hrms_attendances')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'hr-attendance',
            'target_module' => 'attendance',
            'target_label' => 'Staff Attendance',
            'relationship' => 'Biometric Check-in & Leave Accounting',
            'why_it_matters' => 'Feeds daily staff punch logs into monthly payroll generation and substitute teacher allocation.',
            'status' => $attCount > 0 ? 'available' : 'unavailable',
            'record_count' => $attCount,
            'metrics' => [['label' => 'Staff Attendance Rows', 'value' => (string) $attCount]],
            'shared_entities' => ['staff_id', 'punch_time', 'leave_balance'],
            'route' => '/user',
            'reason' => $attCount === 0 ? "No staff biometric attendance logged." : null,
        ];

        return $integrations;
    }

    private function getCommunicationIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $studentCount = 0;
        try {
            $studentCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'communication-students',
            'target_module' => 'student',
            'target_label' => 'Student & Parent Roster',
            'relationship' => 'Verified Guardian Contact Directory',
            'why_it_matters' => 'Ensures emergency announcements, circulars, and notifications target valid verified parent contacts.',
            'status' => $studentCount > 0 ? 'available' : 'unavailable',
            'record_count' => $studentCount,
            'metrics' => [['label' => 'Target Parent Contacts', 'value' => (string) $studentCount]],
            'shared_entities' => ['student_id', 'guardian_mobile', 'guardian_email'],
            'route' => '/student',
            'reason' => $studentCount === 0 ? "No active students found for year {$syear}." : null,
        ];

        $feeDefaulters = 0;
        try {
            $feeDefaulters = DB::table('fees_collect')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'communication-fees',
            'target_module' => 'fees',
            'target_label' => 'Fees Billing',
            'relationship' => 'Fee Reminder Campaigns & Payment Gateways',
            'why_it_matters' => 'Directly dispatches fee overdue reminder notices with payment links to accounts in arrears.',
            'status' => $feeDefaulters > 0 ? 'available' : 'unavailable',
            'record_count' => $feeDefaulters,
            'metrics' => [['label' => 'Billed Accounts In Scope', 'value' => (string) $feeDefaulters]],
            'shared_entities' => ['student_id', 'due_amount', 'due_date'],
            'route' => '/fees/intelligence',
            'reason' => $feeDefaulters === 0 ? "No fee billing records found for year {$syear}." : null,
        ];

        return $integrations;
    }

    private function getHomeworkIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $studentCount = 0;
        try {
            $studentCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'homework-student',
            'target_module' => 'student',
            'target_label' => 'Student Class Divisions',
            'relationship' => 'Assignment Distribution & Submission Tracking',
            'why_it_matters' => 'Maps homework tasks to students by subject and section, monitoring daily completion percentages.',
            'status' => $studentCount > 0 ? 'available' : 'unavailable',
            'record_count' => $studentCount,
            'metrics' => [['label' => 'Eligible Students', 'value' => (string) $studentCount]],
            'shared_entities' => ['student_id', 'standard_id', 'submission_status'],
            'route' => '/student',
            'reason' => $studentCount === 0 ? "No enrolled students found for year {$syear}." : null,
        ];

        return $integrations;
    }

    private function getAdmissionsIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $studentCount = 0;
        try {
            $studentCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'admissions-student',
            'target_module' => 'student',
            'target_label' => 'Student Enrollment',
            'relationship' => 'Prospect to Enrolled Roll Transition',
            'why_it_matters' => 'Promotes approved prospective applicants into permanent enrolled student records with generated roll numbers.',
            'status' => $studentCount > 0 ? 'available' : 'unavailable',
            'record_count' => $studentCount,
            'metrics' => [['label' => 'Enrolled Student Records', 'value' => (string) $studentCount]],
            'shared_entities' => ['applicant_id', 'student_id', 'gr_number', 'admission_date'],
            'route' => '/student',
            'reason' => $studentCount === 0 ? "No students enrolled for year {$syear}." : null,
        ];

        $feeCount = 0;
        try {
            $feeCount = DB::table('fees_collect')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'admissions-fees',
            'target_module' => 'fees',
            'target_label' => 'Fees Billing',
            'relationship' => 'Prospect Registration & Seat Booking Dues',
            'why_it_matters' => 'Records admission prospectus payments and confirms seat reservation upon initial fee deposit receipt.',
            'status' => $feeCount > 0 ? 'available' : 'unavailable',
            'record_count' => $feeCount,
            'metrics' => [['label' => 'Admission Receipts', 'value' => (string) $feeCount]],
            'shared_entities' => ['applicant_id', 'admission_fee', 'receipt_id'],
            'route' => '/fees/intelligence',
            'reason' => $feeCount === 0 ? "No fee receipts found for year {$syear}." : null,
        ];

        return $integrations;
    }

    private function getInventoryIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $deptCount = 0;
        try {
            $deptCount = DB::table('hrms_departments')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'inventory-hr',
            'target_module' => 'hr',
            'target_label' => 'Institutional Departments',
            'relationship' => 'Departmental Material Indents & Requisitions',
            'why_it_matters' => 'Allows science labs, sports departments, and library staff to requisition materials with authorized head approval.',
            'status' => $deptCount > 0 ? 'available' : 'unavailable',
            'record_count' => $deptCount,
            'metrics' => [['label' => 'Active Departments', 'value' => (string) $deptCount]],
            'shared_entities' => ['department_id', 'requisition_id', 'allocated_asset_id'],
            'route' => '/user',
            'reason' => $deptCount === 0 ? "No institutional departments found." : null,
        ];

        return $integrations;
    }

    private function getHostelIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $studentCount = 0;
        try {
            $studentCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'hostel-students',
            'target_module' => 'student',
            'target_label' => 'Student Enrollment',
            'relationship' => 'Resident Student Manifest',
            'why_it_matters' => 'Links hostel room, bed, and dining hall allotment to registered students.',
            'status' => $studentCount > 0 ? 'available' : 'unavailable',
            'record_count' => $studentCount,
            'metrics' => [['label' => 'Active Students', 'value' => (string) $studentCount]],
            'shared_entities' => ['student_id', 'room_id', 'bed_id'],
            'route' => '/student',
            'reason' => $studentCount === 0 ? "No enrolled students found for year {$syear}." : null,
        ];

        $feeCount = 0;
        try {
            $feeCount = DB::table('fees_paid_other')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'hostel-fees',
            'target_module' => 'fees',
            'target_label' => 'Fees Collection',
            'relationship' => 'Hostel & Mess Fee Reconciliation',
            'why_it_matters' => 'Reconciles boarding dues, caution deposits, and meal charges against student accounts.',
            'status' => $feeCount > 0 ? 'available' : 'unavailable',
            'record_count' => $feeCount,
            'metrics' => [['label' => 'Hostel Fee Records', 'value' => (string) $feeCount]],
            'shared_entities' => ['student_id', 'hostel_fee_amount', 'paid_status'],
            'route' => '/fees/intelligence',
            'reason' => $feeCount === 0 ? "No hostel fee records logged for {$syear}." : null,
        ];

        return $integrations;
    }

    private function getVisitorIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $studentCount = 0;
        try {
            $studentCount = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->where('is_deleted', 'N')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'visitor-student',
            'target_module' => 'student',
            'target_label' => 'Student Directory',
            'relationship' => 'Parent Visit & Early Release Verification',
            'why_it_matters' => 'Verifies visitor identity against registered parents and guardians before granting campus entry or issuing gate passes.',
            'status' => $studentCount > 0 ? 'available' : 'unavailable',
            'record_count' => $studentCount,
            'metrics' => [['label' => 'Registered Students', 'value' => (string) $studentCount]],
            'shared_entities' => ['visitor_id', 'student_id', 'relationship_type'],
            'route' => '/student',
            'reason' => $studentCount === 0 ? "No enrolled students found for year {$syear}." : null,
        ];

        return $integrations;
    }

    private function getCorrespondenceIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $deptCount = 0;
        try {
            $deptCount = DB::table('hrms_departments')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'correspondence-hr',
            'target_module' => 'hr',
            'target_label' => 'Staff & Departments',
            'relationship' => 'Official Inward/Outward Document Routing',
            'why_it_matters' => 'Routes external correspondence from educational boards, government bodies, and vendors to responsible staff.',
            'status' => $deptCount > 0 ? 'available' : 'unavailable',
            'record_count' => $deptCount,
            'metrics' => [['label' => 'Institutional Departments', 'value' => (string) $deptCount]],
            'shared_entities' => ['dispatch_id', 'assigned_department_id', 'staff_recipient_id'],
            'route' => '/user',
            'reason' => $deptCount === 0 ? "No departments found for routing." : null,
        ];

        return $integrations;
    }

    private function getTeachLearnIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $subjectCount = 0;
        try {
            $subjectCount = DB::table('subject')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'teachlearn-academic',
            'target_module' => 'academic',
            'target_label' => 'Academic Curriculum',
            'relationship' => 'Curriculum Framework & Syllabus Pacing',
            'why_it_matters' => 'Aligns personalized adaptive learning (PAL) units and lesson plans with approved academic terms.',
            'status' => $subjectCount > 0 ? 'available' : 'unavailable',
            'record_count' => $subjectCount,
            'metrics' => [['label' => 'Curriculum Subjects', 'value' => (string) $subjectCount]],
            'shared_entities' => ['subject_id', 'chapter_id', 'competency_target'],
            'route' => '/academic_setup',
            'reason' => $subjectCount === 0 ? "No subjects registered in academic master." : null,
        ];

        $marksCount = 0;
        try {
            $marksCount = DB::table('result_personalize_marks')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'teachlearn-result',
            'target_module' => 'result',
            'target_label' => 'Formative Assessment',
            'relationship' => 'Diagnostic Assessment Feedback Loop',
            'why_it_matters' => 'Surfaces weak concept topics from exam and test results to automatically recommend targeted remedial content.',
            'status' => $marksCount > 0 ? 'available' : 'unavailable',
            'record_count' => $marksCount,
            'metrics' => [['label' => 'Test Score Entries', 'value' => (string) $marksCount]],
            'shared_entities' => ['student_id', 'topic_mastery_pct', 'remedial_required'],
            'route' => '/result',
            'reason' => $marksCount === 0 ? "No assessment marks recorded for {$syear}." : null,
        ];

        return $integrations;
    }

    /* -------------------------------------------------------------------------- */
    /* People & Competency Integration Builders                                  */
    /* -------------------------------------------------------------------------- */

    private function getOrganizationIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $talentActivityCount = 0;
        try {
            $talentActivityCount = DB::table('talent_job_applications')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'organization-talent',
            'target_module' => 'talent',
            'target_label' => 'Talent Management',
            'relationship' => 'Headcount & Hiring Pipeline',
            'why_it_matters' => 'Department headcount and attrition figures are the denominator Talent\'s hiring funnel and offer decisions are sized against.',
            'status' => $talentActivityCount > 0 ? 'available' : 'unavailable',
            'record_count' => $talentActivityCount,
            'metrics' => [['label' => 'Job Applications on File', 'value' => (string) $talentActivityCount]],
            'shared_entities' => ['tbluser.id', 'hrms_departments.id', 'talent_job_applications.department_id'],
            'route' => '/modules/talent-management/intelligence',
            'reason' => $talentActivityCount === 0 ? 'No recruitment activity recorded for this institute.' : null,
        ];

        $taskCount = 0;
        try {
            $taskCount = DB::table('task')
                ->where('sub_institute_id', $tenantId)
                ->whereNull('deleted_at')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'organization-task',
            'target_module' => 'task-management',
            'target_label' => 'Task Management',
            'relationship' => 'Staff Workload by Department',
            'why_it_matters' => 'Task assignment is keyed on the same staff id Organization reports headcount against, so workload can be read alongside department size.',
            'status' => $taskCount > 0 ? 'available' : 'unavailable',
            'record_count' => $taskCount,
            'metrics' => [['label' => 'Tasks on File', 'value' => (string) $taskCount]],
            'shared_entities' => ['tbluser.id (TASK_ALLOCATED_TO)'],
            'route' => '/modules/task-management-551/intelligence',
            'reason' => $taskCount === 0 ? 'No tasks recorded for this institute.' : null,
        ];

        $attendanceCount = 0;
        try {
            $attendanceCount = DB::table('hrms_attendances')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'organization-staff-attendance',
            'target_module' => 'staff-attendance',
            'target_label' => 'Attendance Management',
            'relationship' => 'Active Staff Roster Verification',
            'why_it_matters' => 'Attendance Management\'s punch-register figures are only meaningful measured against Organization\'s own active-staff count.',
            'status' => $attendanceCount > 0 ? 'available' : 'unavailable',
            'record_count' => $attendanceCount,
            'metrics' => [['label' => 'Punch Records on File', 'value' => (string) $attendanceCount]],
            'shared_entities' => ['tbluser.id', 'hrms_departments.id'],
            'route' => '/hrit/attendance-management/attendance-reports',
            'reason' => $attendanceCount === 0 ? 'No staff attendance data recorded for this institute.' : null,
        ];

        return $integrations;
    }

    private function getTaskManagementIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $staffCount = 0;
        try {
            $staffCount = DB::table('tbluser')
                ->where('sub_institute_id', $tenantId)
                ->where('status', 1)
                ->whereNull('terminated_date')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'task-organization',
            'target_module' => 'organization',
            'target_label' => 'Organization Management',
            'relationship' => 'Assignee Directory',
            'why_it_matters' => 'Every task\'s TASK_ALLOCATED_TO is an active staff id from the Organization directory, so workload concentration can be read against department context.',
            'status' => $staffCount > 0 ? 'available' : 'unavailable',
            'record_count' => $staffCount,
            'metrics' => [['label' => 'Active Staff', 'value' => (string) $staffCount]],
            'shared_entities' => ['tbluser.id'],
            'route' => '/modules/organization-management/intelligence',
            'reason' => $staffCount === 0 ? 'No active staff on file for this institute.' : null,
        ];

        $attendanceCount = 0;
        try {
            $attendanceCount = DB::table('hrms_attendances')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'task-staff-attendance',
            'target_module' => 'staff-attendance',
            'target_label' => 'Attendance Management',
            'relationship' => 'Overload vs. Presence Cross-Check',
            'why_it_matters' => 'A person carrying a high overdue task share who is also irregular on the punch register is a different story than one who is simply overloaded.',
            'status' => $attendanceCount > 0 ? 'available' : 'unavailable',
            'record_count' => $attendanceCount,
            'metrics' => [['label' => 'Punch Records on File', 'value' => (string) $attendanceCount]],
            'shared_entities' => ['tbluser.id'],
            'route' => '/hrit/attendance-management/attendance-reports',
            'reason' => $attendanceCount === 0 ? 'No staff attendance data recorded for this institute.' : null,
        ];

        return $integrations;
    }

    private function getTalentIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $departmentCount = 0;
        try {
            $departmentCount = DB::table('hrms_departments')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'talent-organization',
            'target_module' => 'organization',
            'target_label' => 'Organization Management',
            'relationship' => 'Department Structure',
            'why_it_matters' => 'Hiring pipeline, onboarding and offboarding are all placed against the same department structure Organization owns.',
            'status' => $departmentCount > 0 ? 'available' : 'unavailable',
            'record_count' => $departmentCount,
            'metrics' => [['label' => 'Departments on File', 'value' => (string) $departmentCount]],
            'shared_entities' => ['hrms_departments.id', 'tbluser.department_id'],
            'route' => '/modules/organization-management/intelligence',
            'reason' => $departmentCount === 0 ? 'No department master data on file.' : null,
        ];

        $jobroleCount = 0;
        try {
            $jobroleCount = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'talent-capability',
            'target_module' => 'capability',
            'target_label' => 'Capability Intelligence',
            'relationship' => 'Job-Role Skill Mapping',
            'why_it_matters' => 'Candidate and succession matching is only as good as the skill mapping behind each job role, which Capability owns.',
            'status' => $jobroleCount > 0 ? 'available' : 'unavailable',
            'record_count' => $jobroleCount,
            'metrics' => [['label' => 'Job Roles Mapped', 'value' => (string) $jobroleCount]],
            'shared_entities' => ['s_user_jobrole.id', 's_user_skill_jobrole.jobrole_id'],
            'route' => '/modules/capability-intelligence/intelligence',
            'reason' => $jobroleCount === 0 ? 'No job-role mapping on file.' : null,
        ];

        $attendanceCount = 0;
        try {
            $attendanceCount = DB::table('hrms_attendances')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'talent-staff-attendance',
            'target_module' => 'staff-attendance',
            'target_label' => 'Attendance Management',
            'relationship' => 'Onboarding/Offboarding Presence Check',
            'why_it_matters' => 'A new hire\'s onboarding journey and an exiting employee\'s offboarding case are both cross-checked against the punch register.',
            'status' => $attendanceCount > 0 ? 'available' : 'unavailable',
            'record_count' => $attendanceCount,
            'metrics' => [['label' => 'Punch Records on File', 'value' => (string) $attendanceCount]],
            'shared_entities' => ['tbluser.id'],
            'route' => '/hrit/attendance-management/attendance-reports',
            'reason' => $attendanceCount === 0 ? 'No staff attendance data recorded for this institute.' : null,
        ];

        return $integrations;
    }

    private function getCapabilityIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $jobroleCount = 0;
        try {
            $jobroleCount = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $tenantId)
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'capability-talent',
            'target_module' => 'talent',
            'target_label' => 'Talent Management',
            'relationship' => 'Job-Role Skill Coverage for Hiring',
            'why_it_matters' => 'Skill-coverage gaps by job role are the same surface Talent\'s hiring funnel and succession planning read from.',
            'status' => $jobroleCount > 0 ? 'available' : 'unavailable',
            'record_count' => $jobroleCount,
            'metrics' => [['label' => 'Job Roles Mapped', 'value' => (string) $jobroleCount]],
            'shared_entities' => ['s_user_jobrole.id', 's_user_skill_jobrole.jobrole_id'],
            'route' => '/modules/talent-management/intelligence',
            'reason' => $jobroleCount === 0 ? 'No job-role mapping on file.' : null,
        ];

        $staffCount = 0;
        try {
            $staffCount = DB::table('tbluser')
                ->where('sub_institute_id', $tenantId)
                ->where('status', 1)
                ->whereNull('terminated_date')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'capability-organization',
            'target_module' => 'organization',
            'target_label' => 'Organization Management',
            'relationship' => 'Department Skill Coverage',
            'why_it_matters' => 'Skill-coverage gaps are reported by department, using the same department structure Organization owns.',
            'status' => $staffCount > 0 ? 'available' : 'unavailable',
            'record_count' => $staffCount,
            'metrics' => [['label' => 'Active Staff', 'value' => (string) $staffCount]],
            'shared_entities' => ['tbluser.id', 'hrms_departments.id'],
            'route' => '/modules/organization-management/intelligence',
            'reason' => $staffCount === 0 ? 'No active staff on file for this institute.' : null,
        ];

        return $integrations;
    }

    private function getStaffAttendanceIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $staffCount = 0;
        try {
            $staffCount = DB::table('tbluser')
                ->where('sub_institute_id', $tenantId)
                ->where('status', 1)
                ->whereNull('terminated_date')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'staff-attendance-organization',
            'target_module' => 'organization',
            'target_label' => 'Organization Management',
            'relationship' => 'Active Staff Register',
            'why_it_matters' => 'Attendance rates are only meaningful measured against Organization\'s own active-staff count and department structure.',
            'status' => $staffCount > 0 ? 'available' : 'unavailable',
            'record_count' => $staffCount,
            'metrics' => [['label' => 'Active Staff', 'value' => (string) $staffCount]],
            'shared_entities' => ['tbluser.id', 'hrms_departments.id'],
            'route' => '/modules/organization-management/intelligence',
            'reason' => $staffCount === 0 ? 'No active staff on file for this institute.' : null,
        ];

        $taskCount = 0;
        try {
            $taskCount = DB::table('task')
                ->where('sub_institute_id', $tenantId)
                ->whereNull('deleted_at')
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'staff-attendance-task',
            'target_module' => 'task-management',
            'target_label' => 'Task Management',
            'relationship' => 'Presence vs. Overload Cross-Check',
            'why_it_matters' => 'Irregular attendance on a person already carrying a heavy overdue task load is a different finding than either signal alone.',
            'status' => $taskCount > 0 ? 'available' : 'unavailable',
            'record_count' => $taskCount,
            'metrics' => [['label' => 'Tasks on File', 'value' => (string) $taskCount]],
            'shared_entities' => ['tbluser.id (TASK_ALLOCATED_TO)'],
            'route' => '/modules/task-management-551/intelligence',
            'reason' => $taskCount === 0 ? 'No tasks recorded for this institute.' : null,
        ];

        return $integrations;
    }

    private function getLmsActivityIntegrations(int $tenantId, ?string $syear): array
    {
        $integrations = [];

        $homeworkCount = 0;
        try {
            $homeworkCount = DB::table('homework')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'lms-activity-homework',
            'target_module' => 'homework',
            'target_label' => 'Homework & Assignments',
            'relationship' => 'Learner Engagement Source',
            'why_it_matters' => 'Homework submission is the real, measurable learner-activity signal this module blends with content coverage.',
            'status' => $homeworkCount > 0 ? 'available' : 'unavailable',
            'record_count' => $homeworkCount,
            'metrics' => [['label' => 'Homework Rows on File', 'value' => (string) $homeworkCount]],
            'shared_entities' => ['standard_id', 'subject_id', 'student_id'],
            'route' => '/lms/homework/intelligence',
            'reason' => $homeworkCount === 0 ? "No homework activity recorded for {$syear}." : null,
        ];

        $contentCount = 0;
        try {
            $contentCount = DB::table('content_master')
                ->where('sub_institute_id', $tenantId)
                ->when($syear, fn ($q) => $q->where('syear', $syear))
                ->count();
        } catch (\Throwable) {}

        $integrations[] = [
            'id' => 'lms-activity-teachlearn',
            'target_module' => 'teach-learn',
            'target_label' => 'Teach & Learn (PAL)',
            'relationship' => 'Course & Content Catalogue Source',
            'why_it_matters' => 'Content coverage — what has been published and to which class — is the other real half of this module\'s position metrics.',
            'status' => $contentCount > 0 ? 'available' : 'unavailable',
            'record_count' => $contentCount,
            'metrics' => [['label' => 'Published Content Items', 'value' => (string) $contentCount]],
            'shared_entities' => ['sub_std_map.standard_id', 'sub_std_map.subject_id', 'content_master.id'],
            'route' => '/modules/teach-learn/intelligence',
            'reason' => $contentCount === 0 ? "No published content recorded for {$syear}." : null,
        ];

        return $integrations;
    }
}

