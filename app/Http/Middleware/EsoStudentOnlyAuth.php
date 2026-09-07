<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adaptive Learning Engine — student-only execution gate.
 *
 * `pal.auth` (PalApiAuth) already enforces per-learner ownership for every
 * PAL V4 route with a `{learnerId}`, including these ESO ones — a student
 * can only ever act as themselves, and staff/admin are scoped to their own
 * institute/class the same way they are for every other PAL V4 feature
 * (Pedagogy Engine, Misconceptions, etc.), which is legitimate read-only
 * reporting access this middleware must not disturb.
 *
 * This middleware exists for a narrower, additional rule that is specific
 * to STARTING/ADVANCING an Adaptive Learning session (diagnostic, practice,
 * attempt, retrieval, Pal rendering — never chapter-concepts or
 * decision-log, which stay teacher/parent-readable reporting surfaces): a
 * teacher, staff member, or admin — regardless of how broad their
 * institute/class scope would otherwise be under PalApiAuth — must never be
 * able to act as the learner in one of these routes, even for a student
 * genuinely within their own scope. Only the enrolled student's own
 * authenticated session may drive their own Adaptive Learning session.
 *
 * Runs after `pal.auth`, which populates the `pal_auth` request attribute
 * this reads — never trusts anything the client supplies directly.
 */
class EsoStudentOnlyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->attributes->get('pal_auth');

        if (! is_array($auth) || ($auth['role'] ?? null) !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Adaptive Learning sessions can only be started by the enrolled student\'s own account.',
            ], 403);
        }

        // Defense in depth: PalApiAuth::authorizeLearner() already rejects a
        // student acting on any learnerId but their own before this runs, so
        // this can only ever be true here — but this route group is exactly
        // the security boundary this middleware exists to guarantee, so the
        // self-match is re-asserted explicitly rather than assumed.
        $routeLearnerId = $request->route('learnerId');
        if ($routeLearnerId !== null && (int) $routeLearnerId !== (int) $auth['user_id']) {
            return response()->json([
                'success' => false,
                'message' => 'You can only access your own Adaptive Learning session.',
            ], 403);
        }

        $bodyLearnerId = $request->input('learner_id');
        if ($bodyLearnerId !== null && $bodyLearnerId !== '' && (int) $bodyLearnerId !== (int) $auth['user_id']) {
            return response()->json([
                'success' => false,
                'message' => 'You can only access your own Adaptive Learning session.',
            ], 403);
        }

        return $next($request);
    }
}
