<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk for IDMS
    |--------------------------------------------------------------------------
    */
    'disk' => env('IDMS_DISK', 'digitalocean'),

    /*
    |--------------------------------------------------------------------------
    | AI Classification Configuration
    |--------------------------------------------------------------------------
    */
    'ai_enabled' => env('IDMS_AI_ENABLED', true),
    'ai_provider' => env('IDMS_AI_PROVIDER', 'gemini'), // gemini | openai
    'ai_model' => env('IDMS_AI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')),
    'gemini_api_key' => env('GEMINI_API_KEY'),
    'openai_api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Confidence and Matching Thresholds
    |--------------------------------------------------------------------------
    */
    'confidence_threshold' => (float) env('IDMS_CONFIDENCE_THRESHOLD', 0.65),
    'duplicate_similarity_threshold' => (float) env('IDMS_DUPLICATE_THRESHOLD', 0.90),
    'version_similarity_threshold' => (float) env('IDMS_VERSION_THRESHOLD', 0.75),

    /*
    |--------------------------------------------------------------------------
    | File Limits and Types
    |--------------------------------------------------------------------------
    */
    'max_upload_size_kb' => (int) env('IDMS_MAX_FILE_SIZE_KB', 51200), // 50MB
    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/tiff',
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Languages
    |--------------------------------------------------------------------------
    */
    'ocr_languages' => ['eng', 'guj', 'hin'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Document Types for Classification
    |--------------------------------------------------------------------------
    */
    'allowed_document_types' => [
        'Contract',
        'Agreement',
        'Policy',
        'Circular',
        'Notice',
        'Minutes of Meeting',
        'Proposal',
        'Report',
        'Invoice',
        'Purchase Order',
        'Academic Syllabus',
        'Question Paper',
        'Certificate',
        'Identity Document',
        'General Correspondence',
    ],
];
