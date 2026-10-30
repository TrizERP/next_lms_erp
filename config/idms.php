<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk for IDMS
    |--------------------------------------------------------------------------
    */
    'disk' => env('IDMS_DISK', 'digitalocean'),

    // Days a deleted document stays restorable before it is purged for good.
    'trash_retention_days' => (int) env('IDMS_TRASH_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | AI Classification Configuration
    |--------------------------------------------------------------------------
    */
    'ai_enabled' => env('IDMS_AI_ENABLED', true),
    'ai_provider' => env('IDMS_AI_PROVIDER', 'gemini'), // gemini | openai
    'ai_model' => env('IDMS_AI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')),
    // No provider keys here: document AI resolves its provider, model and key from AI Providers
    // (module `document_intelligence`).

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

    /*
    |--------------------------------------------------------------------------
    | Question Paper worker heartbeat
    |--------------------------------------------------------------------------
    | The question-papers worker records that it is alive in the cache (QuestionPaperWorkerHeartbeat), so a paper nobody
    | has picked up can say "the worker is not running". That is only trustworthy when the web app and the worker read
    | the SAME cache: file/redis/database on one host or a shared store, never `array`. `heartbeat_trusted` null means
    | "decide from the cache driver"; set QUESTION_PAPER_HEARTBEAT_TRUSTED=false if web and worker run on different
    | hosts with a per-host file cache, so the screen does not claim the worker is down when it is not.
    */
    'question_paper' => [
        'heartbeat_key' => env('QUESTION_PAPER_HEARTBEAT_KEY', 'question-papers:queue-worker'),
        'heartbeat_trusted' => env('QUESTION_PAPER_HEARTBEAT_TRUSTED'),

        /*
        | Start/Stop control of the worker from the Document Library (QuestionPaperWorkerManager). Backend-only, with a fixed
        | allowlisted command; nothing a client sends reaches a shell.
        |
        |  driver   auto        local environment => launcher; anything else => none (explicitly unsupported)
        |           launcher    local fixed-command watchdog (Windows PowerShell or POSIX sh); graceful stop uses a flag
        |           supervisor  supervisorctl start/stop <supervisor_program>:*  (Linux production)
        |           systemd     systemctl start/stop <systemd_unit>              (Linux production)
        |           none        no control; the panel says so and nothing is executed
        |  sudo     prefix the service command with `sudo -n`; needs a sudoers rule for EXACTLY those commands, never general sudo
        |  control_scope  rbac         platform.scheduler:update (school administrators who hold it)
        |                 super_admin  only a Super Admin (is_admin = 2); the default in production, because the worker is shared by every school
        */
        'worker' => [
            'driver' => env('QUESTION_PAPER_WORKER_DRIVER', 'auto'),
            'supervisor_program' => env('QUESTION_PAPER_SUPERVISOR_PROGRAM', 'question-papers-worker'),
            'systemd_unit' => env('QUESTION_PAPER_SYSTEMD_UNIT', 'question-papers-worker'),
            'sudo' => (bool) env('QUESTION_PAPER_WORKER_SUDO', false),
            'php_cli' => env('QUESTION_PAPER_PHP_CLI'),
            'control_scope' => env('QUESTION_PAPER_WORKER_CONTROL', env('APP_ENV') === 'production' ? 'super_admin' : 'rbac'),
            'start_wait' => 20,
            'stop_wait' => 10,
            // Where the panel keeps its state and the worker's stop flag. Default storage/framework; only tests change it.
            'state_dir' => env('QUESTION_PAPER_WORKER_STATE_DIR'),
            // Extra environment for the local launcher. Empty in real use; isolated tests point it at a scratch database.
            'env' => [],
        ],
    ],
];
