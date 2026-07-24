<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TeacherDailyReportApiController extends Controller
{
    use GetsJwtToken;

    private const ACTIONS = [
        'attendance',
        'homework_assign',
        'homework_check',
        'parent_comm',
        'student_leave',
    ];

    private function context(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json(['status_code' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
            }
        } catch (\Exception $exception) {
            return response()->json(['status_code' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'user_id' => 'required|integer',
            'syear' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->messages()->first(), 'data' => []], 422);
        }

        $authorization = (string) $request->header('Authorization');
        $token = preg_replace('/^Bearer\s+/i', '', $authorization);
        $parts = explode('.', $token);
        $payload = [];
        if (count($parts) === 3) {
            $decoded = base64_decode(strtr($parts[1], '-_', '+/'));
            $payload = json_decode($decoded ?: '{}', true) ?: [];
        }

        $actorId = (int) ($payload['id'] ?? 0);
        $tenantId = (int) ($payload['sub_institute_id'] ?? 0);
        if ($actorId !== $request->integer('user_id') || $tenantId !== $request->integer('sub_institute_id')) {
            return response()->json(['status_code' => 2, 'message' => 'Token context does not match the request.', 'data' => []], 403);
        }

        $actor = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->select('u.id', 'u.user_profile_id', 'u.sub_institute_id', 'p.name as profile_name')
            ->where('u.id', $actorId)
            ->where('u.sub_institute_id', $tenantId)
            ->where('u.status', 1)
            ->first();
        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }

        $request->attributes->set('report_actor', $actor);

        return null;
    }

    private function authorizeReport(Request $request)
    {
        $actor = $request->attributes->get('report_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return null;
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', 'teacher_daily_report.index')->value('id');
        $individual = $menuId ? DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)
            ->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->first() : null;
        $rights = $individual ?: ($menuId ? DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)
            ->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->first() : null);

        if (! (bool) ($rights->can_view ?? false)) {
            return response()->json(['status_code' => 0, 'message' => 'You do not have permission to view this report.', 'data' => []], 403);
        }

        return null;
    }

    public function search(Request $request)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        if ($response = $this->authorizeReport($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'status' => 'nullable|in:Y,N',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
                'data' => [],
            ], 422);
        }

        $actor = $request->attributes->get('report_actor');
        $date = $request->input('date');
        $query = DB::table('tbluser as teacher')
            ->join('tbluserprofilemaster as profile', function ($join) {
                $join->on('profile.id', '=', 'teacher.user_profile_id')->where('profile.name', 'Teacher');
            })
            ->join('timetable', function ($join) use ($request) {
                $join->on('timetable.teacher_id', '=', 'teacher.id')->where('timetable.syear', $request->integer('syear'));
            })
            ->select(
                'teacher.id as teacher_id',
                DB::raw("concat_ws(' ', teacher.first_name, teacher.middle_name, teacher.last_name) as teacher_name")
            )
            ->selectRaw("IF(EXISTS(SELECT 1 FROM attendance_student a WHERE a.created_by = teacher.id AND DATE(a.created_on) = ?), 'Yes', 'No') as student_attendance", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM homework h WHERE h.created_by = teacher.id AND DATE(h.created_on) = ?), 'Yes', 'No') as homework_assign", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM homework h WHERE h.created_by = teacher.id AND DATE(h.submission_date) = ? AND h.completion_status = 'Y'), 'Yes', 'No') as homework_check", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM parent_communication p WHERE p.reply_by = teacher.id AND DATE(p.created_at) = ?), 'Yes', 'No') as parent_comm", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM leave_applications l WHERE l.reply = teacher.id AND DATE(l.apply_date) = ?), 'Yes', 'No') as student_leave", [$date])
            ->where('teacher.sub_institute_id', $actor->sub_institute_id)
            ->where('teacher.status', 1)
            ->groupBy('teacher.id', 'teacher.first_name', 'teacher.middle_name', 'teacher.last_name');

        if ($request->input('status') === 'Y') {
            $query->havingRaw("student_attendance = 'Yes' OR homework_assign = 'Yes' OR homework_check = 'Yes' OR parent_comm = 'Yes' OR student_leave = 'Yes'");
        } elseif ($request->input('status') === 'N') {
            $query->havingRaw("student_attendance = 'No' AND homework_assign = 'No' AND homework_check = 'No' AND parent_comm = 'No' AND student_leave = 'No'");
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => ['teachers' => $query->orderBy('teacher.first_name')->get()],
        ]);
    }

    public function details(Request $request, $teacherId)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        if ($response = $this->authorizeReport($request)) {
            return $response;
        }

        $actor = $request->attributes->get('report_actor');
        $validator = Validator::make(array_merge($request->all(), ['teacher_id' => $teacherId]), [
            'date' => 'required|date_format:Y-m-d',
            'action' => ['required', Rule::in(self::ACTIONS)],
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('tbluser', 'id')->where(fn ($query) => $query
                    ->where('sub_institute_id', $actor->sub_institute_id)
                    ->where('status', 1)),
            ],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
                'data' => [],
            ], 422);
        }

        $date = $request->input('date');
        $action = $request->input('action');
        [$columns, $rows] = $this->detailData(
            $action,
            (int) $teacherId,
            $date,
            $actor->sub_institute_id,
            $request->integer('syear')
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => ['columns' => $columns, 'rows' => $rows],
        ]);
    }

    private function detailData(string $action, int $teacherId, string $date, int $tenantId, int $syear): array
    {
        $teacherName = "concat_ws(' ', teacher.first_name, teacher.middle_name, teacher.last_name)";

        if ($action === 'homework_assign') {
            $rows = DB::table('homework as item')
                ->join('tbluser as teacher', 'teacher.id', '=', 'item.created_by')
                ->join('standard as standard', 'standard.id', '=', 'item.standard_id')
                ->join('division as division', 'division.id', '=', 'item.division_id')
                ->join('subject as subject', 'subject.id', '=', 'item.subject_id')
                ->selectRaw("concat(standard.name, '-', division.name) as class_name, subject.subject_name, item.title, item.image as attachment, {$teacherName} as teacher_name, date_format(item.created_on, '%d-%m-%Y') as activity_date")
                ->where('item.syear', $syear)->where('item.sub_institute_id', $tenantId)
                ->where('item.created_by', $teacherId)->whereDate('item.created_on', $date)
                ->distinct()->get();

            return [[
                ['key' => 'class_name', 'label' => 'Std-Div'],
                ['key' => 'subject_name', 'label' => 'Subject'],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'attachment', 'label' => 'Attachment'],
                ['key' => 'teacher_name', 'label' => 'Teacher Name'],
                ['key' => 'activity_date', 'label' => 'Date'],
            ], $rows];
        }

        if ($action === 'homework_check') {
            $rows = DB::table('homework as item')
                ->join('tbluser as teacher', 'teacher.id', '=', 'item.reply_by')
                ->join('standard as standard', 'standard.id', '=', 'item.standard_id')
                ->join('division as division', 'division.id', '=', 'item.division_id')
                ->join('subject as subject', 'subject.id', '=', 'item.subject_id')
                ->selectRaw("concat(standard.name, '-', division.name) as class_name, subject.subject_name, item.title, item.image as attachment, {$teacherName} as teacher_name, date_format(item.created_on, '%d-%m-%Y') as activity_date, date_format(item.submission_date, '%d-%m-%Y') as submission_date, item.submission_remarks, item.completion_status")
                ->where('item.syear', $syear)->where('item.sub_institute_id', $tenantId)
                ->where('item.reply_by', $teacherId)->whereDate('item.submission_date', $date)
                ->where('item.completion_status', 'Y')->distinct()->get();

            return [[
                ['key' => 'class_name', 'label' => 'Std-Div'],
                ['key' => 'subject_name', 'label' => 'Subject'],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'attachment', 'label' => 'Attachment'],
                ['key' => 'teacher_name', 'label' => 'Teacher Name'],
                ['key' => 'activity_date', 'label' => 'Date'],
                ['key' => 'submission_date', 'label' => 'Submission Date'],
                ['key' => 'submission_remarks', 'label' => 'Submission Remark'],
                ['key' => 'completion_status', 'label' => 'Submission Status'],
            ], $rows];
        }

        if ($action === 'attendance') {
            $rows = DB::table('attendance_student as item')
                ->join('tbluser as teacher', 'teacher.id', '=', 'item.created_by')
                ->join('standard as standard', 'standard.id', '=', 'item.standard_id')
                ->join('division as division', 'division.id', '=', 'item.section_id')
                ->selectRaw("concat(standard.name, '-', division.name) as class_name, {$teacherName} as teacher_name, date_format(item.created_on, '%d-%m-%Y') as created_date, item.attendance_code, date_format(item.attendance_date, '%d-%m-%Y') as attendance_date")
                ->where('item.syear', $syear)->where('item.sub_institute_id', $tenantId)
                ->where('item.created_by', $teacherId)->whereDate('item.created_on', $date)
                ->distinct()->get();

            return [[
                ['key' => 'class_name', 'label' => 'Std-Div'],
                ['key' => 'teacher_name', 'label' => 'Teacher Name'],
                ['key' => 'created_date', 'label' => 'Created Date'],
                ['key' => 'attendance_code', 'label' => 'Attendance Code'],
                ['key' => 'attendance_date', 'label' => 'Attendance Date'],
            ], $rows];
        }

        $timetableJoin = function ($join) use ($teacherId, $syear) {
            $join->on('timetable.teacher_id', '=', 'teacher.id')
                ->where('timetable.teacher_id', $teacherId)
                ->where('timetable.syear', $syear);
        };

        if ($action === 'parent_comm') {
            $rows = DB::table('parent_communication as item')
                ->join('tbluser as teacher', 'teacher.id', '=', 'item.reply_by')
                ->join('timetable', $timetableJoin)
                ->join('standard as standard', 'standard.id', '=', 'timetable.standard_id')
                ->join('division as division', 'division.id', '=', 'timetable.division_id')
                ->selectRaw("concat(standard.name, '-', division.name) as class_name, {$teacherName} as teacher_name, item.message, item.reply, date_format(item.created_at, '%d-%m-%Y') as created_date, date_format(item.reply_on, '%d-%m-%Y') as reply_date")
                ->where('item.syear', $syear)->where('item.sub_institute_id', $tenantId)
                ->where('item.reply_by', $teacherId)->whereDate('item.created_at', $date)
                ->distinct()->get();

            return [[
                ['key' => 'class_name', 'label' => 'Std-Div'],
                ['key' => 'teacher_name', 'label' => 'Teacher Name'],
                ['key' => 'message', 'label' => 'Message'],
                ['key' => 'reply', 'label' => 'Reply'],
                ['key' => 'created_date', 'label' => 'Created Date'],
                ['key' => 'reply_date', 'label' => 'Reply Date'],
            ], $rows];
        }

        $rows = DB::table('leave_applications as item')
            ->join('tbluser as teacher', 'teacher.id', '=', 'item.reply')
            ->join('timetable', $timetableJoin)
            ->join('standard as standard', 'standard.id', '=', 'timetable.standard_id')
            ->join('division as division', 'division.id', '=', 'timetable.division_id')
            ->selectRaw("concat(standard.name, '-', division.name) as class_name, {$teacherName} as teacher_name, item.message, item.status, item.files, date_format(item.apply_date, '%d-%m-%Y') as applied_date")
            ->where('item.syear', $syear)->where('item.sub_institute_id', $tenantId)
            ->where('item.reply', $teacherId)->whereDate('item.apply_date', $date)
            ->distinct()->get();

        return [[
            ['key' => 'class_name', 'label' => 'Std-Div'],
            ['key' => 'teacher_name', 'label' => 'Teacher Name'],
            ['key' => 'message', 'label' => 'Message'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'files', 'label' => 'File'],
            ['key' => 'applied_date', 'label' => 'Applied Date'],
        ], $rows];
    }
}
