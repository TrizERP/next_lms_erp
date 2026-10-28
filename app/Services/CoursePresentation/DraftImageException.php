<?php

namespace App\Services\CoursePresentation;

/** A draft picture change that was refused or failed; the message is meant for the teacher and the status is the HTTP status to answer with. */
class DraftImageException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
