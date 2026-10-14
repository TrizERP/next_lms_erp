<?php

namespace App\Services\Homework\Exceptions;

use RuntimeException;

/**
 * The AI provider cannot be used because of how the PLATFORM is set up -- no key, or a key the
 * provider rejects -- not because of the work being marked.
 *
 * It will fail the same way for every sheet until an administrator fixes the key, so a retry is
 * pointless and a "couldn't read this one" is the wrong thing to tell a teacher. A queued job
 * uses the type to fail the sheet at once with the real reason, instead of treating it like an
 * unreadable scan or waiting out a backoff.
 *
 * Still a RuntimeException, so every caller that already catches RuntimeException behaves as before.
 */
class ProviderConfigurationException extends RuntimeException
{
}
