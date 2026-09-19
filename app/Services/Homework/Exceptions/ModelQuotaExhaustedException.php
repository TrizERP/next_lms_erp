<?php

namespace App\Services\Homework\Exceptions;

use RuntimeException;

/**
 * One model's own quota is spent — but only that model's.
 *
 * Gemini meters the free tier per project *per model*
 * (`GenerateRequestsPerDayPerProjectPerModel-FreeTier`), so a 429 naming a
 * model is not the same refusal as a per-project or per-minute limit: a
 * different model on the same key still answers. Distinguished from a
 * plain rate limit so a caller can move rather than give up.
 */
class ModelQuotaExhaustedException extends RuntimeException
{
}
