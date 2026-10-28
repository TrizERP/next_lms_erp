<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * One place that knows every format a stored password can be in.
 *
 * The estate is mid-migration: staff passwords were stored as plain text and students' as
 * unsalted MD5, and the JSON login (ApiLoginController) re-saves them as bcrypt when a user signs
 * in. The browser login compared the plain column inside SQL, so it could not sign in a user who
 * had already been upgraded. This accepts all three formats and compares in constant time.
 */
class PasswordVerifier
{
    public static function matches(?string $stored, string $given, bool $legacyMd5): bool
    {
        $stored = (string) $stored;
        if ($stored === '' || $given === '') {
            return false;
        }

        // bcrypt / argon hashes made by Hash::make
        if (self::looksHashed($stored)) {
            try {
                return Hash::check($given, $stored);
            } catch (\Throwable) {
                return false;
            }
        }

        $legacy = $legacyMd5 ? md5($given) : $given;

        return hash_equals($stored, $legacy);
    }

    private static function looksHashed(string $stored): bool
    {
        return str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon');
    }
}
