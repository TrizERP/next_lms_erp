<?php

namespace App\Http\Controllers\api\alumni\Concerns;

use App\Services\Alumni\DuplicateAlumniException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing for the alumni controllers: tenant and user from the verified-JWT session
 * (never the request body), the role gate, and the response envelope.
 *
 * AUTHORISATION: `staff.only` only keeps Student/Parent out and the `perm:` middleware is warn-only
 * in this codebase, so the role check is enforced here. Roles are matched by profile name because
 * profile ids differ per tenant. Areas:
 *   manage  - alumni records, profiles, events, communication, engagement
 *   finance - donations, payments, receipts, financial reports (adds Accountant)
 *   audit   - the audit-log viewer: Admin-type profiles only (it shows who did what, including donation amounts)
 */
trait AlumniApiSupport
{
    /** @var array<string, array<int, string>> */
    private static array $ALUMNI_ROLES = [
        'manage' => ['ADMIN', 'SUPER ADMIN', 'SCHOOL ADMIN', 'ASSISTANT ADMIN', 'ALUMNI COORDINATOR', 'ALUMNI ADMIN'],
        'finance' => ['ADMIN', 'SUPER ADMIN', 'SCHOOL ADMIN', 'ASSISTANT ADMIN', 'ACCOUNTANT'],
        'audit' => ['ADMIN', 'SUPER ADMIN', 'SCHOOL ADMIN', 'ASSISTANT ADMIN'],
    ];

    /** Null when allowed, otherwise the 403 response. */
    private function gate(Request $request, string $area = 'manage'): ?JsonResponse
    {
        $session = $request->session();
        if ((bool) $session->get('is_student')) {
            return $this->forbidden();   // students never manage alumni
        }
        if ((int) $session->get('is_admin') === 2) {
            return null;                 // platform super admin
        }
        $profile = strtoupper(trim((string) $session->get('user_profile_name')));

        return in_array($profile, self::$ALUMNI_ROLES[$area] ?? [], true) ? null : $this->forbidden();
    }

    /**
     * Student-facing routes (mentorship requests, opportunity board). Null when the caller is a real student of
     * this institute; otherwise the 403. The student's id comes from the verified JWT, never from the request,
     * so a student can only ever act on their own records.
     */
    private function studentGate(Request $request): ?JsonResponse
    {
        $session = $request->session();
        $id = (int) $session->get('user_id');
        if (! (bool) $session->get('is_student') || $id <= 0) {
            return response()->json(['status_code' => 0, 'message' => 'This is available to students only.'], 403);
        }
        $exists = \Illuminate\Support\Facades\DB::table('tblstudent')->where('id', $id)
            ->where('sub_institute_id', (int) $session->get('sub_institute_id'))->exists();

        return $exists ? null : response()->json(['status_code' => 0, 'message' => 'Student record not found.'], 403);
    }

    private function tenant(Request $request): int
    {
        return (int) $request->session()->get('sub_institute_id');
    }

    private function userId(Request $request): ?int
    {
        $u = $request->session()->get('user_id');

        return $u ? (int) $u : null;
    }

    private function ok($data, string $message = 'SUCCESS', int $code = 200): JsonResponse
    {
        return response()->json(['status_code' => 1, 'message' => $message, 'data' => $data], $code);
    }

    private function notFound(string $what = 'Alumni record'): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => $what . ' not found.'], 404);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => 'You do not have access to alumni management.'], 403);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
    }

    private function duplicate(DuplicateAlumniException $e): JsonResponse
    {
        return response()->json([
            'status_code' => 0, 'message' => 'A matching alumni record already exists.',
            'errors' => ['duplicate' => ['A matching alumni record already exists.']],
            'matches' => $e->matches,
        ], 409);
    }
}
