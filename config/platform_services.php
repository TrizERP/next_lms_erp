<?php

/*
|--------------------------------------------------------------------------
| Platform services registry — modules, components, and what each can be
| configured to do
|--------------------------------------------------------------------------
|
| THE ONE IDEA. Every configurable thing in the ERP is addressed as
| `module.component`. Fees is a module; `fees.collection` is a component. Under a
| component sit the three things the platform services configure:
|
|   `fees.collection.payment_received`  a notification event
|   `fees.collection.gateway_reconcile` a scheduled task
|   `fees.concession.flow`              a workflow point
|
| Communication, Scheduler and Workflow are three answers to the same question —
| *what should happen for this component?* — which is why they read one registry
| instead of each keeping a private list of modules.
|
| WHY THIS FILE IS THE SOURCE OF TRUTH, AND NOT THE FRONTEND.
| The screens must not be able to offer a setting the backend has no hook for,
| and the API must refuse a key nobody declared. Both follow from the registry
| living here and being served to the frontend at GET /api/platform/registry.
| Ports of this list into JavaScript were considered and rejected: two copies of
| a contract drift, and the copy that drifts is always the one that validates.
|
| WHY A DECLARED REGISTRY AND NOT THE LIVE MENU.
| tblmenumaster describes *screens a role may open*. A screen is not a thing that
| raises notifications, runs jobs or needs a sign-off. What these services
| configure is a component's behaviour, and behaviour has to be declared by the
| people who build the component. So a module appears here because someone wrote
| it here, and a component gains a notification the day its owner adds one.
|
| THIS FILE GRANTS NOTHING. Same rule as config/rbac_modules.php: it names what
| exists, never who may change it. Rights are asked of the RBAC registry under
| `platform.notification`, `platform.scheduler` and `platform.workflow`.
|
| DEFAULTS ARE OPINIONS, NOT DATA. Each default below is a considered position: a
| fee receipt is `locked` on email because a school cannot let somebody opt out of
| the record of money they paid; an exam result is not on SMS because six hundred
| messages at once is a bill nobody approved. A tenant overrides any of it and the
| override is what gets stored — the default stays here, so improving one reaches
| every school that never disagreed with it and none that did.
|
| ADDING TO IT. A new module needs an entry under `modules`; a new component needs
| one under `components` whose key starts with its module; a new event, task or
| workflow point needs one under the matching list with a key starting with its
| component. Nothing else — no migration, no frontend change.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery channels
    |--------------------------------------------------------------------------
    |
    | `enabled` is the position before any school has an opinion. Web and email
    | are on because they cost nothing and everybody has them; mobile is on
    | because the parent app is the primary parent surface. SMS and WhatsApp are
    | off until a school switches them on: both bill per message, both need
    | credentials configured first, and a service that starts spending money the
    | day it is installed is one nobody trusts again.
    |
    */
    'channels' => [
        'web' => [
            'label' => 'Web',
            'description' => 'The bell in the top bar and the notification list.',
            'enabled' => true,
            'needs_credentials' => false,
        ],
        'email' => [
            'label' => 'Email',
            'description' => 'Sent from the institute address configured in Integration.',
            'enabled' => true,
            'needs_credentials' => true,
        ],
        'mobile' => [
            'label' => 'Mobile app',
            'description' => 'Push notification to the parent and staff apps.',
            'enabled' => true,
            'needs_credentials' => true,
        ],
        'sms' => [
            'label' => 'SMS',
            'description' => 'Billed per message. Needs an SMS gateway and approved sender templates.',
            'enabled' => false,
            'needs_credentials' => true,
        ],
        'whatsapp' => [
            'label' => 'WhatsApp',
            'description' => 'Billed per conversation. Needs an approved business account and templates.',
            'enabled' => false,
            'needs_credentials' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | `group` and `icon` are for the module picker and carry no behaviour.
    |
    */
    'modules' => [
        'admissions'    => ['label' => 'Admissions',    'group' => 'Academics',      'icon' => 'user-plus',              'description' => 'Enquiries, registrations, applications and confirmation.'],
        'students'      => ['label' => 'Students',      'group' => 'Academics',      'icon' => 'users',                  'description' => 'Student records, promotion, transfer and identity.'],
        'attendance'    => ['label' => 'Attendance',    'group' => 'Academics',      'icon' => 'calendar-check',         'description' => 'Daily student and staff attendance, and leave.'],
        'examination'   => ['label' => 'Examination',   'group' => 'Academics',      'icon' => 'file-check',             'description' => 'Exam schedules, marks, results and report cards.'],
        'academics'     => ['label' => 'Academics',     'group' => 'Academics',      'icon' => 'book-open',              'description' => 'Timetable, syllabus, lesson plans and homework.'],
        'lms'           => ['label' => 'Learning',      'group' => 'Academics',      'icon' => 'graduation-cap',         'description' => 'Courses, content, assignments and assessments.'],
        'fees'          => ['label' => 'Fees',          'group' => 'Administration', 'icon' => 'receipt-indian-rupee',   'description' => 'Fee structures, collection, dues, concession and refunds.'],
        'hr'            => ['label' => 'HR & Payroll',  'group' => 'People',         'icon' => 'briefcase',              'description' => 'Staff records, leave, payroll and appraisal.'],
        'front_desk'    => ['label' => 'Front desk',    'group' => 'Operations',     'icon' => 'concierge-bell',         'description' => 'Visitors, enquiries, call logs and complaints.'],
        'library'       => ['label' => 'Library',       'group' => 'Operations',     'icon' => 'library',                'description' => 'Catalogue, issue and return, and fines.'],
        'hostel'        => ['label' => 'Hostel',        'group' => 'Operations',     'icon' => 'bed-double',             'description' => 'Room allocation, mess and gate passes.'],
        'transport'     => ['label' => 'Transport',     'group' => 'Operations',     'icon' => 'bus',                    'description' => 'Routes, vehicles, drivers and trip tracking.'],
        'inventory'     => ['label' => 'Inventory',     'group' => 'Operations',     'icon' => 'package',                'description' => 'Stock, purchase and issue of school material.'],
        'communication' => ['label' => 'Communication', 'group' => 'Administration', 'icon' => 'megaphone',              'description' => 'Circulars, campaigns and parent messaging.'],
        'reports'       => ['label' => 'Reports',       'group' => 'Administration', 'icon' => 'bar-chart-3',            'description' => 'Scheduled reports, exports and statutory returns.'],
        'compliance'    => ['label' => 'Compliance',    'group' => 'Administration', 'icon' => 'shield-check',           'description' => 'Documents, accreditation evidence and retention.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Components
    |--------------------------------------------------------------------------
    |
    | Keyed `module.component`. The module half is authoritative — a component
    | whose key does not start with a declared module is a configuration error
    | and PlatformRegistry rejects it at boot rather than serving it.
    |
    */
    'components' => [
        // Admissions
        'admissions.enquiry'       => ['label' => 'Enquiry',               'description' => 'A prospective family asking about a seat.'],
        'admissions.registration'  => ['label' => 'Registration',          'description' => 'A registered applicant with a form number.'],
        'admissions.application'   => ['label' => 'Application',           'description' => 'The submitted form, its documents and its review.'],
        'admissions.confirmation'  => ['label' => 'Admission confirmation', 'description' => 'Offer, acceptance and seat allotment.'],

        // Students
        'students.profile'    => ['label' => 'Student profile',      'description' => 'The student record and its guardians.'],
        'students.promotion'  => ['label' => 'Promotion',            'description' => 'Year-end movement to the next class.'],
        'students.transfer'   => ['label' => 'Transfer certificate', 'description' => 'Leaving certificate, dues clearance and exit.'],
        'students.identity'   => ['label' => 'ID card',              'description' => 'Identity card issue and reissue.'],

        // Attendance
        'attendance.student' => ['label' => 'Student attendance', 'description' => 'Daily and period-wise marking.'],
        'attendance.staff'   => ['label' => 'Staff attendance',   'description' => 'Staff sign-in, biometric and muster.'],
        'attendance.leave'   => ['label' => 'Student leave',      'description' => 'Leave applications from guardians.'],

        // Examination
        'examination.schedule'    => ['label' => 'Exam schedule', 'description' => 'Datesheet, seating and invigilation.'],
        'examination.marks'       => ['label' => 'Marks entry',   'description' => 'Subject marks captured by teachers.'],
        'examination.result'      => ['label' => 'Result',        'description' => 'Computed result and its publication.'],
        'examination.report_card' => ['label' => 'Report card',   'description' => 'The printed and shared progress report.'],

        // Academics
        'academics.timetable'   => ['label' => 'Timetable',   'description' => 'Class, teacher and room scheduling.'],
        'academics.syllabus'    => ['label' => 'Syllabus',    'description' => 'Curriculum plan and coverage tracking.'],
        'academics.lesson_plan' => ['label' => 'Lesson plan', 'description' => 'Teacher lesson plans and their review.'],
        'academics.homework'    => ['label' => 'Homework',    'description' => 'Daily homework given to a class.'],

        // Learning
        'lms.course'     => ['label' => 'Course',           'description' => 'A course, its chapters and its enrolment.'],
        'lms.assignment' => ['label' => 'Assignment',       'description' => 'Assignments, submissions and grading.'],
        'lms.quiz'       => ['label' => 'Quiz',             'description' => 'Quizzes, attempts and scores.'],
        'lms.content'    => ['label' => 'Content library',  'description' => 'Uploaded and authored learning content.'],

        // Fees
        'fees.structure'  => ['label' => 'Fee structure',  'description' => 'Heads, instalments and class-wise amounts.'],
        'fees.collection' => ['label' => 'Fee collection', 'description' => 'Payment capture, online and at the counter.'],
        'fees.receipt'    => ['label' => 'Receipt',        'description' => 'The durable record issued for a payment.'],
        'fees.defaulter'  => ['label' => 'Defaulters',     'description' => 'Overdue instalments and reminder runs.'],
        'fees.concession' => ['label' => 'Concession',     'description' => 'Waivers, scholarships and sibling discounts.'],
        'fees.refund'     => ['label' => 'Refund',         'description' => 'Money returned to a family.'],

        // HR & Payroll
        'hr.staff'     => ['label' => 'Staff records', 'description' => 'Employment record, documents and roles.'],
        'hr.leave'     => ['label' => 'Staff leave',   'description' => 'Leave balance, applications and approval.'],
        'hr.payroll'   => ['label' => 'Payroll',       'description' => 'Salary run, payslips and statutory deductions.'],
        'hr.appraisal' => ['label' => 'Appraisal',     'description' => 'Review cycles and outcomes.'],

        // Front desk
        'front_desk.visitor'   => ['label' => 'Visitor',            'description' => 'Gate entry, passes and exit.'],
        'front_desk.call_log'  => ['label' => 'Call log',           'description' => 'Calls received and their follow-up.'],
        'front_desk.complaint' => ['label' => 'Complaint',          'description' => 'Parent complaints and their resolution.'],
        'front_desk.gate_pass' => ['label' => 'Student gate pass',  'description' => 'Early release of a student during school hours.'],

        // Library
        'library.catalogue'   => ['label' => 'Catalogue',        'description' => 'Titles, copies and accession.'],
        'library.circulation' => ['label' => 'Issue and return', 'description' => 'Borrowing, renewal and return.'],
        'library.fine'        => ['label' => 'Fines',            'description' => 'Overdue and damage charges.'],

        // Hostel
        'hostel.allocation' => ['label' => 'Room allocation',   'description' => 'Rooms, beds and occupancy.'],
        'hostel.mess'       => ['label' => 'Mess',              'description' => 'Menu, attendance and mess charges.'],
        'hostel.gate_pass'  => ['label' => 'Hostel gate pass',  'description' => 'Leaving and returning to the hostel.'],

        // Transport
        'transport.route'   => ['label' => 'Route',         'description' => 'Routes, stops and allocation of students.'],
        'transport.vehicle' => ['label' => 'Vehicle',       'description' => 'Vehicles, drivers, fitness and insurance.'],
        'transport.trip'    => ['label' => 'Trip tracking', 'description' => 'Live trips, boarding and arrival.'],

        // Inventory
        'inventory.stock'    => ['label' => 'Stock',    'description' => 'Items, quantities and reorder levels.'],
        'inventory.purchase' => ['label' => 'Purchase', 'description' => 'Indents, purchase orders and receipt of goods.'],
        'inventory.issue'    => ['label' => 'Issue',    'description' => 'Material issued to a person or department.'],

        // Communication
        'communication.circular' => ['label' => 'Circular',         'description' => 'School-wide or class-wide announcements.'],
        'communication.campaign' => ['label' => 'Campaign',         'description' => 'Bulk SMS, email and WhatsApp sends.'],
        'communication.message'  => ['label' => 'Parent messaging', 'description' => 'One-to-one messages between staff and guardians.'],

        // Reports
        'reports.scheduled' => ['label' => 'Scheduled report', 'description' => 'Reports built and delivered on a schedule.'],
        'reports.export'    => ['label' => 'Data export',      'description' => 'Bulk extracts of module data.'],

        // Compliance
        'compliance.document'      => ['label' => 'Document repository', 'description' => 'Statutory and institutional documents.'],
        'compliance.accreditation' => ['label' => 'Accreditation',       'description' => 'Evidence collection for inspection and audit.'],
        'compliance.retention'     => ['label' => 'Data retention',      'description' => 'Archival and purge of aged records.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification events
    |--------------------------------------------------------------------------
    |
    | Keyed `module.component.event`. `defaults` names each channel as one of:
    |
    |   'on'     delivered by default, the recipient may turn it off
    |   'off'    available, not delivered until somebody asks for it
    |   'locked' delivered, and the recipient may NOT turn it off
    |
    | A channel left out is off and unlocked.
    |
    | `mandatory` means the event itself may not be switched off — a receipt, a
    | safeguarding alert. Channels stay tunable; existence does not.
    |
    */
    'notifications' => [
        // ── Admissions ──────────────────────────────────────────────────────
        'admissions.enquiry.received' => [
            'label' => 'Enquiry received', 'description' => 'A new enquiry has been logged.',
            'audience' => ['Admissions team'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'admissions.enquiry.follow_up_due' => [
            'label' => 'Follow-up due', 'description' => 'An enquiry has gone unanswered past its follow-up date.',
            'audience' => ['Admissions counsellor'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'admissions.registration.submitted' => [
            'label' => 'Registration submitted', 'description' => 'A family has completed the registration form.',
            'audience' => ['Parent', 'Admissions team'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'sms' => 'on'],
        ],
        'admissions.application.document_missing' => [
            'label' => 'Documents missing', 'description' => 'The application is short of a required document.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'whatsapp' => 'on'],
        ],
        'admissions.application.interview_scheduled' => [
            'label' => 'Interview scheduled', 'description' => 'An interaction slot has been fixed for the applicant.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'admissions.confirmation.offer_issued' => [
            'label' => 'Offer issued', 'description' => 'A seat has been offered, with a date to accept by.',
            'audience' => ['Parent'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'admissions.confirmation.admitted' => [
            'label' => 'Admission confirmed', 'description' => 'The seat is taken and an admission number is issued.',
            'audience' => ['Parent', 'Class teacher'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on', 'sms' => 'on'],
        ],

        // ── Students ────────────────────────────────────────────────────────
        'students.profile.updated' => [
            'label' => 'Profile changed', 'description' => 'A guardian, contact number or address was changed.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'students.promotion.completed' => [
            'label' => 'Promoted to next class', 'description' => 'The student has been moved to the next class and section.',
            'audience' => ['Parent', 'Class teacher'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'students.transfer.issued' => [
            'label' => 'Transfer certificate issued', 'description' => 'The leaving certificate has been generated.',
            'audience' => ['Parent'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on', 'sms' => 'on'],
        ],
        'students.identity.ready' => [
            'label' => 'ID card ready', 'description' => 'The identity card may be collected.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],

        // ── Attendance ──────────────────────────────────────────────────────
        'attendance.student.absent' => [
            'label' => 'Student marked absent', 'description' => 'Sent the same morning the student is marked absent.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'attendance.student.low_percentage' => [
            'label' => 'Attendance below threshold', 'description' => 'The student has fallen under the required attendance.',
            'audience' => ['Parent', 'Class teacher'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'attendance.student.not_marked' => [
            'label' => 'Attendance not marked', 'description' => 'A class has no attendance recorded past the cut-off.',
            'audience' => ['Class teacher', 'Principal'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'attendance.staff.absent' => [
            'label' => 'Staff absent', 'description' => 'A staff member has not signed in by the cut-off.',
            'audience' => ['HR', 'Principal'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'attendance.leave.applied' => [
            'label' => 'Leave applied', 'description' => 'A guardian has applied for student leave.',
            'audience' => ['Class teacher'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],
        'attendance.leave.decided' => [
            'label' => 'Leave approved or rejected', 'description' => 'The decision on a leave application.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],

        // ── Examination ─────────────────────────────────────────────────────
        'examination.schedule.published' => [
            'label' => 'Datesheet published', 'description' => 'The exam timetable is available to students.',
            'audience' => ['Parent', 'Student', 'Teacher'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'whatsapp' => 'on'],
        ],
        'examination.marks.entry_due' => [
            'label' => 'Marks entry due', 'description' => 'A subject teacher has marks still to enter.',
            'audience' => ['Teacher'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'examination.result.published' => [
            'label' => 'Result published', 'description' => 'The result is now visible to the family.',
            'audience' => ['Parent', 'Student'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'whatsapp' => 'on'],
        ],
        'examination.report_card.ready' => [
            'label' => 'Report card ready', 'description' => 'The progress report may be downloaded.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],

        // ── Academics ───────────────────────────────────────────────────────
        'academics.timetable.changed' => [
            'label' => 'Timetable changed', 'description' => 'A period, teacher or room has been altered.',
            'audience' => ['Teacher', 'Student'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],
        'academics.syllabus.behind' => [
            'label' => 'Syllabus coverage behind plan', 'description' => 'A subject is behind its planned coverage.',
            'audience' => ['Teacher', 'Academic head'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'academics.lesson_plan.review_due' => [
            'label' => 'Lesson plan awaiting review', 'description' => 'A submitted lesson plan needs a reviewer.',
            'audience' => ['Academic head'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'academics.homework.assigned' => [
            'label' => 'Homework assigned', 'description' => 'Homework has been given to the class.',
            'audience' => ['Parent', 'Student'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],

        // ── Learning ────────────────────────────────────────────────────────
        'lms.course.enrolled' => [
            'label' => 'Enrolled in a course', 'description' => 'A student has been added to a course.',
            'audience' => ['Student', 'Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'lms.assignment.due_soon' => [
            'label' => 'Assignment due soon', 'description' => 'An assignment is due within the reminder window.',
            'audience' => ['Student', 'Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'lms.assignment.overdue' => [
            'label' => 'Assignment overdue', 'description' => 'The due date has passed with nothing submitted.',
            'audience' => ['Student', 'Parent', 'Teacher'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'lms.assignment.graded' => [
            'label' => 'Assignment graded', 'description' => 'A submission has been marked and returned.',
            'audience' => ['Student', 'Parent'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],
        'lms.quiz.attempt_graded' => [
            'label' => 'Quiz attempt graded', 'description' => 'A quiz attempt has a score.',
            'audience' => ['Student'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],
        'lms.content.published' => [
            'label' => 'New content published', 'description' => 'New material has been added to a course.',
            'audience' => ['Student'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],

        // ── Fees ────────────────────────────────────────────────────────────
        'fees.structure.published' => [
            'label' => 'Fee structure published', 'description' => 'The year or term fee structure is now in force.',
            'audience' => ['Parent', 'Accounts'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'fees.collection.payment_received' => [
            'label' => 'Payment received', 'description' => 'A payment has been captured against an instalment.',
            'audience' => ['Parent'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'fees.collection.payment_failed' => [
            'label' => 'Online payment failed', 'description' => 'A gateway attempt did not complete.',
            'audience' => ['Parent', 'Accounts'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'sms' => 'on'],
        ],
        'fees.receipt.issued' => [
            'label' => 'Receipt issued', 'description' => 'The durable receipt, with its reference number.',
            'audience' => ['Parent'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on', 'whatsapp' => 'on'],
        ],
        'fees.defaulter.due_soon' => [
            'label' => 'Instalment due soon', 'description' => 'A reminder ahead of the due date.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'fees.defaulter.overdue' => [
            'label' => 'Instalment overdue', 'description' => 'The due date has passed with the amount unpaid.',
            'audience' => ['Parent', 'Accounts'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'fees.concession.decided' => [
            'label' => 'Concession approved or rejected', 'description' => 'The decision on a waiver request.',
            'audience' => ['Parent', 'Accounts'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'fees.refund.processed' => [
            'label' => 'Refund processed', 'description' => 'Money has been returned, with its reference.',
            'audience' => ['Parent', 'Accounts'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on', 'sms' => 'on'],
        ],

        // ── HR ──────────────────────────────────────────────────────────────
        'hr.staff.document_expiring' => [
            'label' => 'Staff document expiring', 'description' => 'A certificate or licence is close to expiry.',
            'audience' => ['HR'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'hr.leave.applied' => [
            'label' => 'Leave applied', 'description' => 'A staff member has applied for leave.',
            'audience' => ['Reporting manager'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'hr.leave.decided' => [
            'label' => 'Leave approved or rejected', 'description' => 'The decision on a leave application.',
            'audience' => ['Staff'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'hr.payroll.payslip_ready' => [
            'label' => 'Payslip available', 'description' => 'The month payslip may be downloaded.',
            'audience' => ['Staff'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on'],
        ],
        'hr.appraisal.cycle_open' => [
            'label' => 'Appraisal cycle open', 'description' => 'Self-assessment is open until the closing date.',
            'audience' => ['Staff', 'Reporting manager'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],

        // ── Front desk ──────────────────────────────────────────────────────
        'front_desk.visitor.arrived' => [
            'label' => 'Visitor arrived', 'description' => 'A visitor has been checked in for a staff member.',
            'audience' => ['Host staff'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],
        'front_desk.complaint.logged' => [
            'label' => 'Complaint logged', 'description' => 'A parent complaint has been recorded.',
            'audience' => ['Front desk', 'Principal'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'front_desk.complaint.resolved' => [
            'label' => 'Complaint resolved', 'description' => 'The complaint has been closed, with the outcome.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'whatsapp' => 'on'],
        ],
        'front_desk.call_log.follow_up_due' => [
            'label' => 'Call follow-up due', 'description' => 'A logged call was promised a call back and has not had one.',
            'audience' => ['Front desk'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'front_desk.gate_pass.issued' => [
            'label' => 'Student released early', 'description' => 'A student has left the campus during school hours.',
            'audience' => ['Parent', 'Class teacher'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'locked', 'sms' => 'on', 'whatsapp' => 'on'],
        ],

        // ── Library ─────────────────────────────────────────────────────────
        'library.catalogue.copies_missing' => [
            'label' => 'Copies missing after stock audit', 'description' => 'Copies not accounted for in the latest stock audit.',
            'audience' => ['Librarian'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'library.circulation.due_soon' => [
            'label' => 'Book due soon', 'description' => 'A borrowed title is due back shortly.',
            'audience' => ['Student', 'Staff'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'library.circulation.overdue' => [
            'label' => 'Book overdue', 'description' => 'A borrowed title is past its return date.',
            'audience' => ['Student', 'Parent', 'Librarian'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'library.fine.raised' => [
            'label' => 'Library fine raised', 'description' => 'A charge has been added for overdue or damage.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],

        // ── Hostel ──────────────────────────────────────────────────────────
        'hostel.allocation.assigned' => [
            'label' => 'Room allotted', 'description' => 'A bed has been allotted to the student.',
            'audience' => ['Parent', 'Warden'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],
        'hostel.gate_pass.movement' => [
            'label' => 'Hostel exit or return', 'description' => 'The student has left or returned to the hostel.',
            'audience' => ['Parent', 'Warden'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'mobile' => 'locked', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'hostel.mess.charge_raised' => [
            'label' => 'Mess charge raised', 'description' => 'The month mess bill has been posted.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on'],
        ],

        // ── Transport ───────────────────────────────────────────────────────
        'transport.route.changed' => [
            'label' => 'Route or stop changed', 'description' => 'The route, stop or pickup time for a student has been altered.',
            'audience' => ['Parent', 'Transport in-charge'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'transport.trip.boarded' => [
            'label' => 'Boarded the bus', 'description' => 'The student has boarded at their stop.',
            'audience' => ['Parent'],
            'defaults' => ['mobile' => 'on'],
        ],
        'transport.trip.delayed' => [
            'label' => 'Bus delayed', 'description' => 'The trip is running late against its schedule.',
            'audience' => ['Parent'],
            'defaults' => ['web' => 'on', 'mobile' => 'on', 'sms' => 'on', 'whatsapp' => 'on'],
        ],
        'transport.vehicle.document_expiring' => [
            'label' => 'Vehicle document expiring', 'description' => 'Fitness, insurance or permit is close to expiry.',
            'audience' => ['Transport in-charge'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked', 'mobile' => 'on'],
        ],

        // ── Inventory ───────────────────────────────────────────────────────
        'inventory.stock.below_reorder' => [
            'label' => 'Stock below reorder level', 'description' => 'An item has fallen under its reorder quantity.',
            'audience' => ['Store keeper', 'Purchase'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'inventory.purchase.order_approved' => [
            'label' => 'Purchase order approved', 'description' => 'A purchase order has cleared its approval chain.',
            'audience' => ['Purchase', 'Accounts'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],

        // ── Communication ───────────────────────────────────────────────────
        'communication.circular.published' => [
            'label' => 'Circular published', 'description' => 'A circular has been released to its audience.',
            'audience' => ['Parent', 'Staff'],
            'defaults' => ['web' => 'on', 'email' => 'on', 'mobile' => 'on', 'whatsapp' => 'on'],
        ],
        'communication.campaign.completed' => [
            'label' => 'Campaign finished', 'description' => 'A bulk send has finished, with delivery counts.',
            'audience' => ['Communication team'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'communication.campaign.delivery_failed' => [
            'label' => 'Delivery failures in a campaign', 'description' => 'Messages that could not be delivered, with reasons.',
            'audience' => ['Communication team'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'communication.message.received' => [
            'label' => 'New message', 'description' => 'A one-to-one message has arrived.',
            'audience' => ['Parent', 'Staff'],
            'defaults' => ['web' => 'on', 'mobile' => 'on'],
        ],

        // ── Reports ─────────────────────────────────────────────────────────
        'reports.scheduled.delivered' => [
            'label' => 'Scheduled report delivered', 'description' => 'A scheduled report has been produced and sent.',
            'audience' => ['Report recipients'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'reports.export.ready' => [
            'label' => 'Export ready', 'description' => 'A requested extract is ready to download.',
            'audience' => ['Requesting user'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],

        // ── Compliance ──────────────────────────────────────────────────────
        'compliance.document.expiring' => [
            'label' => 'Document expiring', 'description' => 'A statutory document is close to its expiry date.',
            'audience' => ['Compliance officer', 'Principal'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked'],
        ],
        'compliance.accreditation.evidence_due' => [
            'label' => 'Evidence due', 'description' => 'An accreditation criterion still has no evidence attached.',
            'audience' => ['Compliance officer'],
            'defaults' => ['web' => 'on', 'email' => 'on'],
        ],
        'compliance.retention.purge_scheduled' => [
            'label' => 'Records due for purge', 'description' => 'Records have reached the end of their retention period.',
            'audience' => ['Compliance officer'], 'mandatory' => true,
            'defaults' => ['web' => 'on', 'email' => 'locked'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled tasks
    |--------------------------------------------------------------------------
    |
    | Keyed `module.component.task`. `schedule` is the five-field cron the
    | Scheduler screen edits — minute, hour, day of month, month, day of week —
    | and is the schedule shipped with the product; a tenant's override is stored
    | separately and never edits this.
    |
    | `disabled_by_default` is true for tasks that spend money or send in bulk. A
    | school opts into those; it does not discover them from its SMS bill.
    |
    */
    'tasks' => [
        // Admissions
        'admissions.enquiry.follow_up_scan' => [
            'label' => 'Scan for overdue enquiry follow-ups',
            'description' => 'Finds enquiries past their follow-up date and raises the reminder.',
            'schedule' => ['minute' => '0', 'hour' => '9', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'admissions.application.document_chase' => [
            'label' => 'Chase missing application documents',
            'description' => 'Reminds families whose application is short of a document.',
            'schedule' => ['minute' => '30', 'hour' => '10', 'day' => '*', 'month' => '*', 'day_of_week' => '1-5'],
        ],
        'admissions.confirmation.offer_expiry' => [
            'label' => 'Expire unaccepted offers',
            'description' => 'Releases seats whose offer window has closed.',
            'schedule' => ['minute' => '0', 'hour' => '1', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Students
        'students.profile.data_quality' => [
            'label' => 'Student data quality check',
            'description' => 'Reports records missing a guardian contact, date of birth or address.',
            'schedule' => ['minute' => '0', 'hour' => '2', 'day' => '*', 'month' => '*', 'day_of_week' => '1'],
        ],
        'students.identity.card_batch' => [
            'label' => 'Build ID card print batch',
            'description' => 'Collects students with no issued card into a print batch.',
            'schedule' => ['minute' => '0', 'hour' => '3', 'day' => '1', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Attendance
        'attendance.student.absentee_notice' => [
            'label' => 'Send morning absentee notices',
            'description' => 'Notifies guardians of students marked absent today.',
            'schedule' => ['minute' => '30', 'hour' => '10', 'day' => '*', 'month' => '*', 'day_of_week' => '1-6'],
        ],
        'attendance.student.unmarked_check' => [
            'label' => 'Check for unmarked classes',
            'description' => 'Flags classes with no attendance recorded past the cut-off.',
            'schedule' => ['minute' => '0', 'hour' => '12', 'day' => '*', 'month' => '*', 'day_of_week' => '1-6'],
        ],
        'attendance.student.shortfall_scan' => [
            'label' => 'Attendance shortfall scan',
            'description' => 'Finds students below the required attendance percentage.',
            'schedule' => ['minute' => '0', 'hour' => '4', 'day' => '1', 'month' => '*', 'day_of_week' => '*'],
        ],
        'attendance.staff.muster_close' => [
            'label' => 'Close the daily staff muster',
            'description' => 'Freezes the day attendance and marks absences.',
            'schedule' => ['minute' => '0', 'hour' => '20', 'day' => '*', 'month' => '*', 'day_of_week' => '1-6'],
        ],

        // Examination
        'examination.marks.entry_reminder' => [
            'label' => 'Remind teachers of pending marks',
            'description' => 'Reminds subject teachers with marks still to enter.',
            'schedule' => ['minute' => '0', 'hour' => '16', 'day' => '*', 'month' => '*', 'day_of_week' => '1-5'],
        ],
        'examination.result.recompute' => [
            'label' => 'Recompute pending results',
            'description' => 'Rebuilds results where marks changed since the last computation.',
            'schedule' => ['minute' => '0', 'hour' => '23', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Academics
        'academics.syllabus.coverage_rollup' => [
            'label' => 'Roll up syllabus coverage',
            'description' => 'Recalculates planned against actual coverage per subject.',
            'schedule' => ['minute' => '0', 'hour' => '22', 'day' => '*', 'month' => '*', 'day_of_week' => '5'],
        ],
        'academics.homework.digest' => [
            'label' => 'Send the homework digest',
            'description' => 'One evening summary per class instead of a message per subject.',
            'schedule' => ['minute' => '0', 'hour' => '18', 'day' => '*', 'month' => '*', 'day_of_week' => '1-5'],
        ],

        // Learning
        'lms.assignment.due_reminder' => [
            'label' => 'Remind about assignments due',
            'description' => 'Notifies students with an assignment due inside the window.',
            'schedule' => ['minute' => '0', 'hour' => '17', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'lms.assignment.overdue_scan' => [
            'label' => 'Scan for overdue assignments',
            'description' => 'Finds submissions that never arrived and tells the teacher.',
            'schedule' => ['minute' => '0', 'hour' => '21', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'lms.content.index_rebuild' => [
            'label' => 'Rebuild the content search index',
            'description' => 'Re-indexes learning content so search reflects new material.',
            'schedule' => ['minute' => '0', 'hour' => '3', 'day' => '*', 'month' => '*', 'day_of_week' => '0'],
        ],

        // Fees
        'fees.defaulter.overdue_scan' => [
            'label' => 'Scan for overdue instalments',
            'description' => 'Marks instalments overdue and updates the defaulter list.',
            'schedule' => ['minute' => '0', 'hour' => '1', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'fees.defaulter.reminder_run' => [
            'label' => 'Send fee reminders',
            'description' => 'Sends the due-soon and overdue reminders to families.',
            'schedule' => ['minute' => '0', 'hour' => '10', 'day' => '*', 'month' => '*', 'day_of_week' => '1'],
            'disabled_by_default' => true,
        ],
        'fees.collection.gateway_reconcile' => [
            'label' => 'Reconcile the payment gateway',
            'description' => 'Matches gateway settlements against captured payments.',
            'schedule' => ['minute' => '0', 'hour' => '2', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'fees.receipt.daily_close' => [
            'label' => 'Close the day collection',
            'description' => 'Freezes the day counter collection and produces the summary.',
            'schedule' => ['minute' => '0', 'hour' => '21', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // HR
        'hr.staff.document_expiry_scan' => [
            'label' => 'Scan staff documents for expiry',
            'description' => 'Finds certificates and licences nearing expiry.',
            'schedule' => ['minute' => '0', 'hour' => '5', 'day' => '*', 'month' => '*', 'day_of_week' => '1'],
        ],
        'hr.leave.balance_accrual' => [
            'label' => 'Accrue monthly leave balance',
            'description' => 'Adds the month leave entitlement to every staff balance.',
            'schedule' => ['minute' => '0', 'hour' => '0', 'day' => '1', 'month' => '*', 'day_of_week' => '*'],
        ],
        'hr.payroll.monthly_run' => [
            'label' => 'Prepare the monthly payroll',
            'description' => 'Builds the salary run from attendance and deductions, for review.',
            'schedule' => ['minute' => '0', 'hour' => '2', 'day' => '25', 'month' => '*', 'day_of_week' => '*'],
            'disabled_by_default' => true,
        ],

        // Front desk
        'front_desk.visitor.auto_checkout' => [
            'label' => 'Auto check-out stale visitors',
            'description' => 'Closes visitor passes left open at the end of the day.',
            'schedule' => ['minute' => '0', 'hour' => '22', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'front_desk.complaint.escalation_scan' => [
            'label' => 'Escalate ageing complaints',
            'description' => 'Escalates complaints open beyond their resolution window.',
            'schedule' => ['minute' => '0', 'hour' => '8', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Front desk
        'front_desk.call_log.follow_up_scan' => [
            'label' => 'Scan for pending call follow-ups',
            'description' => 'Finds logged calls promised a call back that have not had one.',
            'schedule' => ['minute' => '0', 'hour' => '9', 'day' => '*', 'month' => '*', 'day_of_week' => '1-6'],
        ],

        // Library
        'library.catalogue.stock_audit' => [
            'label' => 'Reconcile the catalogue',
            'description' => 'Compares accessioned copies against what circulation and the shelf report, and flags the gap.',
            'schedule' => ['minute' => '0', 'hour' => '2', 'day' => '1', 'month' => '*', 'day_of_week' => '*'],
        ],
        'library.circulation.overdue_scan' => [
            'label' => 'Scan for overdue books',
            'description' => 'Marks loans overdue and notifies the borrower.',
            'schedule' => ['minute' => '0', 'hour' => '6', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'library.fine.accrue' => [
            'label' => 'Accrue overdue fines',
            'description' => 'Adds the day fine to every overdue loan.',
            'schedule' => ['minute' => '30', 'hour' => '6', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Hostel
        'hostel.mess.monthly_billing' => [
            'label' => 'Raise monthly mess bills',
            'description' => 'Posts the month mess charge per resident.',
            'schedule' => ['minute' => '0', 'hour' => '3', 'day' => '1', 'month' => '*', 'day_of_week' => '*'],
        ],
        'hostel.gate_pass.overdue_return' => [
            'label' => 'Flag overdue hostel returns',
            'description' => 'Alerts the warden about residents past their return time.',
            'schedule' => ['minute' => '0', 'hour' => '21', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Transport
        'transport.vehicle.document_expiry_scan' => [
            'label' => 'Scan vehicle documents for expiry',
            'description' => 'Finds fitness, insurance and permits nearing expiry.',
            'schedule' => ['minute' => '0', 'hour' => '5', 'day' => '*', 'month' => '*', 'day_of_week' => '1'],
        ],
        'transport.trip.delay_watch' => [
            'label' => 'Watch trips for delay',
            'description' => 'Compares live trips against schedule and raises delay alerts.',
            'schedule' => ['minute' => '*/10', 'hour' => '7,8,14,15,16', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Inventory
        'inventory.stock.reorder_scan' => [
            'label' => 'Scan stock for reorder',
            'description' => 'Finds items below their reorder level and raises an indent.',
            'schedule' => ['minute' => '0', 'hour' => '7', 'day' => '*', 'month' => '*', 'day_of_week' => '1'],
        ],

        // Communication
        'communication.campaign.dispatch_queue' => [
            'label' => 'Dispatch the message queue',
            'description' => 'Sends queued messages within the sending window.',
            'schedule' => ['minute' => '*/5', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'communication.campaign.delivery_reconcile' => [
            'label' => 'Reconcile delivery receipts',
            'description' => 'Pulls delivery status back from the SMS and WhatsApp providers.',
            'schedule' => ['minute' => '*/30', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Reports
        'reports.scheduled.dispatch' => [
            'label' => 'Run scheduled reports',
            'description' => 'Builds every report due now and delivers it to its recipients.',
            'schedule' => ['minute' => '0', 'hour' => '6', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],
        'reports.export.cleanup' => [
            'label' => 'Clean up finished exports',
            'description' => 'Removes generated export files past their download window.',
            'schedule' => ['minute' => '0', 'hour' => '4', 'day' => '*', 'month' => '*', 'day_of_week' => '*'],
        ],

        // Compliance
        'compliance.document.expiry_scan' => [
            'label' => 'Scan documents for expiry',
            'description' => 'Finds statutory documents nearing their expiry date.',
            'schedule' => ['minute' => '0', 'hour' => '5', 'day' => '*', 'month' => '*', 'day_of_week' => '1'],
        ],
        'compliance.retention.purge_run' => [
            'label' => 'Purge records past retention',
            'description' => 'Archives and removes records past their retention period.',
            'schedule' => ['minute' => '0', 'hour' => '3', 'day' => '1', 'month' => '*', 'day_of_week' => '*'],
            'disabled_by_default' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow points
    |--------------------------------------------------------------------------
    |
    | Keyed `module.component.flow`: the actions in each component that can pause
    | for a sign-off. A point is not itself a chain — a school defines one or more
    | chains against a point, told apart by their condition, and
    | `suggested_steps` is only the starting shape offered when it adds the first.
    |
    | `approver_type` is one of: role, user, reporting_manager, class_teacher,
    | principal. The last three are derived from the record at run time and carry
    | no `approver` value.
    |
    */
    'workflows' => [
        'admissions.confirmation.flow' => [
            'label' => 'Seat offer', 'description' => 'Offering a seat to an applicant.', 'subject' => 'Application',
            'suggested_steps' => [
                ['name' => 'Admissions review', 'approver_type' => 'role', 'approver' => 'Admissions head', 'sla_hours' => 24],
                ['name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 48],
            ],
        ],
        'students.transfer.flow' => [
            'label' => 'Transfer certificate', 'description' => 'Issuing a leaving certificate.', 'subject' => 'TC request',
            'suggested_steps' => [
                ['name' => 'Dues clearance', 'approver_type' => 'role', 'approver' => 'Accounts', 'sla_hours' => 24],
                ['name' => 'Library clearance', 'approver_type' => 'role', 'approver' => 'Librarian', 'sla_hours' => 24],
                ['name' => 'Principal sign-off', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 48],
            ],
        ],
        'attendance.leave.flow' => [
            'label' => 'Student leave', 'description' => 'A guardian request for student leave.', 'subject' => 'Leave application',
            'suggested_steps' => [
                ['name' => 'Class teacher', 'approver_type' => 'class_teacher', 'approver' => '', 'sla_hours' => 12],
            ],
        ],
        'examination.result.flow' => [
            'label' => 'Result publication', 'description' => 'Releasing a computed result to families.', 'subject' => 'Result set',
            'suggested_steps' => [
                ['name' => 'Exam controller', 'approver_type' => 'role', 'approver' => 'Exam controller', 'sla_hours' => 24],
                ['name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 24],
            ],
        ],
        'examination.marks.flow' => [
            'label' => 'Marks correction', 'description' => 'Changing a mark after entry has closed.', 'subject' => 'Marks change',
            'suggested_steps' => [
                ['name' => 'Subject head', 'approver_type' => 'role', 'approver' => 'Subject head', 'sla_hours' => 24],
                ['name' => 'Exam controller', 'approver_type' => 'role', 'approver' => 'Exam controller', 'sla_hours' => 24],
            ],
        ],
        'academics.lesson_plan.flow' => [
            'label' => 'Lesson plan review', 'description' => 'Approving a teacher lesson plan before it is taught.', 'subject' => 'Lesson plan',
            'suggested_steps' => [
                ['name' => 'Academic head', 'approver_type' => 'role', 'approver' => 'Academic head', 'sla_hours' => 48],
            ],
        ],
        'fees.concession.flow' => [
            'label' => 'Fee concession', 'description' => 'Granting a waiver, scholarship or discount.', 'subject' => 'Concession request',
            'suggested_steps' => [
                ['name' => 'Accounts verification', 'approver_type' => 'role', 'approver' => 'Accounts', 'sla_hours' => 24],
                ['name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 48],
                ['name' => 'Trustee approval', 'approver_type' => 'role', 'approver' => 'Trustee', 'sla_hours' => 72],
            ],
        ],
        'fees.refund.flow' => [
            'label' => 'Fee refund', 'description' => 'Returning money to a family.', 'subject' => 'Refund request',
            'suggested_steps' => [
                ['name' => 'Accounts verification', 'approver_type' => 'role', 'approver' => 'Accounts', 'sla_hours' => 24],
                ['name' => 'Finance head', 'approver_type' => 'role', 'approver' => 'Finance head', 'sla_hours' => 48],
            ],
        ],
        'fees.structure.flow' => [
            'label' => 'Fee structure change', 'description' => 'Publishing or amending a fee structure.', 'subject' => 'Fee structure',
            'suggested_steps' => [
                ['name' => 'Finance head', 'approver_type' => 'role', 'approver' => 'Finance head', 'sla_hours' => 48],
                ['name' => 'Trustee approval', 'approver_type' => 'role', 'approver' => 'Trustee', 'sla_hours' => 72],
            ],
        ],
        'hr.leave.flow' => [
            'label' => 'Staff leave', 'description' => 'A staff request for leave.', 'subject' => 'Leave application',
            'suggested_steps' => [
                ['name' => 'Reporting manager', 'approver_type' => 'reporting_manager', 'approver' => '', 'sla_hours' => 24],
                ['name' => 'HR', 'approver_type' => 'role', 'approver' => 'HR', 'sla_hours' => 24],
            ],
        ],
        'hr.payroll.flow' => [
            'label' => 'Payroll release', 'description' => 'Releasing the month salary run.', 'subject' => 'Payroll run',
            'suggested_steps' => [
                ['name' => 'HR verification', 'approver_type' => 'role', 'approver' => 'HR', 'sla_hours' => 24],
                ['name' => 'Finance head', 'approver_type' => 'role', 'approver' => 'Finance head', 'sla_hours' => 24],
            ],
        ],
        'hr.staff.flow' => [
            'label' => 'Staff onboarding', 'description' => 'Adding a staff member and granting access.', 'subject' => 'New staff record',
            'suggested_steps' => [
                ['name' => 'HR verification', 'approver_type' => 'role', 'approver' => 'HR', 'sla_hours' => 48],
                ['name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 48],
            ],
        ],
        'front_desk.gate_pass.flow' => [
            'label' => 'Student early release', 'description' => 'Letting a student leave during school hours.', 'subject' => 'Gate pass',
            'suggested_steps' => [
                ['name' => 'Class teacher', 'approver_type' => 'class_teacher', 'approver' => '', 'sla_hours' => 1],
                ['name' => 'Principal', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 1],
            ],
        ],
        'front_desk.complaint.flow' => [
            'label' => 'Complaint closure', 'description' => 'Closing a parent complaint.', 'subject' => 'Complaint',
            'suggested_steps' => [
                ['name' => 'Principal review', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 48],
            ],
        ],
        'hostel.gate_pass.flow' => [
            'label' => 'Hostel gate pass', 'description' => 'A resident leaving the hostel.', 'subject' => 'Gate pass',
            'suggested_steps' => [
                ['name' => 'Warden', 'approver_type' => 'role', 'approver' => 'Warden', 'sla_hours' => 4],
                ['name' => 'Guardian consent', 'approver_type' => 'role', 'approver' => 'Guardian', 'sla_hours' => 12],
            ],
        ],
        'hostel.allocation.flow' => [
            'label' => 'Room allocation', 'description' => 'Allotting or changing a bed.', 'subject' => 'Allocation request',
            'suggested_steps' => [
                ['name' => 'Warden', 'approver_type' => 'role', 'approver' => 'Warden', 'sla_hours' => 24],
            ],
        ],
        'transport.route.flow' => [
            'label' => 'Route change', 'description' => 'Altering a route, a stop or a pickup time.', 'subject' => 'Route change',
            'suggested_steps' => [
                ['name' => 'Transport in-charge', 'approver_type' => 'role', 'approver' => 'Transport in-charge', 'sla_hours' => 24],
                ['name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 48],
            ],
        ],
        'inventory.purchase.flow' => [
            'label' => 'Purchase order', 'description' => 'Raising a purchase order against an indent.', 'subject' => 'Purchase order',
            'suggested_steps' => [
                ['name' => 'Store verification', 'approver_type' => 'role', 'approver' => 'Store keeper', 'sla_hours' => 24],
                ['name' => 'Purchase head', 'approver_type' => 'role', 'approver' => 'Purchase head', 'sla_hours' => 48],
                ['name' => 'Finance head', 'approver_type' => 'role', 'approver' => 'Finance head', 'sla_hours' => 48],
            ],
        ],
        'inventory.issue.flow' => [
            'label' => 'Material issue', 'description' => 'Issuing stock to a person or department.', 'subject' => 'Issue request',
            'suggested_steps' => [
                ['name' => 'Store keeper', 'approver_type' => 'role', 'approver' => 'Store keeper', 'sla_hours' => 24],
            ],
        ],
        'communication.circular.flow' => [
            'label' => 'Circular release', 'description' => 'Releasing a circular to parents or staff.', 'subject' => 'Circular',
            'suggested_steps' => [
                ['name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 24],
            ],
        ],
        'communication.campaign.flow' => [
            'label' => 'Bulk send approval', 'description' => 'Approving a bulk SMS, email or WhatsApp send.', 'subject' => 'Campaign',
            'suggested_steps' => [
                ['name' => 'Communication head', 'approver_type' => 'role', 'approver' => 'Communication head', 'sla_hours' => 12],
                ['name' => 'Finance approval for cost', 'approver_type' => 'role', 'approver' => 'Finance head', 'sla_hours' => 24],
            ],
        ],
        'compliance.retention.flow' => [
            'label' => 'Record purge', 'description' => 'Permanently removing records past retention.', 'subject' => 'Purge batch',
            'suggested_steps' => [
                ['name' => 'Compliance officer', 'approver_type' => 'role', 'approver' => 'Compliance officer', 'sla_hours' => 72],
                ['name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 72],
            ],
        ],
        'lms.content.flow' => [
            'label' => 'Content publication', 'description' => 'Publishing learning content to students.', 'subject' => 'Content item',
            'suggested_steps' => [
                ['name' => 'Academic head', 'approver_type' => 'role', 'approver' => 'Academic head', 'sla_hours' => 48],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Approver types
    |--------------------------------------------------------------------------
    |
    | Named here so the frontend renders the same list the API validates against.
    | The derived three take no approver value — the engine resolves them from the
    | record when a chain runs.
    |
    */
    'approver_types' => [
        'role'              => ['label' => 'Role',              'needs_value' => true,  'description' => 'Anyone holding this role in the institute.'],
        'user'              => ['label' => 'Named person',      'needs_value' => true,  'description' => 'One specific user, by id.'],
        'reporting_manager' => ['label' => 'Reporting manager', 'needs_value' => false, 'description' => 'The requester’s reporting manager, resolved when the chain runs.'],
        'class_teacher'     => ['label' => 'Class teacher',     'needs_value' => false, 'description' => 'The class teacher of the student on the record.'],
        'principal'         => ['label' => 'Principal',         'needs_value' => false, 'description' => 'The principal of the institute.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalation actions
    |--------------------------------------------------------------------------
    |
    | What happens when a step sits unactioned past its SLA. `auto_approve` is
    | offered because some schools want it for low-value steps, and refused for
    | none — but it is the one an administrator should have to choose knowingly,
    | so the screen says what it means rather than only naming it.
    |
    */
    'escalation_actions' => [
        'none'         => ['label' => 'Do nothing',        'description' => 'The step waits indefinitely.'],
        'remind'       => ['label' => 'Remind the approver', 'description' => 'Sends the approver another notification and keeps waiting.'],
        'escalate'     => ['label' => 'Escalate',          'description' => 'Passes the step to the next step’s approver.'],
        'auto_approve' => ['label' => 'Approve automatically', 'description' => 'Approves without a person. Use only where a delay is worse than no review.'],
        'auto_reject'  => ['label' => 'Reject automatically',  'description' => 'Rejects and returns the record to the requester.'],
    ],
];
