<?php

namespace App\Services\Homework\Exceptions;

use RuntimeException;

/**
 * Thrown when the Gemini evaluation call fails or returns a payload
 * that cannot be parsed into the expected evaluation JSON shape.
 */
class EvaluationException extends RuntimeException
{
}
