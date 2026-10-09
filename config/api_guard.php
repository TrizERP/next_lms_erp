<?php

/*
|--------------------------------------------------------------------------
| API guard (V1 security hardening)
|--------------------------------------------------------------------------
|
| Routes in routes/api.php that matched `protect` below require a valid
| bearer JWT (the same one ApiLoginController issues), and the school named
| in the request must be the token's school - see App\Http\Middleware\RequireApiJwt.
|
| Patterns are matched with fnmatch() against the route URI *without* the
| leading "api/" and with placeholders left as written in the route file
| (e.g. "lms-homework/show/{id}"). A pattern may be prefixed with an HTTP
| method ("POST online_admission_confirm").
|
| `public` is checked first and wins. Keep it short and justify each entry.
|
| Routes that authenticate themselves in the controller, or carry their own
| middleware (api.session, lms.auth, pal.auth, ...), are deliberately not
| listed: this file closes the routes that had NO authentication at all.
*/

return [
    // Set API_GUARD_ENFORCE=false only as a short-lived emergency rollback.
    'enforce' => env('API_GUARD_ENFORCE', true),

    'public' => [
        // External callers with no JWT yet / no way to hold one.
        'POST online_admission_confirm',          // public online admission form submission
        'g2g-lms/certifications-records/certificates/verify/*', // public certificate verification
        'mobile/web-handoff/claims',              // redeemed with a single-use ticket instead of a JWT
        // WhatsApp gateway webhooks - called by the provider, not a user.
        // TODO(V1.1): verify a shared secret on these (see docs/V1_LAUNCH.md).
        'incoming-message',
        'update-message',
        'crm-whatsapp',
        'crm-whatsapp-update',
        'whats-send-app',
        'whats-comming-app',
    ],

    // Reachable without login ONLY when the request carries type=webForm, for the public
    // standalone forms (resources/views/front_desk/dicipline/standalone.blade.php).
    'webform' => [
        'get-standard-list',
        'get-division-list',
    ],

    // Of the protected URLs, these are teacher/admin actions: a student or parent (token
    // `is_student`, or profile named Student/Parent - the same rule as `staff.only`) gets a
    // 403. Student-facing reads (homework list, courses, dropdowns, menu) are deliberately
    // not here, so they keep working for any logged-in user.
    'staff' => [
        'admission_enquiry', 'admission_enquiry/*',
        'admission_registration', 'admission_registration/*',
        'admission_student', 'ajax_getDivision',
        'admission_without_confirmation_report_v2', 'admission_without_confirmation_report_v2/*',
        'online_admission_confirm', 'online_admission_confirm/*',
        'admissions-dashboard/*', 'students-dashboard/*', 'hostel-dashboard/*',
        'library-dashboard/*', 'transportation-dashboard/*',
        'ai-sop', 'ai-sop/*',
        'assessment-blueprints', 'assessment-blueprints/*',
        'KPI-HRITDashboard', 'attendance-weekly', 'employee-attendance-monthly-report',
        'compliance/*',
        'department-employee-list', 'department-employee-lists', 'departments', 'departments/*',
        'sub-department-list', 'jobroles-by-department',
        'exam-evaluation/*', 'get_marks_dd',
        'intelligence/*', 'lesson-intelligence/*',
        'interactions', 'interactions/*',
        'inventory/reports/*',
        'lms-homework/store', 'lms-homework/update/*', 'lms-homework/delete/*',
        'lms-homework/bulk-delete', 'lms-homework/students',
        'lms-assignment/bulk-delete',
        'lms-question-bank/create', 'lms-question-bank/update',
        'lms-question-bank/delete', 'lms-question-bank/review',
        'POST question-paper', 'PUT question-paper/*', 'PATCH question-paper/*', 'DELETE question-paper/*',
        'POST question-paper-templates', 'POST question-paper-templates/*',
        'PUT question-paper-templates/*', 'PATCH question-paper-templates/*', 'DELETE question-paper-templates/*',
        'lms/concept-intelligence/tab-labels/update', 'lms/concept-intelligence/tab-labels/reset',
        'lms/gamma-content-master',
        'semantic-intelligence', 'semantic-intelligence/*',
        // Templates merge student data and this controller reads the school from the request.
        'document-templates', 'document-templates/*',
        'lms-chapters/store', 'lms-store-subject',
    ],

    'protect' => [
        'academic-terms',
        'admission_enquiry', 'admission_enquiry/*',
        'admission_registration', 'admission_registration/*',
        'admission_student',
        'admission_without_confirmation_report_v2', 'admission_without_confirmation_report_v2/*',
        'admissions-dashboard/*', 'students-dashboard/*', 'hostel-dashboard/*',
        'library-dashboard/*', 'transportation-dashboard/*',
        'ajax_getDivision',
        'ai-platforms', 'ai-sop', 'ai-sop/*',
        'assessment-blueprints', 'assessment-blueprints/*',
        'KPI-HRITDashboard', 'attendance-weekly', 'employee-attendance-monthly-report',
        'compliance/*',
        'department-employee-list', 'department-employee-lists', 'departments', 'departments/*',
        'sub-department-list', 'jobroles-by-department',
        'document-templates', 'document-templates/*',
        'lms-chapters', 'lms-chapters/*', 'lms-store-subject',
        'exam-evaluation/*',
        'get-*',
        'get_marks_dd',
        'intelligence/*',
        'interactions', 'interactions/*',
        'inventory/reports/*',
        'lesson-intelligence/*',
        'lms-assignment/*',
        'lms-chapter-concepts', 'lms-chapter-content', 'lms-content-mapping-values',
        'lms-courses', 'lms-courses/*',
        'lms-homework/*',
        'lms-question-bank', 'lms-question-bank/*', 'lms-questions',
        'lms-study-deck/image-urls',
        'lms/concept-intelligence/*',
        'lms/gamma-content-master',
        'master-menu-rights', 'menu-rights',
        'online_admission_confirm', 'online_admission_confirm/*',
        'pal/pedagogy-engine', 'pal/pedagogy-engine/*',
        'question-bank/*', 'question-mapping-levels',
        'question-paper', 'question-paper/*', 'question-paper-templates', 'question-paper-templates/*',
        'semantic-intelligence', 'semantic-intelligence/*',
        'student-assessment', 'student-results/*',
    ],
];
