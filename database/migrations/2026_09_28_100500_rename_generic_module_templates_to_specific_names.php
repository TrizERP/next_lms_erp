<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the 29 modules whose Templates/Prompts tab still shows the generic
 * "<Label> summary" / "<Label> analysis" / "<Label> Register" names the very first
 * publish-for-every-module migrations gave them, to names a person would actually
 * recognise and pick — the same quality bar Fees, Attendance, Admission, Students and the
 * five LMS + PAL modules already carry.
 *
 * SURGICAL ON PURPOSE. This touches ONLY `name` and `description` on rows that already
 * exist, matched by their unchanging `template_key`. It does not touch `data_source`,
 * `system_prompt`, `safety_rules`, `variables`, `module_key` or `status` — those already
 * correctly ground each template in that module's real records and refuse what that
 * module's data cannot support saying, and none of that is this migration's business to
 * rewrite. A row this migration cannot find (a school has renamed or retired it) is left
 * alone rather than recreated.
 *
 * NOT TOUCHED: fees, attendance, admissions, students, teach_learn, curriculum_planning,
 * engagement, interactions, new_pal, and the shared (module_key null) prompts — all
 * already carry specific, business-relevant names.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_28_100500_rename_generic_module_templates_to_specific_names.php
 */
return new class extends Migration
{
    /**
     * template_key => [name, description]. `description` is the one-line "what it is
     * for" text shown under the name; left as a genuine sentence about that module's real
     * records, not a copy-paste with the module name substituted.
     *
     * @return array<string, array{0:string,1:string}>
     */
    private function renames(): array
    {
        return [
            // Exam & Assessment — already fully built; renamed to match the requested
            // examples exactly (Assessment Summary / Exam Performance Report / Student
            // Result Summary), data_source and safety rules untouched.
            'k12.exam.summary' => ['Assessment Summary', 'A short summary of the recorded assessment marks on the screen.'],
            'k12.exam.analysis' => ['Student Result Summary', 'What a set of recorded results shows, and what the marks do not support saying.'],
            'k12.exam.report' => ['Exam Performance Report', 'Recorded marks by exam, subject and class, with absences reported separately from scores.'],

            'k12.certificate.summary' => ['Certificate Issue Summary', 'A short summary of certificates issued from the templates on file.'],
            'k12.certificate.analysis' => ['Certificate Type Analysis', 'How issued certificates break down by template and type.'],
            'k12.certificate.report' => ['Certificate Issue Register', 'Certificates issued, the template used and to whom.'],

            'k12.circular.summary' => ['Circular Publication Summary', 'A short summary of the circulars published and the classes they reached.'],
            'k12.circular.analysis' => ['Circular Type Analysis', 'How published circulars break down by type and class.'],
            'k12.circular.report' => ['Circular Register', 'Circulars published, their type and the classes they were sent to.'],

            'k12.complaint.summary' => ['Complaint Status Summary', 'A short summary of open and resolved complaints.'],
            'k12.complaint.analysis' => ['Overdue Complaint Analysis', 'Which complaints have been open longest, from the status recorded.'],
            'k12.complaint.report' => ['Complaint Register', 'Complaints recorded, their status and the department assigned.'],

            'k12.consent.summary' => ['Consent Response Summary', 'A short summary of consent requests raised and answered.'],
            'k12.consent.analysis' => ['Pending Consent Analysis', 'Which consent requests have no response recorded yet.'],
            'k12.consent.report' => ['Consent Register', 'Consent requests raised for students and whether a response is recorded.'],

            'k12.document-templates.summary' => ['Document Template Usage Summary', 'A short summary of the document templates on file and their versions.'],
            'k12.document-templates.analysis' => ['Draft vs Published Template Analysis', 'How templates break down between draft and published, by type.'],
            'k12.document-templates.report' => ['Document Template Register', 'Templates on file, their version and status.'],

            'k12.easy_com.summary' => ['Message Delivery Summary', 'A short summary of SMS, WhatsApp and app notifications sent.'],
            'k12.easy_com.analysis' => ['Channel Usage Analysis', 'How sent messages break down by channel.'],
            'k12.easy_com.report' => ['Communication Register', 'Messages sent, their channel and recipients.'],

            'k12.front_desk.summary' => ['Front Desk Visit Summary', 'A short summary of today\'s check-ins and check-outs.'],
            'k12.front_desk.analysis' => ['Check-in Without Exit Analysis', 'Which front-desk visits have no exit time recorded.'],
            'k12.front_desk.report' => ['Front Desk Register', 'Visits recorded at the front desk, their check-in and check-out times.'],

            'k12.hostel.summary' => ['Hostel Occupancy Summary', 'A short summary of hostel rooms and who is allocated where.'],
            'k12.hostel.analysis' => ['Room Allocation Analysis', 'How allocations spread across hostels and rooms, from the records on file.'],
            'k12.hostel.report' => ['Hostel Allocation Report', 'Rooms and the students allocated to them.'],

            'k12.institute.summary' => ['Institute Structure Summary', 'A short summary of the sections, standards and divisions on file.'],
            'k12.institute.analysis' => ['Department Distribution Analysis', 'How the academic structure and departments are organised.'],
            'k12.institute.report' => ['Institute Structure Report', 'Sections, standards and divisions recorded for this institute.'],

            'k12.inward_outward.summary' => ['Inward Register Summary', 'A short summary of documents received and entered in the inward register.'],
            'k12.inward_outward.analysis' => ['Unfiled Document Analysis', 'Which inward entries the register records no filed status for.'],
            'k12.inward_outward.report' => ['Inward Register Report', 'Documents received and logged in the inward register.'],

            'k12.library.summary' => ['Library Circulation Summary', 'A short summary of loans issued and currently out.'],
            'k12.library.analysis' => ['Overdue Book Analysis', 'Which loans are past their due date, from the circulation record.'],
            'k12.library.report' => ['Library Circulation Report', 'Loans issued, their borrower and whether they are out, returned or overdue.'],

            'k12.lms.summary' => ['Course Configuration Summary', 'A short summary of the courses and chapters configured for this institute.'],
            'k12.lms.analysis' => ['Content Coverage Analysis', 'Which configured courses carry no activity recorded against them.'],
            'k12.lms.report' => ['Course Register', 'Courses configured, their subject, class and status.'],

            'k12.migration-modules.summary' => ['Custom Module Summary', 'A short summary of the custom modules defined for this institute.'],
            'k12.migration-modules.analysis' => ['Academic Year Rollover Analysis', 'Which academic years have enrolments recorded, for rollover planning.'],
            'k12.migration-modules.report' => ['Custom Module Register', 'Custom modules defined and their configuration.'],

            'k12.mobile_apps.summary' => ['Mobile App Usage Summary', 'A short summary of the parent, student and teacher app home screen configuration.'],
            'k12.mobile_apps.analysis' => ['Home Screen Section Analysis', 'How home screen sections are configured across the apps.'],
            'k12.mobile_apps.report' => ['Mobile App Home Screen Report', 'Home screen sections configured for the mobile apps.'],

            'k12.parent_communication.summary' => ['Parent Message Summary', 'A short summary of messages parents sent in and how many have a reply recorded.'],
            'k12.parent_communication.analysis' => ['Unanswered Message Analysis', 'How long the oldest unanswered parent message has been waiting.'],
            'k12.parent_communication.report' => ['Parent Message Register', 'Messages parents wrote to the school and whether a reply is recorded.'],

            'k12.petty_cash.summary' => ['Petty Cash Spend Summary', 'A short summary of petty cash transactions recorded.'],
            'k12.petty_cash.analysis' => ['Expense Head Analysis', 'How petty cash spend breaks down by head.'],
            'k12.petty_cash.report' => ['Petty Cash Report', 'Petty cash transactions, their head and amount.'],

            'k12.ptm.summary' => ['PTM Booking Summary', 'A short summary of parent-teacher meeting slots and bookings.'],
            'k12.ptm.analysis' => ['PTM Attendance Analysis', 'Which PTM bookings have no attendance recorded yet.'],
            'k12.ptm.report' => ['Parent-Teacher Meeting Report', 'PTM slots, bookings and recorded attendance.'],

            'k12.sqaa.summary' => ['Quality Assurance Evidence Summary', 'A short summary of document slots and the evidence uploaded against them.'],
            'k12.sqaa.analysis' => ['Evidence Coverage Analysis', 'Which document slots have no evidence file attached.'],
            'k12.sqaa.report' => ['Quality Assurance Evidence Register', 'Document slots and the evidence recorded against them.'],

            'k12.student_icard.summary' => ['Student ID Card Summary', 'A short summary of students eligible for an identity card print.'],
            'k12.student_icard.analysis' => ['Pending Card Print Analysis', 'Which students are eligible for a card but have none printed yet.'],
            'k12.student_icard.report' => ['Student I-Card Print List', 'Students and the card details recorded for printing.'],

            'k12.student_medical.summary' => ['Infirmary Visit Summary', 'A short summary of infirmary visits recorded.'],
            'k12.student_medical.analysis' => ['Vaccination Coverage Analysis', 'How recorded vaccinations cover the students on file.'],
            'k12.student_medical.report' => ['Infirmary Visit Register', 'Infirmary visits, vaccinations and growth records on file.'],

            'k12.student_request.summary' => ['Student Request Summary', 'A short summary of change requests raised against student records.'],
            'k12.student_request.analysis' => ['Pending Request Analysis', 'Which student requests are still awaiting a decision.'],
            'k12.student_request.report' => ['Student Request Report', 'Requests raised, their type and current status.'],

            'k12.task_management.summary' => ['Task Status Summary', 'A short summary of tasks and their current status.'],
            'k12.task_management.analysis' => ['Overdue Task Analysis', 'Which tasks are overdue against their due date.'],
            'k12.task_management.report' => ['Task Report', 'Tasks recorded, their project, assignee and status.'],

            'k12.timetable.summary' => ['Class Timetable Summary', 'A short summary of the published class timetable.'],
            'k12.timetable.analysis' => ['Timetable Conflict Analysis', 'Where the published timetable records a clash.'],
            'k12.timetable.report' => ['Class Timetable Report', 'The published timetable by class and period.'],

            'k12.transportation.summary' => ['Transport Route Summary', 'A short summary of transport routes, vehicles and assignments.'],
            'k12.transportation.analysis' => ['Vehicle Assignment Analysis', 'How students are assigned across routes and vehicles.'],
            'k12.transportation.report' => ['Transport Route Report', 'Routes, their vehicles and assigned students.'],

            'k12.user.summary' => ['User Account Summary', 'A short summary of ERP accounts and their profiles.'],
            'k12.user.analysis' => ['Inactive Account Analysis', 'Which accounts have no login recorded.'],
            'k12.user.report' => ['User Account Register', 'ERP accounts, their profile and status.'],

            'k12.user_icard.summary' => ['Staff ID Card Summary', 'A short summary of staff eligible for an identity card print.'],
            'k12.user_icard.analysis' => ['Pending Staff Card Print Analysis', 'Which staff are eligible for a card but have none printed yet.'],
            'k12.user_icard.report' => ['Staff I-Card Print List', 'Staff and the card details recorded for printing.'],

            'k12.visitor_management.summary' => ['Visitor Log Summary', 'A short summary of today\'s visitors recorded at the gate.'],
            'k12.visitor_management.analysis' => ['Visitors Without Exit Analysis', 'Which visitors have no exit time recorded.'],
            'k12.visitor_management.report' => ['Visitor Register', 'Visits recorded at the gate, their purpose and times.'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        foreach ($this->renames() as $templateKey => [$name, $description]) {
            DB::table('ai_templates')
                ->where('template_key', $templateKey)
                ->whereNull('sub_institute_id')
                ->update([
                    'name' => $name,
                    'description' => $description,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Deliberately a no-op. The pre-rename names were generic placeholders
        // ("<Label> summary"), not a state worth restoring, and reconstructing them here
        // would duplicate the very declaration this migration exists to replace.
    }
};
