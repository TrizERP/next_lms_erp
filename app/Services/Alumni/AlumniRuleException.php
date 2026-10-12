<?php

namespace App\Services\Alumni;

/**
 * A business rule said no. The message is written to be shown to the user as it is, and `status` is the HTTP code the API
 * should answer with (409 for "not in a state that allows this", 422 for "this input is not acceptable").
 */
class AlumniRuleException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 409, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
