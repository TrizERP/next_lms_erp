<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * HR-sensitive routes (recruitment candidates and offers, mobility, offboarding): admin and
 * HR / management profiles only. `staff.only` lets every teacher and librarian through, which
 * showed them colleagues' exit cases, promotions and job applicants.
 *
 * Profile names are free text per school, so the match is by keyword. Add schools' own wording
 * to HR_PROFILE_KEYWORDS (comma separated) instead of editing code.
 */
class RequireHrRole
{
    public function handle(Request $request, Closure $next)
    {
        $profile = strtolower((string) session()->get('user_profile_name'));
        $isStudent = (bool) session()->get('is_student') || in_array($profile, ['student', 'parent'], true);
        $keywords = array_filter(array_map('trim', explode(',', strtolower((string) env(
            'HR_PROFILE_KEYWORDS',
            'admin,hr,human,principal,director,manager,head,management,owner,chairman,trustee'
        )))));

        $allowed = false;
        if (!$isStudent && $profile !== '') {
            foreach ($keywords as $k) {
                if ($k !== '' && str_contains($profile, $k)) {
                    $allowed = true;
                    break;
                }
            }
        }

        if (!$allowed) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not authorized to access this resource',
            ], 403);
        }

        return $next($request);
    }
}
