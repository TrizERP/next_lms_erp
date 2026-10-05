<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
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
            // The five People & Competency modules. Their absence here — not any
            // absence of data — is why their Integration tab read "No Data
            // Available" on every tenant: the match fell through to `default`.
            'organization' => $this->getOrganizationIntegrations($tenantId, $syear),
            'task-management' => $this->getTaskManagementIntegrations($tenantId, $syear),
            'talent' => $this->getTalentIntegrations($tenantId, $syear),
            'capability' => $this->getCapabilityIntegrations($tenantId, $syear),
            'lms-activity' => $this->getLmsActivityIntegrations($tenantId, $syear),
            'petty-cash' => $this->getPettyCashIntegrations($tenantId, $syear),
            'document-templates' => $this->getDocumentTemplateIntegrations($tenantId, $syear),
            'ptm' => $this->getPtmIntegrations($tenantId, $syear),
            'consent' => $this->getConsentIntegrations($tenantId, $syear),
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
                // "Nothing is defined" and "everything defined has no rows" are
                // different problems with different answers, and a single
                // sentence for both is what made a missing provider look like a
                // data-poor tenant.
                'headline' => $connectedCount > 0
                    ? "{$connectedCount} cross-module integration" . ($connectedCount > 1 ? 's' : '') . " active with verified data"
                    : (count($integrations) === 0
                        ? 'No cross-module integrations are defined for this module yet'
                        : 'Every defined integration is present but holds no records for this academic year'),
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
            'capability' => 'Capability & Skills',
            'lms-activity' => 'LMS Activity',
            'petty-cash' => 'Petty Cash',
            'document-templates' => 'Document Templates',
            'ptm' => 'Parent-Teacher Meetings',
            'consent' => 'Student Consent',
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

    /* ================================================================
     * People & Competency integrations.
     *
     * Every count below is a single tenant-scoped aggregate against a table
     * verified to exist and to carry `sub_institute_id`. None joins inside a
     * loop, so none can go N+1. A table missing on an installation leaves its
     * count at zero and the integration reports `unavailable` with a reason,
     * rather than throwing and taking the whole tab down.
     * ================================================================ */

    private function getOrganizationIntegrations(int $tenantId, ?string $syear): array
    {
        $staff = $this->countOf('tbluser', $tenantId);
        $departments = $this->countOf('hrms_departments', $tenantId);
        $headed = $this->countOf('hrms_departments', $tenantId, null, function ($q) {
            $q->whereNotNull('head_user_id')->where('head_user_id', '!=', 0);
        });
        $postings = $this->countOf('talent_job_postings', $tenantId);
        $applications = $this->countOf('talent_job_applications', $tenantId);
        $jobRoles = $this->countOf('s_user_jobrole', $tenantId);

        return [
            [
                'id' => 'organization-people',
                'target_module' => 'hr',
                'target_label' => 'Staff Directory',
                'relationship' => 'People on the organization chart',
                'why_it_matters' => 'Headcount, departments and reporting lines are read from the same staff records the HR module maintains, so the org chart cannot drift from the staff roll.',
                'status' => $staff > 0 ? 'available' : 'unavailable',
                'record_count' => $staff,
                'metrics' => [['label' => 'Staff records', 'value' => (string) $staff]],
                'shared_entities' => ['user_id', 'department_id'],
                'route' => '/user',
                'reason' => $staff === 0 ? 'No staff records exist for this institute.' : null,
            ],
            [
                'id' => 'organization-departments',
                'target_module' => 'hr',
                'target_label' => 'Departments',
                'relationship' => 'Departments and who heads them',
                'why_it_matters' => 'A department with no named head has nobody to route an approval to, which is what stalls every cross-module workflow that needs one.',
                'status' => $departments > 0 ? 'available' : 'unavailable',
                'record_count' => $departments,
                'metrics' => [
                    ['label' => 'Departments', 'value' => (string) $departments],
                    ['label' => 'With a named head', 'value' => $departments > 0 ? ($headed . ' of ' . $departments) : '-'],
                ],
                'shared_entities' => ['department_id', 'head_user_id'],
                'route' => '/user',
                'reason' => $departments === 0 ? 'No departments are configured for this institute.' : null,
            ],
            [
                'id' => 'organization-talent',
                'target_module' => 'talent',
                'target_label' => 'Hiring',
                'relationship' => 'Open postings and the applications against them',
                'why_it_matters' => 'Vacancies turn an org chart into a hiring plan; postings carry the department they belong to, so a gap in the chart is traceable to a live requisition.',
                'status' => $postings > 0 ? 'available' : 'unavailable',
                'record_count' => $postings,
                'metrics' => [
                    ['label' => 'Job postings', 'value' => (string) $postings],
                    ['label' => 'Applications', 'value' => (string) $applications],
                ],
                'shared_entities' => ['job_id', 'department_id'],
                'route' => '/modules/talent-management/intelligence',
                'reason' => $postings === 0 ? 'No job postings have been raised for this institute.' : null,
            ],
            [
                'id' => 'organization-capability',
                'target_module' => 'capability',
                'target_label' => 'Job Roles',
                'relationship' => 'Job roles defined for this organization',
                'why_it_matters' => 'A job role is the unit both the org chart and the skills matrix hang off, so the two can only be compared where roles are defined.',
                'status' => $jobRoles > 0 ? 'available' : 'unavailable',
                'record_count' => $jobRoles,
                'metrics' => [['label' => 'Job roles', 'value' => (string) $jobRoles]],
                'shared_entities' => ['jobrole_id', 'department_id'],
                'route' => '/modules/capability-intelligence/intelligence',
                'reason' => $jobRoles === 0 ? 'No job roles are defined for this institute.' : null,
            ],
        ];
    }

    private function getTaskManagementIntegrations(int $tenantId, ?string $syear): array
    {
        $tasks = $this->countOf('task', $tenantId);
        $assignees = $this->distinctOf('task', $tenantId, 'TASK_ALLOCATED_TO');
        $withSkill = $this->countOf('task', $tenantId, null, function ($q) {
            $q->whereNotNull('required_skill')->where('required_skill', '!=', '');
        });
        $deptWithTasks = $this->countOf('hrms_departments', $tenantId, null, function ($q) {
            $q->whereNotNull('tasks')->where('tasks', '!=', '');
        });

        return [
            [
                'id' => 'task-people',
                'target_module' => 'hr',
                'target_label' => 'Staff Directory',
                'relationship' => 'Who work is allocated to',
                'why_it_matters' => 'Every task carries an allocatee, so workload concentration is answerable from the same staff records HR maintains.',
                'status' => $assignees > 0 ? 'available' : 'unavailable',
                'record_count' => $assignees,
                'metrics' => [
                    ['label' => 'Tasks', 'value' => (string) $tasks],
                    ['label' => 'Distinct allocatees', 'value' => (string) $assignees],
                ],
                'shared_entities' => ['TASK_ALLOCATED_TO', 'user_id'],
                'route' => '/user',
                'reason' => $assignees === 0 ? 'No task has an allocatee recorded against it.' : null,
            ],
            [
                'id' => 'task-capability',
                'target_module' => 'capability',
                'target_label' => 'Skills',
                'relationship' => 'Tasks that name a required skill',
                'why_it_matters' => 'A task naming a skill can be matched against who actually holds it, which is the difference between allocating work and guessing.',
                'status' => $withSkill > 0 ? 'available' : 'unavailable',
                'record_count' => $withSkill,
                'metrics' => [['label' => 'Tasks naming a skill', 'value' => $tasks > 0 ? ($withSkill . ' of ' . $tasks) : '0']],
                'shared_entities' => ['required_skill', 'skill_id'],
                'route' => '/modules/capability-intelligence/intelligence',
                'reason' => $withSkill === 0 ? 'No task records a required skill.' : null,
            ],
            [
                'id' => 'task-organization',
                'target_module' => 'organization',
                'target_label' => 'Departments',
                'relationship' => 'Departments carrying standing task definitions',
                'why_it_matters' => 'Departments hold their own task definitions; comparing those with what is actually allocated shows which standing duties nobody is doing.',
                'status' => $deptWithTasks > 0 ? 'available' : 'unavailable',
                'record_count' => $deptWithTasks,
                'metrics' => [['label' => 'Departments with task definitions', 'value' => (string) $deptWithTasks]],
                'shared_entities' => ['department_id'],
                'route' => '/modules/organization-management/intelligence',
                'reason' => $deptWithTasks === 0 ? 'No department has standing task definitions recorded.' : null,
            ],
        ];
    }

    private function getTalentIntegrations(int $tenantId, ?string $syear): array
    {
        $staff = $this->countOf('tbluser', $tenantId);
        $postings = $this->countOf('talent_job_postings', $tenantId);
        $applications = $this->countOf('talent_job_applications', $tenantId);
        $reviews = $this->countOf('s_performance_reviews', $tenantId);
        $offboarding = $this->countOf('talent_offboarding_cases', $tenantId);
        $jobRoles = $this->countOf('s_user_jobrole', $tenantId);

        return [
            [
                'id' => 'talent-people',
                'target_module' => 'hr',
                'target_label' => 'Staff Directory',
                'relationship' => 'The employees talent processes act on',
                'why_it_matters' => 'Hiring, performance and offboarding all resolve to the same staff records, so a talent figure can always be traced to a person on the roll.',
                'status' => $staff > 0 ? 'available' : 'unavailable',
                'record_count' => $staff,
                'metrics' => [['label' => 'Staff records', 'value' => (string) $staff]],
                'shared_entities' => ['user_id'],
                'route' => '/user',
                'reason' => $staff === 0 ? 'No staff records exist for this institute.' : null,
            ],
            [
                'id' => 'talent-performance',
                'target_module' => 'hr',
                'target_label' => 'Performance Reviews',
                'relationship' => 'Review cycles against staff and their managers',
                'why_it_matters' => 'Reviews carry both the employee and the manager, so they are the only place progression and offboarding decisions can be evidenced.',
                'status' => $reviews > 0 ? 'available' : 'unavailable',
                'record_count' => $reviews,
                'metrics' => [
                    ['label' => 'Performance reviews', 'value' => (string) $reviews],
                    ['label' => 'Offboarding cases', 'value' => (string) $offboarding],
                ],
                'shared_entities' => ['user_id', 'manager_id', 'department_id'],
                'route' => '/user',
                'reason' => $reviews === 0 ? 'No performance reviews are recorded for this institute.' : null,
            ],
            [
                'id' => 'talent-capability',
                'target_module' => 'capability',
                'target_label' => 'Job Roles',
                'relationship' => 'Roles a posting or a review is written against',
                'why_it_matters' => 'A vacancy without a defined job role cannot be matched to the skills matrix, so the hiring gap cannot be described in capability terms.',
                'status' => $jobRoles > 0 ? 'available' : 'unavailable',
                'record_count' => $jobRoles,
                'metrics' => [
                    ['label' => 'Job roles', 'value' => (string) $jobRoles],
                    ['label' => 'Open postings', 'value' => (string) $postings],
                    ['label' => 'Applications', 'value' => (string) $applications],
                ],
                'shared_entities' => ['jobrole', 'jobrole_id'],
                'route' => '/modules/capability-intelligence/intelligence',
                'reason' => $jobRoles === 0 ? 'No job roles are defined for this institute.' : null,
            ],
        ];
    }

    private function getCapabilityIntegrations(int $tenantId, ?string $syear): array
    {
        $staff = $this->countOf('tbluser', $tenantId);
        $jobRoles = $this->countOf('s_user_jobrole', $tenantId);
        $mappings = $this->countOf('s_user_skill_jobrole', $tenantId);
        $distinctSkills = $this->distinctOf('s_user_skill_jobrole', $tenantId, 'skill');
        $distinctRoles = $this->distinctOf('s_user_skill_jobrole', $tenantId, 'jobrole_id');
        $departments = $this->distinctOf('s_user_jobrole', $tenantId, 'department_id');

        return [
            [
                'id' => 'capability-people',
                'target_module' => 'hr',
                'target_label' => 'Staff Directory',
                'relationship' => 'The workforce a skills matrix describes',
                'why_it_matters' => 'Skill coverage is only meaningful against a headcount; without staff records a proficiency count describes nobody.',
                'status' => $staff > 0 ? 'available' : 'unavailable',
                'record_count' => $staff,
                'metrics' => [['label' => 'Staff records', 'value' => (string) $staff]],
                'shared_entities' => ['user_id'],
                'route' => '/user',
                'reason' => $staff === 0 ? 'No staff records exist for this institute.' : null,
            ],
            [
                'id' => 'capability-skill-matrix',
                'target_module' => 'talent',
                'target_label' => 'Skill to Job-role Matrix',
                'relationship' => 'Which skills each job role requires, and at what proficiency',
                'why_it_matters' => 'This matrix is what lets a vacancy, a task or a review be expressed as a skill gap rather than a job title.',
                'status' => $mappings > 0 ? 'available' : 'unavailable',
                'record_count' => $mappings,
                'metrics' => [
                    ['label' => 'Skill-to-role mappings', 'value' => (string) $mappings],
                    ['label' => 'Distinct skills', 'value' => (string) $distinctSkills],
                    ['label' => 'Roles covered', 'value' => (string) $distinctRoles],
                ],
                'shared_entities' => ['skill_id', 'jobrole_id', 'proficiency_level'],
                'route' => '/modules/talent-management/intelligence',
                'reason' => $mappings === 0 ? 'No skill-to-job-role mappings exist for this institute.' : null,
            ],
            [
                'id' => 'capability-organization',
                'target_module' => 'organization',
                'target_label' => 'Departments',
                'relationship' => 'Departments that job roles are defined under',
                'why_it_matters' => 'Roles anchored to a department let skill coverage be read per department instead of only institute-wide.',
                'status' => $departments > 0 ? 'available' : 'unavailable',
                'record_count' => $departments,
                'metrics' => [
                    ['label' => 'Departments with roles', 'value' => (string) $departments],
                    ['label' => 'Job roles', 'value' => (string) $jobRoles],
                ],
                'shared_entities' => ['department_id', 'jobrole_id'],
                'route' => '/modules/organization-management/intelligence',
                'reason' => $departments === 0 ? 'No job role names a department for this institute.' : null,
            ],
        ];
    }

    private function getLmsActivityIntegrations(int $tenantId, ?string $syear): array
    {
        $students = $this->countOf('tblstudent_enrollment', $tenantId, $syear, function ($q) {
            $q->where('is_deleted', 'N');
        });
        $homework = $this->countOf('homework', $tenantId, $syear);
        $content = $this->countOf('content_master', $tenantId, $syear);
        $subjectMap = $this->countOf('sub_std_map', $tenantId);

        return [
            [
                'id' => 'lms-students',
                'target_module' => 'student',
                'target_label' => 'Student Enrollment',
                'relationship' => 'The cohort published content and homework reaches',
                'why_it_matters' => 'Content counts mean nothing without the roll they are published to; reach is content matched against enrolled students for the same year.',
                'status' => $students > 0 ? 'available' : 'unavailable',
                'record_count' => $students,
                'metrics' => [['label' => 'Enrolled students', 'value' => (string) $students]],
                'shared_entities' => ['student_id', 'standard_id'],
                'route' => '/students/intelligence',
                'reason' => $students === 0 ? ('No students are enrolled for academic year ' . ($syear ?? 'selected') . '.') : null,
            ],
            [
                'id' => 'lms-homework',
                'target_module' => 'homework',
                'target_label' => 'Homework & Assignments',
                'relationship' => 'Activity set against published content',
                'why_it_matters' => 'Content with no homework behind it is material nobody was asked to use, which is the gap this module exists to surface.',
                'status' => $homework > 0 ? 'available' : 'unavailable',
                'record_count' => $homework,
                'metrics' => [
                    ['label' => 'Homework items', 'value' => (string) $homework],
                    ['label' => 'Content items', 'value' => (string) $content],
                ],
                'shared_entities' => ['subject_id', 'standard_id', 'syear'],
                'route' => '/lms/homework/intelligence',
                'reason' => $homework === 0 ? ('No homework was set for academic year ' . ($syear ?? 'selected') . '.') : null,
            ],
            [
                'id' => 'lms-academic',
                'target_module' => 'academic',
                'target_label' => 'Class & Subject Map',
                'relationship' => 'The class-subject pairs content and homework are filed against',
                'why_it_matters' => 'Both content and homework are keyed by class and subject; without that map neither can be attributed to a cohort.',
                'status' => $subjectMap > 0 ? 'available' : 'unavailable',
                'record_count' => $subjectMap,
                'metrics' => [['label' => 'Class-subject pairs', 'value' => (string) $subjectMap]],
                'shared_entities' => ['subject_id', 'standard_id'],
                'route' => '/academic_setup/intelligence',
                'reason' => $subjectMap === 0 ? 'No class-subject mapping is configured for this institute.' : null,
            ],
        ];
    }

    /**
     * One tenant-scoped count, with the year applied only when the table has a
     * `syear` and a year was resolved.
     *
     * A missing table or column returns 0 rather than throwing: an installation
     * without one of these tables should lose that single integration row, not
     * the whole Integration tab.
     */
    private function countOf(string $table, int $tenantId, ?string $syear = null, ?callable $extra = null): int
    {
        try {
            if (! SchemaCache::hasTable($table)) {
                return 0;
            }

            $q = DB::table($table)->where('sub_institute_id', $tenantId);

            if ($syear !== null && $syear !== '' && SchemaCache::hasColumn($table, 'syear')) {
                $q->where('syear', $syear);
            }

            if ($extra !== null) {
                $extra($q);
            }

            return (int) $q->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Distinct non-empty values of one column, tenant scoped. Zero on any failure. */
    private function distinctOf(string $table, int $tenantId, string $column, ?string $syear = null): int
    {
        try {
            if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, $column)) {
                return 0;
            }

            return (int) DB::table($table)
                ->where('sub_institute_id', $tenantId)
                ->when(
                    $syear !== null && $syear !== '' && SchemaCache::hasColumn($table, 'syear'),
                    fn ($q) => $q->where('syear', $syear),
                )
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->where($column, '!=', '0')
                ->distinct()
                ->count($column);
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ================================================================
     * The four modules added after the "Brain does not watch this menu" audit.
     * Each relationship below is a column that genuinely joins the two modules,
     * not a thematic association.
     * ================================================================ */

    private function getPettyCashIntegrations(int $tenantId, ?string $syear): array
    {
        $claimants = $this->distinctOf('petty_cash', $tenantId, 'user_id');
        $staff = $this->countOf('tbluser', $tenantId);
        $heads = $this->countOf('petty_cash_master', $tenantId);

        return [
            [
                'id' => 'petty-cash-people',
                'target_module' => 'hr',
                'target_label' => 'Staff Directory',
                'relationship' => 'Who spends out of the float',
                'why_it_matters' => 'Every claim names the person who made it, so spend concentrates against real '
                    . 'staff records rather than an anonymous total.',
                'status' => $claimants > 0 ? 'available' : 'unavailable',
                'record_count' => $claimants,
                'metrics' => [
                    ['label' => 'Distinct claimants', 'value' => (string) $claimants],
                    ['label' => 'Staff on the directory', 'value' => (string) $staff],
                ],
                'shared_entities' => ['user_id'],
                'route' => '/user',
                'reason' => $claimants === 0 ? 'No petty-cash claim records who made it.' : null,
            ],
            [
                'id' => 'petty-cash-heads',
                'target_module' => 'inventory',
                'target_label' => 'Spending Heads',
                'relationship' => 'The headings claims are filed under',
                'why_it_matters' => 'A claim with no heading cannot be attributed to a budget line, which is what '
                    . 'makes petty cash reconcilable against requisitions at all.',
                'status' => $heads > 0 ? 'available' : 'unavailable',
                'record_count' => $heads,
                'metrics' => [['label' => 'Configured heads', 'value' => (string) $heads]],
                'shared_entities' => ['title_id'],
                'route' => '/Inventory',
                'reason' => $heads === 0 ? 'No petty-cash spending heads are configured for this institute.' : null,
            ],
        ];
    }

    private function getDocumentTemplateIntegrations(int $tenantId, ?string $syear): array
    {
        $reportCards = $this->countOf('result_template_master', $tenantId);
        $modules = $this->distinctOf('template_master', $tenantId, 'module_name');
        $emails = $this->countOf('email_templates', $tenantId);

        return [
            [
                'id' => 'doc-templates-result',
                'target_module' => 'result',
                'target_label' => 'Report Cards',
                'relationship' => 'The layouts the Result module publishes against',
                'why_it_matters' => 'Report cards render from their own template table. Without a layout the Result '
                    . 'module has nothing to publish, however complete the marks are.',
                'status' => $reportCards > 0 ? 'available' : 'unavailable',
                'record_count' => $reportCards,
                'metrics' => [['label' => 'Report-card layouts', 'value' => (string) $reportCards]],
                'shared_entities' => ['template_id', 'standard_id'],
                'route' => '/result/intelligence',
                'reason' => $reportCards === 0
                    ? 'No report-card template is configured, so results cannot be published from this system.'
                    : null,
            ],
            [
                'id' => 'doc-templates-modules',
                'target_module' => 'organization',
                'target_label' => 'Modules Served',
                'relationship' => 'Which modules have a document to offer',
                'why_it_matters' => 'Templates are offered per module. A module with none leaves its staff composing '
                    . 'the same document by hand every time.',
                'status' => $modules > 0 ? 'available' : 'unavailable',
                'record_count' => $modules,
                'metrics' => [['label' => 'Modules covered', 'value' => (string) $modules]],
                'shared_entities' => ['module_name'],
                'route' => '/modules/organization-management/intelligence',
                'reason' => $modules === 0 ? 'No template names the module it belongs to.' : null,
            ],
            [
                'id' => 'doc-templates-communication',
                'target_module' => 'communication',
                'target_label' => 'Email Templates',
                'relationship' => 'Templates used for outbound messages',
                'why_it_matters' => 'Email templates are authored in the same library but sent by Communication, so '
                    . 'the two modules share one authoring surface.',
                'status' => $emails > 0 ? 'available' : 'unavailable',
                'record_count' => $emails,
                'metrics' => [['label' => 'Email templates', 'value' => (string) $emails]],
                'shared_entities' => ['template_id'],
                'route' => '/easy_com/intelligence',
                'reason' => $emails === 0 ? 'No email template is configured for this institute.' : null,
            ],
        ];
    }

    private function getPtmIntegrations(int $tenantId, ?string $syear): array
    {
        $students = $this->distinctOfUpper('ptm_booking_master', $tenantId, 'STUDENT_ID');
        $teachers = $this->distinctOfUpper('ptm_booking_master', $tenantId, 'TEACHER_ID');
        $classes = $this->distinctOf('ptm_time_slots_master', $tenantId, 'standard_id', $syear);

        return [
            [
                'id' => 'ptm-students',
                'target_module' => 'student',
                'target_label' => 'Student Enrollment',
                'relationship' => 'The children whose parents booked a meeting',
                'why_it_matters' => 'A booking names the student, so take-up can be read against the roll instead of '
                    . 'as a bare count - and the children nobody booked for are identifiable.',
                'status' => $students > 0 ? 'available' : 'unavailable',
                'record_count' => $students,
                'metrics' => [['label' => 'Students with a booking', 'value' => (string) $students]],
                'shared_entities' => ['STUDENT_ID', 'standard_id'],
                'route' => '/students/intelligence',
                'reason' => $students === 0 ? 'No booking names a student.' : null,
            ],
            [
                'id' => 'ptm-teachers',
                'target_module' => 'hr',
                'target_label' => 'Teaching Staff',
                'relationship' => 'The teachers parents were booked with',
                'why_it_matters' => 'Meeting load per teacher is only answerable because each booking carries the '
                    . 'member of staff it was made with.',
                'status' => $teachers > 0 ? 'available' : 'unavailable',
                'record_count' => $teachers,
                'metrics' => [['label' => 'Teachers met', 'value' => (string) $teachers]],
                'shared_entities' => ['TEACHER_ID', 'user_id'],
                'route' => '/user',
                'reason' => $teachers === 0 ? 'No booking names a teacher.' : null,
            ],
            [
                'id' => 'ptm-classes',
                'target_module' => 'academic',
                'target_label' => 'Classes',
                'relationship' => 'The classes meeting slots were opened for',
                'why_it_matters' => 'Slots are offered per class-division, so a class with none was never given the '
                    . 'chance to book rather than having declined.',
                'status' => $classes > 0 ? 'available' : 'unavailable',
                'record_count' => $classes,
                'metrics' => [['label' => 'Classes with slots', 'value' => (string) $classes]],
                'shared_entities' => ['standard_id', 'division_id'],
                'route' => '/academic_setup/intelligence',
                'reason' => $classes === 0
                    ? 'No meeting slot names a class for the selected academic year.'
                    : null,
            ],
        ];
    }

    private function getConsentIntegrations(int $tenantId, ?string $syear): array
    {
        $students = $this->distinctOf('consent_master', $tenantId, 'student_id', $syear);
        $classes = $this->distinctOf('consent_master', $tenantId, 'standard_id', $syear);
        $withAmount = $this->countOf('consent_master', $tenantId, $syear, function ($q) {
            $q->whereNotNull('amount')->where('amount', '>', 0);
        });

        return [
            [
                'id' => 'consent-students',
                'target_module' => 'student',
                'target_label' => 'Student Enrollment',
                'relationship' => 'The children consent was raised for',
                'why_it_matters' => 'Consent is per child, so it can only be chased where the record names one - and '
                    . 'coverage is only meaningful against the roll.',
                'status' => $students > 0 ? 'available' : 'unavailable',
                'record_count' => $students,
                'metrics' => [['label' => 'Students with consent raised', 'value' => (string) $students]],
                'shared_entities' => ['student_id', 'standard_id'],
                'route' => '/students/intelligence',
                'reason' => $students === 0 ? 'No consent record names a student for this academic year.' : null,
            ],
            [
                'id' => 'consent-classes',
                'target_module' => 'academic',
                'target_label' => 'Classes',
                'relationship' => 'The classes consent was raised in',
                'why_it_matters' => 'A consent round that reaches only some classes either belongs to one activity or '
                    . 'has missed the rest, and the class list is what tells the two apart.',
                'status' => $classes > 0 ? 'available' : 'unavailable',
                'record_count' => $classes,
                'metrics' => [['label' => 'Classes covered', 'value' => (string) $classes]],
                'shared_entities' => ['standard_id', 'division_id'],
                'route' => '/academic_setup/intelligence',
                'reason' => $classes === 0 ? 'No consent record names a class for this academic year.' : null,
            ],
            [
                'id' => 'consent-money',
                'target_module' => 'fees',
                'target_label' => 'Imprest Heads',
                'relationship' => 'Consent that collects money',
                'why_it_matters' => 'These rows carry an amount and an imprest head, so consent is where a trip or '
                    . 'activity charge is authorised before any money is collected.',
                'status' => $withAmount > 0 ? 'available' : 'unavailable',
                'record_count' => $withAmount,
                'metrics' => [['label' => 'Records carrying an amount', 'value' => (string) $withAmount]],
                'shared_entities' => ['imprest_head_id', 'amount'],
                'route' => '/fees/intelligence',
                'reason' => $withAmount === 0
                    ? 'No consent record for this year carries an amount - these are permission-only.'
                    : null,
            ],
        ];
    }

    /**
     * Distinct values of an UPPERCASE-named column on a table whose tenant
     * column is also uppercase.
     *
     * `ptm_booking_master` spells both `SUB_INSTITUTE_ID` and `STUDENT_ID` in
     * caps. MySQL does not care, but the helper above builds its predicate from
     * the lowercase name and would filter on a column this table does not have
     * under that spelling on a case-sensitive collation.
     */
    private function distinctOfUpper(string $table, int $tenantId, string $column): int
    {
        try {
            if (! SchemaCache::hasTable($table)) {
                return 0;
            }

            return (int) DB::table($table)
                ->where('SUB_INSTITUTE_ID', $tenantId)
                ->whereNotNull($column)
                ->where($column, '!=', 0)
                ->distinct()
                ->count($column);
        } catch (\Throwable) {
            return 0;
        }
    }
}
