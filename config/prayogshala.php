<?php

/**
 * Prayogshala generation policy. Every value can be overridden in .env.
 */
return [
    // Output budget and timeout for the provider call (the activity JSON is long).
    'max_output_tokens' => (int) env('PRAYOGSHALA_MAX_OUTPUT_TOKENS', 16000),
    'timeout_seconds' => (int) env('PRAYOGSHALA_TIMEOUT_SECONDS', 240),

    // Retry policy for failures that clear by themselves (rate limit, provider overload, timeout).
    // Quota, billing and key failures are never retried. The pause is base * 2^n plus up to 25%
    // jitter, never shorter than the provider's Retry-After, and a wait longer than
    // max_retry_wait_seconds is not held open (the failure carries the delay instead).
    'max_provider_attempts' => (int) env('PRAYOGSHALA_MAX_PROVIDER_ATTEMPTS', 3),
    'retry_base_seconds' => (int) env('PRAYOGSHALA_RETRY_BASE_SECONDS', 2),
    'max_retry_wait_seconds' => (int) env('PRAYOGSHALA_MAX_RETRY_WAIT_SECONDS', 60),
];
