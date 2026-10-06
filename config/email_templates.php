<?php

/**
 * Registry of transactional email events that can be managed from the frontend
 * (Settings > Email Templates).
 *
 * Each event declares:
 *  - the placeholders an admin may use in the body/subject,
 *  - the legacy blade view it falls back to while no DB template exists yet,
 *    which is also the source for the "Import current layout" button.
 *
 * Adding a new manageable mail = add an entry here + call
 * EmailTemplateService::render() from the sending code.
 */
return [

    'placeholder_open'  => '<<',
    'placeholder_close' => '>>',

    'events' => [

        'admission_payment_confirmation' => [
            'label'           => 'Admission - Payment Confirmation',
            'module'          => 'admission',
            'default_subject' => 'ADMISSION PAYMENT CONFIRMATION',
            'status_codes'    => [],
            'placeholders'    => [
                'aca_year'    => 'Academic year, e.g. 2025-26',
                'enquiry_no'  => 'Admission enquiry number',
                'paid_status' => 'Payment status flag (Yes/No)',
                'student_name'=> 'Student full name',
            ],
            'legacy' => [
                'context' => ['page_type' => 'paid', 'paid_status' => 'Yes'],
                'views'   => [
                    ['standards' => [], 'view' => 'admission.registrationHills.acknowledgementmai'],
                ],
            ],
        ],

        'admission_confirmed' => [
            'label'           => 'Admission - Confirmed / Confirmed with Activity',
            'module'          => 'admission',
            'default_subject' => 'ADMISSION PROCEDURE',
            'status_codes'    => ['C', 'C/A'],
            // Lets one template serve both statuses: << session >> resolves to
            // the right word, so C and C/A share a single editable layout.
            'status_labels'   => [
                'session'   => ['C' => 'Morning', 'C/A' => 'Afternoon'],
                // The two sessions are run by different principals, so the
                // signature follows the session rather than being fixed text.
                'principal' => ['C' => 'Mr. Ajay Singh Chauhan', 'C/A' => 'Mrs. Rehana Patni'],
            ],
            'placeholders'    => [
                'aca_year'      => 'Academic year, e.g. 2025-26',
                'conf_date'     => 'Confirmation date',
                'conf'          => 'Confirmation status code',
                'session'       => 'Morning for C, Afternoon for C/A - use this to share one template across both',
                'principal'     => 'Principal who signs: Mr. Ajay Singh Chauhan (Morning) or Mrs. Rehana Patni (Afternoon)',
                'parent_time'   => 'Reporting time / time window',
                'admission_std' => 'Standard name',
                'medium'        => 'Standard medium',
                'student_name'  => 'Student full name',
                'enquiry_no'    => 'Admission enquiry number',
            ],
            'legacy' => [
                'context' => ['page_type' => 'confirm'],
                'views'   => [
                    ['standards' => [3300, 3306], 'view' => 'admission.registrationHills.provisionalAdmissionStd11'],
                    ['standards' => [3305], 'view' => 'admission.registrationHills.11scienceAdmission'],
                    ['standards' => [3291, 3308], 'view' => 'admission.registrationHills.sendConfirmEmail'],
                    ['standards' => [3292, 3293, 3294, 3295, 3296, 3297, 3298, 3309, 3310, 3311, 3312, 3313, 3314, 3315], 'view' => 'admission.registrationHills.confirmationmail'],
                    ['standards' => [3316, 3303], 'view' => 'admission.registrationHills.sendConfirmEmailStd9'],
                ],
            ],
        ],

        'parent_interaction' => [
            'label'           => 'Admission - Parent Interaction Outcome',
            'module'          => 'admission',
            'default_subject' => 'ADMISSION PROCEDURE',
            'status_codes'    => ['I', 'NO', 'W/L'],
            'placeholders'    => [
                'aca_year'      => 'Academic year, e.g. 2025-26',
                'parent_date'   => 'Parent interaction date',
                'parent_time'   => 'Parent interaction time',
                'pint'          => 'Parent interaction status code',
                'admission_std' => 'Standard name',
                'medium'        => 'Standard medium',
                'student_name'  => 'Student full name',
                'enquiry_no'    => 'Admission enquiry number',
            ],
            'legacy' => [
                'context' => ['page_type' => 'parent'],
                'views'   => [
                    // The old code had a per-standard branch for 3291/3308 that
                    // never matched (`$standard_id == [3291,3308]`), so every
                    // standard received this layout. Parity is kept on purpose -
                    // create a standard-specific template to change it.
                    ['standards' => [], 'view' => 'admission.registrationHills.sendEmailPrentInteraction'],
                ],
            ],
        ],

        'admission_activity_invite' => [
            'label'           => 'Admission - Activity / Entrance Test Invite',
            'module'          => 'admission',
            'default_subject' => 'ADMISSION PROCEDURE',
            'status_codes'    => [],
            'placeholders'    => [
                'aca_year'      => 'Academic year, e.g. 2025-26',
                'parent_date'   => 'Activity / entrance test date',
                'parent_time'   => 'Activity / entrance test time',
                'admission_std' => 'Standard name',
                'medium'        => 'Standard medium',
                'student_name'  => 'Student full name',
                'enquiry_no'    => 'Admission enquiry number',
            ],
            'legacy' => [
                'context' => ['page_type' => 'parent'],
                'views'   => [
                    ['standards' => [3291], 'view' => 'admission.registrationHills.sendConfirmEmail'],
                    ['standards' => [], 'view' => 'admission.registrationHills.admissionEnquiryStd2to9'],
                ],
            ],
        ],

        'admission_result' => [
            'label'           => 'Admission - Waitlist / Regret',
            'module'          => 'admission',
            'default_subject' => 'ADMISSION PROCEDURE',
            'status_codes'    => ['NO', 'W/L'],
            'placeholders'    => [
                'aca_year'      => 'Academic year, e.g. 2025-26',
                'conf'          => 'Confirmation status code',
                'conf_date'     => 'Confirmation date',
                'parent_date'   => 'Parent interaction date',
                'parent_time'   => 'Parent interaction time',
                'admission_std' => 'Standard name',
                'medium'        => 'Standard medium',
                'student_name'  => 'Student full name',
                'enquiry_no'    => 'Admission enquiry number',
            ],
            'legacy' => [
                'context' => ['page_type' => 'parent'],
                'views'   => [
                    ['standards' => [], 'view' => 'admission.registrationHills.sendConfirmEmail'],
                ],
            ],
        ],

        'transport_welcome' => [
            'label'           => 'Admission - Transport Welcome',
            'module'          => 'admission',
            'default_subject' => 'Welcome to Hills High School - Important Information',
            'status_codes'    => [],
            'placeholders'    => [
                'aca_year'     => 'Academic year, e.g. 2025-26',
                'student_name' => 'Student full name',
                'enquiry_no'   => 'Admission enquiry number',
                'mobile'       => 'Parent mobile number',
                'email'        => 'Parent email',
            ],
            'legacy' => [
                'context' => [],
                'views'   => [
                    ['standards' => [], 'view' => 'admission.registrationHills.transport_welcome'],
                ],
            ],
        ],

    ],
];
