<?php

namespace App\Services\QuestionPapers;

use RuntimeException;

/**
 * A failure that retrying cannot fix (corrupt file, no questions in it, wrong content). The job
 * is marked failed at once; `$manualRetry` says whether the owner could still fix it (for example
 * by configuring an AI provider) and ask for another run.
 */
class PermanentQuestionPaperException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $manualRetry = false)
    {
        parent::__construct($message);
    }
}
