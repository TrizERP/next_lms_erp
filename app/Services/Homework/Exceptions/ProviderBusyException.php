<?php

namespace App\Services\Homework\Exceptions;

use RuntimeException;

/**
 * The AI provider was busy — not broken, and not misconfigured.
 *
 * Thrown only after every retry has been spent on a 500/502/503/504/529, which
 * all mean "not now" rather than "not ever": the request was well formed and the
 * credential was accepted. It is a distinct type so a caller can tell a capacity
 * spike, where trying again later is the whole remedy, apart from an unreadable
 * file or a bad key, where trying again changes nothing.
 */
class ProviderBusyException extends RuntimeException
{
}
