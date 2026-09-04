<?php

namespace App\Services\Homework\Exceptions;

use RuntimeException;

/**
 * Thrown when neither text-layer extraction nor OCR could recover
 * readable content from an assignment/submission file.
 */
class DocumentExtractionException extends RuntimeException
{
}
