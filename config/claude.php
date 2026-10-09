<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claude (Anthropic) Content Generation
    |--------------------------------------------------------------------------
    |
    | Powers the chapter content library writer (content_master), the way
    | config/deepseek.php powers the question-generation writer
    | (lms_question_master). The API key resolves, in priority order, from:
    |   1. the `ai_api_keys` table (api_type = `api_type` below, status = 1)
    |   2. the ANTHROPIC_API_KEY environment variable
    | Never hardcode the key in source.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    // api_type used to look the key up in the ai_api_keys table.
    'api_type' => env('ANTHROPIC_API_TYPE', 'ANTHROPIC_API_KEY'),

    // Server-side only. The generation endpoint does not accept a
    // caller-supplied model, for the same reason question generation does not:
    // it would let a client pick an arbitrarily expensive model on the
    // tenant's account.
    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),

    /*
    | Chapters where Claude replaces Gamma/Gemini. Comma-separated
    | chapter_master ids, or `*` for every chapter. Empty disables Claude
    | entirely and every chapter keeps the Gamma/Gemini behaviour.
    |
    | Default 8592 = Standard 9 > Science > Chapter 1.
    */
    'chapter_ids' => env('CLAUDE_CONTENT_CHAPTER_IDS', ''),

    /*
    | content_master.source marker for rows this service writes. Both the read
    | API (ApiLmsCourseController::GENERATED_CONTENT_SOURCES) and the frontend
    | badge (chapters/page.tsx GENERATED_CONTENT_SOURCES) match against this
    | string, so changing it means changing all three.
    */
    'source_label' => env('CLAUDE_CONTENT_SOURCE_LABEL', 'Claude AI'),

    /*
    | The student app's public address, used for the links a study deck's PDF prints beside each interactive
    | activity (<this>/student/study-deck/<chapter>?content=<id>&slide=<n>). Falls back to the mobile page builder's
    | frontend address. Empty means the PDF carries no links.
    */
    'study_deck_frontend_url' => env('STUDY_DECK_FRONTEND_URL', env('MOBILE_PAGE_BUILDER_FRONTEND_URL', 'https://k12.scholarclone.com')),

    /*
    | Runtime controls.
    |
    | NOTE: temperature / top_p / top_k are deliberately absent. Claude Opus 5
    | rejects all three with HTTP 400, unlike the DeepSeek call in
    | QuestionGenerationService. Do not add them back.
    */
    'effort' => env('CLAUDE_EFFORT', 'high'),

    // A 20-60 slide deck comfortably exceeds the 16k default, and the request
    // is streamed so a high ceiling costs nothing when the model stops early.
    'max_output_tokens' => (int) env('CLAUDE_MAX_OUTPUT_TOKENS', 32000),

    // Matches the drawer's own 10-minute client abort.
    'timeout_seconds' => (int) env('CLAUDE_TIMEOUT_SECONDS', 600),

    /*
    | Question generation (H5P-driven formats).
    |
    | App\Services\QuestionGeneration\H5p\ClaudeQuestionClient reads this block.
    | The key itself is NOT duplicated here: it resolves exactly as above
    | (ai_api_keys row for `api_type`, then ANTHROPIC_API_KEY), school-scoped.
    | The model defaults to the one chosen for content generation; set
    | ANTHROPIC_QUESTION_MODEL to give question generation a cheaper one.
    | temperature / top_p / top_k stay absent for the reason given above.
    */
    'question_generation' => [
        'model' => env('ANTHROPIC_QUESTION_MODEL', env('ANTHROPIC_MODEL', 'claude-opus-5')),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
        // A handful of short questions as JSON; far below the content writer's ceiling.
        'max_output_tokens' => (int) env('CLAUDE_QUESTION_MAX_OUTPUT_TOKENS', 8000),
        'timeout_seconds' => (int) env('CLAUDE_QUESTION_TIMEOUT_SECONDS', 180),
    ],

    /*
    | Which completer the study-deck pipeline uses: `api` (default, the
    | Anthropic API via ContentGenerationService) or `cli` - DEV ONLY, shells out
    | to the `claude` CLI with every tool disabled, for tenants with no API key.
    */
    'executor' => env('CLAUDE_EXECUTOR', 'api'),
    'cli' => [
        'binary' => env('CLAUDE_CLI_BINARY', 'claude'),
        'model' => env('CLAUDE_CLI_MODEL', ''),
        'timeout_seconds' => (int) env('CLAUDE_CLI_TIMEOUT_SECONDS', 900),
    ],

    // Per-user rate limit on the billable generation path, applied by
    // App\Http\Middleware\ThrottleContentGeneration. Generating every content
    // type is 5 sequential Opus 5 calls, so this is a spend control.
    'rate_limit_attempts' => (int) env('CLAUDE_RATE_LIMIT_ATTEMPTS', 12),
    'rate_limit_decay_minutes' => (int) env('CLAUDE_RATE_LIMIT_DECAY_MINUTES', 1),
];
