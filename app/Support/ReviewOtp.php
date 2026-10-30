<?php

namespace App\Support;

/**
 * Fixed-OTP accounts for app-store review and demos.
 *
 * Phone numbers and a fixed OTP were hard-coded in the login controllers, so anyone
 * who knew one of those numbers could log in as its owner. They now come from the
 * environment and are off unless set:
 *
 *   OTP_REVIEW_NUMBERS=9XXXXXXXXX,9YYYYYYYYY
 *   OTP_REVIEW_CODE=<fixed code>
 */
class ReviewOtp
{
    public static function matches(?string $mobile): bool
    {
        $numbers = array_filter(array_map('trim', explode(',', (string) env('OTP_REVIEW_NUMBERS', ''))));

        return $mobile !== null && $mobile !== '' && self::code() !== null && in_array(trim($mobile), $numbers, true);
    }

    public static function code(): ?string
    {
        $code = (string) env('OTP_REVIEW_CODE', '');

        return $code === '' ? null : $code;
    }
}
