<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DeepSeek LLM Configuration
    |--------------------------------------------------------------------------
    |
    | Powers the question-generation API (lms_question_master writer).
    | The API key resolves (in priority order) from:
    |   1. the `ai_api_keys` table (api_type = DEEPSEEK_API_TYPE)
    |   2. the DEEPSEEK_API_KEY environment variable
    | Never hardcode the key in source.
    |
    */

    'api_key' => env('DEEPSEEK_API_KEY'),

    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),

    // Public DeepSeek model. Override per-request via `model`.
    'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-pro'),

    // api_type used to look the key up in the ai_api_keys table.
    'api_type' => env('DEEPSEEK_API_TYPE', 'DEEPSEEK_API_KEY'),

    // Pinned prompt / envelope versions (see question-generation prompt pack).
    'prompt_version' => 'qgen-sys-2.0',
    'answer_envelope_version' => 'ans-2.0',

    // Runtime controls.
    'temperature_mcq' => 0.4,
    'temperature_narrative' => 0.6,
    'timeout_seconds' => (int) env('DEEPSEEK_TIMEOUT_SECONDS', 600),
    // 0 means do not send max_tokens; the provider/model will use its own ceiling.
    'max_output_tokens' => (int) env('DEEPSEEK_MAX_OUTPUT_TOKENS', 0),
    'batch_size_mcq' => (int) env('DEEPSEEK_BATCH_SIZE_MCQ', 10),
    'batch_size_narrative' => (int) env('DEEPSEEK_BATCH_SIZE_NARRATIVE', 3),

    // DeepSeek "pro/thinking" style flags. Left off for deepseek-chat; enable
    // for reasoner-class models that accept them.
    'thinking' => env('DEEPSEEK_THINKING', false),
    'reasoning_effort' => env('DEEPSEEK_REASONING_EFFORT', null),
];
