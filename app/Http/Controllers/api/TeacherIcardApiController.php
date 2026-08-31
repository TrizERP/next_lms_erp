<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\student\TeacherIcardApiController as LegacyTeacherIcardApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Self-service "My ID card" endpoint — lets a signed-in staff member view
 * their own I-card without an admin having to look them up.
 *
 * Extends the existing admin-facing teacher_icard API controller
 * (App\Http\Controllers\student\TeacherIcardApiController) purely to reuse
 * its protected card-rendering helpers — resolveTemplateOptions() and
 * buildTeacherCardHtml() — the exact same template-filling logic behind
 * student/api/teacher_icard/preview. This class adds no new card-layout
 * code; it only re-scopes the query from an admin-supplied `users[]` list
 * down to the caller's own session identity, so the self-service card is
 * pixel-identical to what an admin would generate for this same person.
 */
class TeacherIcardApiController extends LegacyTeacherIcardApiController
{
    /**
     * Returns the signed-in staff member's own I-card HTML.
     *
     * Identity comes exclusively from session() (hydrated by the
     * `api.session` middleware — see App\Http\Middleware\ApiSessionHydrator
     * and RoleDashboardApiController::teacherSummary() for the same
     * pattern), never from a client-supplied user id, so a valid token can
     * only ever produce that same person's card.
     *
     * Gate: reject only `is_student` sessions, rather than restricting to a
     * specific profile name (e.g. "Teacher"). The admin tool this mirrors
     * issues I-cards to any non-student profile in tbluserprofilemaster
     * (teacher_types() explicitly excludes only "Student"), so any staff
     * member — teacher, admin, front-desk, etc. — should be able to view
     * their own card here too.
     */
    public function mine(Request $request): JsonResponse
    {
        $subInstituteId = (string) session()->get('sub_institute_id');
        $userId = (string) session()->get('user_id');
        $isStudent = (bool) session()->get('is_student');

        if ($subInstituteId === '' || $userId === '') {
            return response()->json([
                'status' => 0,
                'message' => 'Session is missing required identity. Please sign in again.',
            ], 401);
        }

        if ($isStudent) {
            return response()->json([
                'status' => 0,
                'message' => 'The staff I-card is available to staff members only.',
            ], 403);
        }

        $templates = $this->resolveTemplateOptions();
        $template = (string) ($templates[0]['value'] ?? '');
        if ($template === '') {
            return response()->json([
                'status' => 0,
                'message' => 'No I-card template is configured.',
            ], 500);
        }

        // Same select + status filter as the admin preview() flow, just
        // scoped to a single, session-owned id instead of an admin-supplied
        // whereIn('id', $users) list.
        $userData = DB::table('tbluser')
            ->select('first_name', 'last_name', 'email', 'mobile', 'gender', 'address')
            ->where('sub_institute_id', $subInstituteId)
            ->where('id', $userId)
            ->where('status', 1)
            ->get()
            ->toArray();

        if (empty($userData)) {
            return response()->json([
                'status' => 0,
                'message' => 'Your staff profile could not be found.',
            ], 404);
        }

        try {
            $html = $this->buildTeacherCardHtml($userData, $template, 1, 1);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'html' => $html,
            'template' => $template,
            'row' => 1,
            'column' => 1,
        ]);
    }
}
