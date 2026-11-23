<?php

namespace App\Services\PAL\LabSpec;

/**
 * Who may author, preview, generate and approve labs: teachers and admins, never students.
 *
 * The rule is one pure function over what the session knows, so it can be tested without booting the application.
 *   - A student session is never allowed, whatever else it says.
 *   - An admin is allowed: `is_admin` >= 1, or a profile that chains up to the admin root profile (parent_id 1).
 *     `is_admin` alone is not trusted, because many genuine institute admins have it empty (the same reason the PAL
 *     student-data check falls back to the profile's parent).
 *   - A teacher is allowed: a profile whose name contains "teacher" (profile names are free text per school:
 *     "Teacher", "Class Teacher", "Subject teacher").
 * Parents and every other profile are refused.
 */
class PalLabAuthor
{
    public static function allows(bool $isStudent, int $isAdmin, string $profileName, ?int $profileParentId): bool
    {
        if ($isStudent) {
            return false;
        }
        if ($isAdmin >= 1 || $profileParentId === 1) {
            return true;
        }

        return str_contains(strtolower(trim($profileName)), 'teacher');
    }
}
