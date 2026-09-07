<?php

declare(strict_types=1);

/**
 * G2G LMS (Package 1) — ported as-is from hp_erp's `config/lms.php`.
 * Consumed by `App\Http\Controllers\G2gLms\LearningCatalogController::filters()`
 * (languages, certificate_templates) and `MyLearningController`'s certificate
 * endpoints (certificate_warning_days, certificate_templates' `view`).
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Certificate expiry warning window
    |--------------------------------------------------------------------------
    |
    | How many days before its expiry date a certificate is reported as
    | "expiring" rather than "active". Configurable rather than a constant
    | because compliance renewal windows vary by organisation.
    */

    'certificate_warning_days' => (int) env('LMS_CERT_EXPIRY_WARNING_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Course languages
    |--------------------------------------------------------------------------
    |
    | Offered in the Course Builder and stored on lms_course_settings.language.
    | Served through the Learning Catalog's filters() endpoint.
    */

    'languages' => [
        'English (US)',
        'English (UK)',
        'Hindi',
        'Gujarati',
        'Marathi',
        'Spanish (ES)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate templates
    |--------------------------------------------------------------------------
    |
    | `view` is the blade the certificate download endpoint renders for that
    | choice. The controller resolves through this map and falls back to the
    | standard view for an unknown value or a missing view.
    */

    'certificate_templates' => [
        [
            'value' => 'standard',
            'label' => 'Standard Corporate Template',
            'view'  => 'lms.certificate',
        ],
        [
            'value' => 'compliance',
            'label' => 'Compliance Template',
            'view'  => 'lms.certificate_compliance',
        ],
    ],
];
