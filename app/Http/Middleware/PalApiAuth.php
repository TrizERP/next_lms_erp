<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\Exceptions\NoTokenException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PAL V4 API authentication + tenant/ownership scoping.
 *
 * Central authentication: every `api/pal/*` request must carry a valid JWT
 * (the same GenTux token issued by ApiLoginController). The decoded payload
 * (id / sub_institute_id / is_admin / is_student / client_id / user_profile_id)
 * is normalised into a `pal_auth` request attribute for the controller.
 *
 * Tenant / ownership scoping (the PAL V4 tables have NO sub_institute_id column
 * of their own -- learner_id references the user id directly), so the boundary
 * is enforced here by resolving the target learner back to its institute:
 *   - students may only ever touch their OWN learner record;
 *   - staff / institute admins are scoped to their own institute(s);
 *   - client-level admins (institute 0 + client) are scoped to their client;
 *   - the multi-client super admin (is_admin === 2) is unrestricted.
 */
class PalApiAuth
{
    use GetsJwtToken;

    public function handle(Request $request, Closure $next)
    {
        // 1. Central authentication -- require a valid JWT.
        try {
            $jwt = $this->jwtToken($request);
        } catch (NoTokenException $e) {
            return $this->deny('Authentication token is required.', 401);
        }

        try {
            if (! $jwt->validate()) {
                return $this->deny('Invalid or expired authentication token.', 401);
            }
            $payload = $jwt->payload();
        } catch (\Throwable $e) {
            return $this->deny('Invalid authentication token.', 401);
        }

        $userId    = (int) ($payload['id'] ?? 0);
        $isAdmin   = (int) ($payload['is_admin'] ?? 0);
        $isStudent = ! empty($payload['is_student']);

        if ($userId <= 0) {
            return $this->deny('Authentication token is malformed.', 401);
        }

        // `is_admin` is unreliable -- many genuine institute admins have it
        // null in tbluser (same reason ApiSessionHydrator falls back to the
        // profile name rather than trusting is_admin alone). The system-wide
        // convention is that admin-tier profiles (Admin / School admin /
        // Assistant admin / Principal, ...) all chain up to profile id 1 via
        // parent_id, consistently across institutes. Without this fallback,
        // such admins get misclassified as plain "staff" and wrongly scoped
        // down to only their own assigned classes.
        $isAdminProfile = $isAdmin >= 1;
        if (! $isAdminProfile && ! $isStudent && ! empty($payload['user_profile_id'])) {
            $profileParentId = DB::table('tbluserprofilemaster')
                ->where('id', $payload['user_profile_id'])
                ->value('parent_id');
            $isAdminProfile = (int) $profileParentId === 1;
        }

        $auth = [
            'user_id'          => $userId,
            'sub_institute_id' => $payload['sub_institute_id'] ?? '',
            'is_admin'         => $isAdmin,
            'is_student'       => $isStudent,
            'user_profile_id'  => $payload['user_profile_id'] ?? null,
            'client_id'        => $payload['client_id'] ?? null,
            'role'             => $isStudent ? 'student' : ($isAdminProfile ? 'admin' : 'staff'),
        ];
        $request->attributes->set('pal_auth', $auth);

        // 2. Tenant + ownership scoping -- enforce whenever the request targets a learner.
        $targetLearnerId = $this->resolveTargetLearner($request);
        if ($targetLearnerId !== null) {
            $decision = $this->authorizeLearner($auth, $targetLearnerId);
            if ($decision !== true) {
                return $this->deny($decision, 403);
            }
        }

        return $next($request);
    }

    /**
     * Find the learner the request is acting on, from (in order) the route
     * `{learnerId}` param, a `learner_id` body/query field, or -- for
     * session-only routes -- the owner of the referenced learning session.
     */
    private function resolveTargetLearner(Request $request): ?int
    {
        $routeLearner = $request->route('learnerId');
        if ($routeLearner !== null && $routeLearner !== '') {
            return (int) $routeLearner;
        }

        $bodyLearner = $request->input('learner_id');
        if ($bodyLearner !== null && $bodyLearner !== '') {
            return (int) $bodyLearner;
        }

        $sessionId = $request->route('sessionId');
        if ($sessionId !== null && $sessionId !== '') {
            $owner = DB::table('pal_learning_sessions')->where('id', (int) $sessionId)->value('learner_id');
            return $owner !== null ? (int) $owner : null;
        }

        return null;
    }

