<?php

namespace App\Services\Evaluation;

use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Whether an AI failure is worth waiting out.
 *
 * A busy provider, a per-minute rate limit or a network timeout clears in
 * seconds to minutes, so a queued job should retry later rather than give up on
 * a student's sheet. An unreadable file, a bad key or an exhausted DAILY model
 * quota will not clear by waiting, and retrying those only delays the teacher
 * finding out.
 */
final class TransientAiFailure
{
    public static function is(Throwable $exception): bool
    {
        while ($exception !== null) {
            if ($exception instanceof ProviderBusyException
                || $exception instanceof RateLimitedException
                || $exception instanceof ConnectionException) {
                return true;
            }

            $exception = $exception->getPrevious();
        }

        return false;
    }
}
