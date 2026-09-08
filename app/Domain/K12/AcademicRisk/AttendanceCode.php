<?php

namespace App\Domain\K12\AcademicRisk;

/** Attendance codes that can legitimately contribute to a rate. */
final class AttendanceCode
{
    public static function isPresent(mixed $value): bool
    {
        return strtoupper(trim((string) $value)) === 'P';
    }

    public static function isAbsent(mixed $value): bool
    {
        return strtoupper(trim((string) $value)) === 'A';
    }

    public static function isCounted(mixed $value): bool
    {
        return self::isPresent($value) || self::isAbsent($value);
    }
}
