<?php

namespace App\Services\Homework\Exceptions;

use RuntimeException;

/**
 * The AI key as a whole has hit a rate limit (HTTP 429 that is not one model's
 * daily allowance).
 *
 * Distinct from ModelQuotaExhaustedException on purpose: a per-model daily quota
 * will not clear in minutes, but a per-minute limit will. A queued job can wait
 * this out and try again; a caller with nothing to wait on can still treat it as
 * the plain RuntimeException it always was, because this extends it.
 */
class RateLimitedException extends RuntimeException
{
}
