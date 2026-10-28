<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * Encryption at rest for provider API keys held in `ai_api_keys.api_key`.
 *
 * A database read or backup used to expose every school's AI provider key. Keys are now stored as
 * `enc:v1:<ciphertext>` (Laravel's encrypter, so they depend on APP_KEY). Reading is backward
 * compatible: a value without the prefix is a key saved before this existed and is returned
 * unchanged, so nothing stops working before the backfill (`php artisan ai:encrypt-keys`) is run.
 */
class KeyVault
{
    public const PREFIX = 'enc:v1:';

    public static function seal(?string $key): ?string
    {
        if ($key === null || $key === '' || $key === '-' || self::isSealed($key)) {
            return $key;
        }

        return self::PREFIX . Crypt::encryptString($key);
    }

    public static function reveal(?string $stored): ?string
    {
        if ($stored === null || ! self::isSealed($stored)) {
            return $stored;
        }

        try {
            return Crypt::decryptString(substr($stored, strlen(self::PREFIX)));
        } catch (\Throwable) {
            // Wrong APP_KEY or damaged value: behave like "no key" instead of sending ciphertext to a provider.
            return '';
        }
    }

    public static function isSealed(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }
}
