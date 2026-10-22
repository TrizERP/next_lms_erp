<?php

namespace App\Domain\AI\Configuration;

use RuntimeException;

/**
 * A module cannot be given a provider, and the reason is something an administrator fixes.
 *
 * WHY THIS IS NOT A PROVIDER ERROR
 *
 * `AiProviderException` means a vendor answered badly. This means nobody asked the vendor:
 * the module has no saved configuration, or its saved credential is missing, or none of
 * its providers can do what the module needs. Keeping them apart lets a caller say "ask
 * your administrator to configure Assessment AI" instead of "the AI is busy, try again",
 * and — more to the point — lets the fallback chain know that retrying is pointless.
 *
 * There is deliberately no fallback to an environment-file provider behind this. If the
 * saved configuration is missing, the module is unconfigured, and says so.
 */
final class AiConfigurationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $module = null,
        public readonly string $reason = 'not_configured',
    ) {
        parent::__construct($message);
    }

    /** No row, no pool key: nothing for this module to call. */
    public static function notConfigured(string $module, string $moduleLabel): self
    {
        return new self(sprintf(
            '%s has no AI provider configured. An administrator needs to add a provider, model and API key '
            . 'for it in AI & Intelligence → AI Providers.',
            $moduleLabel
        ), $module, 'not_configured');
    }

    /** A configuration exists but its credential is empty, retired or unusable. */
    public static function missingCredential(string $module, string $moduleLabel, string $providerLabel): self
    {
        return new self(sprintf(
            '%s is configured to use %s, but there is no usable API key for it. An administrator needs to '
            . 'add or replace the key in AI & Intelligence → AI Providers.',
            $moduleLabel,
            $providerLabel
        ), $module, 'missing_credential');
    }

    /** Providers exist, but none can do what this module needs. */
    public static function noCapableProvider(string $module, string $moduleLabel, string $needs): self
    {
        return new self(sprintf(
            '%s needs a provider that supports %s, and none of the configured providers does. An administrator '
            . 'needs to configure a capable provider in AI & Intelligence → AI Providers.',
            $moduleLabel,
            $needs
        ), $module, 'no_capable_provider');
    }
}
