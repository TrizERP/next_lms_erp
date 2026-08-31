<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "My timetable" self-service endpoint for the Teacher role. Runs behind the
 * `api.session` middleware (App\Http\Middleware\ApiSessionHydrator), which
 * validates the JWT and hydrates session() with user_id, sub_institute_id,
 * syear and user_profile_name — the same trust model as
 * RoleDashboardApiController::teacherSummary(). Identity comes only from
 * session(), never from the request body.
 *
 * Scoping mirrors TeacherAssignmentMobileApiController: the teacher's own
 * rows in `timetable` (teacher_id = session user_id) are the source of
 * truth for "which classes/periods is this teacher teaching", since there
 * is no dedicated teacher-subject/teacher-period mapping table.
 */
class TeacherTimetableApiController extends Controller
{
    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['status' => '0', 'message' => $message], 403);
    }

    public function summary(Request $request): JsonResponse
    {
        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userId = session()->get('user_id');
        $profileName = strtolower(trim((string) session()->get('user_profile_name')));
        $isStudent = (bool) session()->get('is_student');

        if ($isStudent || ! in_array($profileName, ['teacher', 'lms teacher'], true)) {
            return $this->unauthorized('This page is available to teachers only.');
        }

        $timetable = DB::table('timetable as t')
            ->join('period as p', 'p.id', '=', 't.period_id')
            ->join('subject as sub', 'sub.id', '=', 't.subject_id')
            ->join('standard as s', 's.id', '=', 't.standard_id')
            ->join('division as d', 'd.id', '=', 't.division_id')
            ->selectRaw(
                't.week_day, t.period_id, p.title as period_title, p.start_time, p.end_time, '
                . 't.subject_id, sub.subject_name, t.standard_id, s.name as standard_name, '
                . 't.division_id, d.name as division_name'
            )
            ->where('t.teacher_id', $userId)
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('t.syear', $syear)
            ->orderBy('t.week_day')
            ->orderBy('p.start_time')
            ->get();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'timetable' => $timetable,
        ]);
    }
}
