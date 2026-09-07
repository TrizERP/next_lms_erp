<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Question generation — prompt pack and runtime controls
    |--------------------------------------------------------------------------
    |
    | This file no longer owns provider credentials. `api_key`, `base_url`, `model`,
    | `api_type`, `timeout_seconds` and `max_output_tokens` all derive from the single
    | provider block in config/ai.php, so a key rotation is one edit.
    |
    | What stays here is what actually belongs to question generation rather than to a
    | provider: the pinned prompt and envelope versions, the per-question-type
    | temperatures, and the batch sizes. Those are domain settings that happen to be
    | passed to a model — folding them into a "provider" config would have made the
    | provider block a dumping ground and left nowhere sensible for the next one.
    |
    | The AI brain does not read this file. It depends on
    | App\Domain\AI\Support\ModelClient and follows AI_PROVIDER.
    |
    */

    'api_key' => config('ai.provider.deepseek.api_key'),
    'base_url' => config('ai.provider.deepseek.base_url', 'https://api.deepseek.com'),
    'model' => config('ai.provider.deepseek.model', 'deepseek-v4-pro'),
    'api_type' => config('ai.provider.deepseek.api_type', 'DEEPSEEK_API_KEY'),
    'timeout_seconds' => config('ai.provider.deepseek.timeout', 600),
    'max_output_tokens' => config('ai.provider.deepseek.max_output_tokens', 0),

    // Pinned prompt / envelope versions (see question-generation prompt pack).
    // 2.1: CBSE 2025-26 typology (Assertion-Reason / Case-Based MCQ sub_types)
    //      + optional competency_ref provenance.
    'prompt_version' => 'qgen-sys-2.1',
    'answer_envelope_version' => 'ans-2.0',

    // Runtime controls.
    'temperature_mcq' => 0.4,
    'temperature_narrative' => 0.6,
    'batch_size_mcq' => (int) env('DEEPSEEK_BATCH_SIZE_MCQ', 10),
    'batch_size_narrative' => (int) env('DEEPSEEK_BATCH_SIZE_NARRATIVE', 3),

    // DeepSeek "pro/thinking" style flags. Left off for deepseek-chat; enable
    // for reasoner-class models that accept them.
    'thinking' => env('DEEPSEEK_THINKING', false),
    'reasoning_effort' => env('DEEPSEEK_REASONING_EFFORT', null),
];
