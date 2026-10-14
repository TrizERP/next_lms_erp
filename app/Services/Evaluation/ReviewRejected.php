<?php

namespace App\Services\Evaluation;

use RuntimeException;

/**
 * A teacher's review that cannot be applied, decided while the sheet is locked.
 *
 * Thrown from inside the review transaction so that everything the request had
 * started is rolled back and nothing is half-written, then turned into the
 * module's ordinary 422 (or 404) response by the controller. The rule that
 * failed has always been checked on the sheet AS IT IS NOW -- under the row lock
 * -- and never on a copy read before the lock was taken, which is what makes
 * "approved, then edited by a second request" impossible rather than unlikely.
 */
class ReviewRejected extends RuntimeException
{
    /** @param  array<int,string>  $errors  Every problem found, for requests that report several at once. */
    public function __construct(string $message, public readonly int $status = 422, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
