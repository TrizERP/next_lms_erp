<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise Brain role bridge
    |--------------------------------------------------------------------------
    |
    | The LMS remains the source of users and profiles. These settings map the
    | profile/admin values already present in the LMS JWT to Brain roles.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Accepted JWT signing secrets
    |--------------------------------------------------------------------------
    |
    | The Brain verifies the LMS's own token; it issues none of its own. When the
    | LMS front end signs in against a DIFFERENT deployment from the one serving
    | /api/brain, the two must agree on the signing secret or every Brain request
    | comes back brain_invalid_token. List that deployment's JWT_SECRET here
    | (comma-separated in BRAIN_JWT_SECRETS) rather than weakening the check.
    |
    */
    'jwt_secrets' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BRAIN_JWT_SECRETS', ''))
    ))),

    'default_role' => 'viewer',

    'admin_values' => [1, 2],

    'profile_roles' => [
        // user_profile_id => brain role
    ],

    /*
    |--------------------------------------------------------------------------
    | LMS profile name -> Brain role
    |--------------------------------------------------------------------------
    |
    | `profile_roles` above keys on user_profile_id, which is per-institute in
    | this database: "Admin" is profile 1 for one institute and profile 3596 for
    | another, so an id map can only ever cover the institutes it was written
    | for. tbluserprofilemaster.name is the value the LMS actually administers
    | and shows, so the bridge keys on that instead, normalised to lower case.
    |
    | This is a ROLE map, not organization data — it says what an "Admin" may do
    | in the Brain, and never how many of them there are. A name that appears
    | here for no institute simply never matches.
    |
    | Anything unlisted falls to `default_role`, so a new profile can read the
    | Brain but cannot approve or execute until someone decides it should.
    |
    */
    'profile_name_roles' => [
        'super admin' => 'tenant_admin',
        'admin' => 'tenant_admin',
        'school admin' => 'tenant_admin',
        'college admin' => 'tenant_admin',
        'principal' => 'tenant_admin',

        // Governance without full administration.
        'vice principal' => 'manager',
        'assistant admin' => 'manager',
        'trustee' => 'manager',
        'head of department' => 'manager',

        // Read plus evidence curation, no approval.
        'accountant' => 'analyst',
        'accoutant' => 'analyst',
        'clerk' => 'analyst',
        'assistant clerk' => 'analyst',
        'counseller' => 'analyst',
        'counsellor' => 'analyst',
    ],

    /*
    |--------------------------------------------------------------------------
    | Intelligence rule thresholds
    |--------------------------------------------------------------------------
    |
    | Every rule in App\Brain\Intelligence\LmsSignalRules compares a PROPORTION
    | against one of these, so a rule means the same thing for an institute of
    | 120 staff and one of 12,000. They live here rather than in the rule bodies
    | so a deployment can tune sensitivity without a code change; the defaults in
    | the rules themselves are what apply when a key is absent.
    |
    | Raising a threshold makes a rule quieter, never more truthful — the counts
    | it reports are exact scans either way.
    |
    */
    'thresholds' => [
        // Share of departments/staff/students that must match before a finding
        // is worth raising at all.
        'department_without_head' => 0.20,
        'department_without_description' => 0.30,
        'department_without_staff' => 0.50,
        'staff_concentration' => 0.35,
        'person_without_department' => 0.005,
        'person_without_job_title' => 0.20,
        'person_without_reporting_manager' => 0.20,
        'person_never_logged_in' => 0.30,
        'person_incomplete_contact' => 0.02,
        'person_inactive_still_assigned' => 0.005,
        'student_missing_contact' => 0.001,
        'student_missing_identity' => 0.20,
        'student_missing_dob' => 0.001,
        'student_missing_enrollment_no' => 0.001,
        'attendance_coverage_gap' => 0.30,
        'student_absence_rate' => 0.08,
        'staff_attendance_open_punch' => 0.005,
        'result_coverage_gap' => 0.30,
        'homework_non_submission' => 0.10,
        'fee_collection_coverage' => 0.30,
        'complaint_unresolved' => 0.10,
        'capability_unassigned' => 0.50,

        // Trend rules compare a period against the one before it, or a class
        // against the school's own baseline. These are PERCENTAGE POINTS, not
        // proportions: a class 4 points under the school average is worth
        // naming, half a point is noise.
        'class_attendance_gap_points' => 4.0,
        'subject_homework_gap_points' => 15.0,

        // Absolute thresholds, where a proportion would be meaningless.
        'chronic_absence_marks' => 5,
        'pass_percentage' => 40.0,

        /*
        |----------------------------------------------------------------------
        | Fees intelligence (App\Brain\Intelligence\FeesSignalRules)
        |----------------------------------------------------------------------
        |
        | Every fee rule is evaluated against ONE academic year's demand and
        | receipts. The MINIMUMS below are not sensitivity dials — they are the
        | point beneath which a proportion stops being evidence. Three accounts
        | in arrears are always "100% concentrated in the top three"; a cycle
        | with one receipt always "declined 100%" against the one before it.
        | Rules that fire on those produce confident nonsense, so each rule
        | states the floor it needs and declines below it, and the screen shows
        | the decline as "insufficient evidence" rather than inventing a finding.
        |
        */

        // Collection rate (percentage points) under which the year's collection
        // performance is itself the finding.
        'fee_collection_rate_floor' => 70.0,
        // Share of outstanding held by the largest accounts before concentration
        // is worth acting on, and how many accounts "largest" means.
        'fee_outstanding_concentration' => 0.60,
        'fee_concentration_accounts' => 10,
        // A class or fee head must be this many percentage points below the
        // institute's own rate before it is named.
        'fee_class_gap_points' => 15.0,
        'fee_head_gap_points' => 15.0,
        // Overdue money as a share of total demand.
        'fee_overdue_share' => 0.25,
        // Share of fee accounts with no receipt at all — a process/adoption
        // finding rather than a collection one.
        'fee_receipt_coverage' => 0.50,
        // Cycle-over-cycle fall in collection, as a proportion.
        'fee_cycle_decline' => 0.25,
        // Share of collected value arriving through a single payment mode.
        'fee_payment_mode_concentration' => 0.85,

        // Evidence floors. Below these a proportion is arithmetic, not a finding.
        'fee_min_accounts' => 10,
        'fee_min_defaulters' => 5,
        'fee_min_class_accounts' => 5,
        'fee_min_receipts' => 10,
        'fee_min_cycle_amount' => 1000.0,

        // Ledger quality. Cancellation is normal; cancellation on this scale
        // relative to collection is a finding about how receipts are handled.
        'fee_cancellation_share' => 25.0,
        'fee_min_cancellations' => 5,
    ],
];
