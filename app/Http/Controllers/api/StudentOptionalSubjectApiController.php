<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentOptionalSubjectApiController extends Controller
{
    use GetsJwtToken;

    public function search(Request $request): JsonResponse
    {
        if ($error = $this->guard($request, true)) return $error;
        $students = DB::table('tblstudent as s')->join('tblstudent_enrollment as e', 'e.student_id', '=', 's.id')
            ->selectRaw("s.id, s.enrollment_no, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name")
            ->where('e.sub_institute_id', $request->sub_institute_id)->where('e.syear', $request->syear)
            ->where('e.standard_id', $request->standard)->where('e.section_id', $request->division)
            ->where('s.status', 1)->whereNull('e.end_date')->orderBy('s.first_name')->get();
        $subjects = DB::table('sub_std_map as m')->join('subject as s', 's.id', '=', 'm.subject_id')
            ->select('m.subject_id as id', 's.subject_name', 's.subject_code')->where('m.sub_institute_id', $request->sub_institute_id)
            ->where('m.standard_id', $request->standard)->where('m.elective_subject', 'Yes')->orderBy('m.sort_order')->get();
        $studentIds = $students->pluck('id');
        $mapped = DB::table('student_optional_subject')->select('student_id', 'subject_id')
            ->where('sub_institute_id', $request->sub_institute_id)->where('syear', $request->syear)
            ->whereIn('student_id', $studentIds)->get()->groupBy('student_id')->map(fn ($rows) => $rows->pluck('subject_id')->values());
        return response()->json(['status' => 1, 'message' => 'Student list.', 'data' => $students, 'subjects' => $subjects, 'mapped' => $mapped]);
    }

    public function sync(Request $request): JsonResponse
    {
        if ($error = $this->guard($request)) return $error;
        $validator = Validator::make($request->all(), ['assignments' => 'required|array', 'assignments.*.student_id' => 'required|integer', 'assignments.*.subject_ids' => 'array']);
        if ($validator->fails()) return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'data' => []], 422);
        DB::transaction(function () use ($request) {
            foreach ($request->assignments as $assignment) {
                DB::table('student_optional_subject')->where('sub_institute_id', $request->sub_institute_id)
                    ->where('syear', $request->syear)->where('student_id', $assignment['student_id'])->delete();
                foreach (array_unique($assignment['subject_ids'] ?? []) as $subjectId) {
                    DB::table('student_optional_subject')->insert(['student_id' => $assignment['student_id'], 'subject_id' => $subjectId,
                        'sub_institute_id' => $request->sub_institute_id, 'syear' => $request->syear]);
                }
            }
        });
        return response()->json(['status' => 1, 'message' => 'Optional subjects mapped successfully.', 'data' => []]);
    }

    private function guard(Request $request, bool $search = false): ?JsonResponse
    {
        try { if (! $this->jwtToken()->validate()) return response()->json(['status' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401); }
        catch (\Exception $e) { return response()->json(['status' => 2, 'message' => $e->getMessage(), 'data' => []], 401); }
        $rules = ['sub_institute_id' => 'required|integer', 'syear' => 'required'];
        if ($search) $rules += ['standard' => 'required|integer', 'division' => 'required|integer'];
        $validator = Validator::make($request->all(), $rules);
        return $validator->fails() ? response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'data' => []], 422) : null;
    }
}