    /**
     * @return true|string  true when allowed, otherwise a denial message.
     */
    private function authorizeLearner(array $auth, int $learnerId)
    {
        // Multi-client super admin -- unrestricted.
        if ((int) $auth['is_admin'] === 2) {
            return true;
        }

        // Students may only ever access their own PAL record.
        if ($auth['role'] === 'student') {
            return $auth['user_id'] === $learnerId
                ? true
                : 'You can only access your own PAL data.';
        }

        // Staff / institute admins are scoped to their own institute(s).
        $targetSub = $this->resolveLearnerTenant($learnerId);
        if ($targetSub === null) {
            // Unknown learner id -- permit only self-lookups.
            return $auth['user_id'] === $learnerId
                ? true
                : 'This learner is not part of your institute.';
        }

        // Client-level admin (institute id 0 + client id): scope by client.
        if ((int) $auth['is_admin'] === 1 && (int) $auth['sub_institute_id'] === 0 && ! empty($auth['client_id'])) {
            $targetClient = DB::table('school_setup')->where('id', $targetSub)->value('client_id');
            return (int) $targetClient === (int) $auth['client_id']
                ? true
                : 'This learner belongs to a different client.';
        }

        // Normal staff / institute admin: institute id must match (supports CSV membership).
        $callerInstitutes = array_filter(
            array_map('trim', explode(',', (string) $auth['sub_institute_id'])),
            'strlen'
        );
        if (! in_array((string) $targetSub, $callerInstitutes, true)) {
            return 'This learner belongs to a different institute.';
        }

        // Plain teachers (not institute admins) are further scoped to their
        // assigned classes -- same restriction the rest of the LMS applies via
        // class_teacher (homeroom) / timetable (subject teacher), enforced ad
        // hoc per-controller rather than by a shared guard (see
        // Helper::SearchStudent, studentAttendanceController). PAL had no such
        // restriction at all: any staff member could reach any student in the
        // institute. Institute-level admins keep full institute access.
        if ($auth['role'] === 'staff' && ! $this->isStudentInTeacherScope((int) $auth['user_id'], $targetSub, $learnerId)) {
            return 'This learner is not in one of your assigned classes.';
        }

        return true;
    }

    /**
     * Whether $studentId falls in $teacherId's assigned classes, as either the
     * homeroom class_teacher or a subject teacher per the timetable -- the
     * same two sources Helper::SearchStudent()/lmsActivityStreamController
     * already treat as a teacher's scope elsewhere in the LMS. Matches on the
     * SAME syear on both sides (assignment and enrollment) rather than
     * resolving "the current year" independently, since there is no
     * authoritative current-year flag on academic_year; this both avoids
     * guessing and fails closed (a student with no matching-year enrollment
     * simply won't match).
     */
    private function isStudentInTeacherScope(int $teacherId, int $subInstituteId, int $studentId): bool
    {
        $classTeacherMatch = DB::table('class_teacher as ct')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.standard_id', '=', 'ct.standard_id')
                    ->on('se.section_id', '=', 'ct.division_id')
                    ->on('se.sub_institute_id', '=', 'ct.sub_institute_id')
                    ->on('se.syear', '=', 'ct.syear');
            })
            ->where('ct.teacher_id', $teacherId)
            ->where('ct.sub_institute_id', $subInstituteId)
            ->where('se.student_id', $studentId)
            ->exists();

        if ($classTeacherMatch) {
            return true;
        }

        return DB::table('timetable as t')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.standard_id', '=', 't.standard_id')
                    ->on('se.section_id', '=', 't.division_id')
                    ->on('se.sub_institute_id', '=', 't.sub_institute_id')
                    ->on('se.syear', '=', 't.syear');
            })
            ->where('t.teacher_id', $teacherId)
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('se.student_id', $studentId)
            ->exists();
    }

    /**
     * Resolve a learner id to its institute (sub_institute_id) via the student
     * or staff tables. Returns null when the learner cannot be found.
     */
    private function resolveLearnerTenant(int $learnerId): ?int
    {
        $sub = DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id');
        if ($sub !== null && $sub !== '') {
            return (int) $sub;
        }

        $sub = DB::table('tbluser')->where('id', $learnerId)->value('sub_institute_id');
        if ($sub !== null && $sub !== '') {
            return (int) $sub;
        }

        return null;
    }

    private function deny(string $message, int $status)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
