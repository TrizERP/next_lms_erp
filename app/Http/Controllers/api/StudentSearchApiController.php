<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use App\Services\Graph\GraphSync;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentSearchApiController extends Controller
{
    use GetsJwtToken;

    public function search(Request $request): JsonResponse
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json([
                    'status' => 2,
                    'message' => 'Token Auth Failed',
                    'data' => [],
                ], 401);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'status' => 2,
                'message' => $exception->getMessage(),
                'data' => [],
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
            'user_id' => ['nullable', 'integer'],
            'grade' => ['nullable', 'integer'],
            'standard' => ['nullable', 'integer'],
            'division' => ['nullable', 'integer'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'gr_no' => ['nullable', 'string', 'max:100'],
            'unique_id' => ['nullable', 'string', 'max:100'],
            'including_inactive' => ['nullable', 'in:Yes,No'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid search parameters.',
                'errors' => $validator->errors(),
                'data' => [],
            ], 422);
        }

        $includingInactive = $request->input('including_inactive') === 'Yes';

        $students = tblstudentModel::select(
            'tblstudent.*',
            'tblstudent_enrollment.*',
            'tblstudent.id as student_id',
            'standard.name as standard',
            'division.name as division',
            'academic_section.title as grade',
            'student_quota.title as student_quota',
            DB::raw($includingInactive
                ? 'IF(tblstudent_enrollment.end_date IS NOT NULL, "pink", "") as inactive_colour'
                : '"" as inactive_colour')
        )
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->leftJoin('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->where('tblstudent_enrollment.sub_institute_id', $request->input('sub_institute_id'))
            ->where('tblstudent_enrollment.syear', $request->input('syear'))
            ->where('tblstudent.status', 1)
            ->when($request->filled('grade'), function ($query) use ($request) {
                $query->where('tblstudent_enrollment.grade_id', $request->input('grade'));
            })
            ->when($request->filled('standard'), function ($query) use ($request) {
                $query->where('tblstudent_enrollment.standard_id', $request->input('standard'));
            })
            ->when($request->filled('division'), function ($query) use ($request) {
                $query->where('tblstudent_enrollment.section_id', $request->input('division'));
            })
            ->when($request->filled('first_name'), function ($query) use ($request) {
                $query->where('tblstudent.first_name', 'like', '%'.$request->input('first_name').'%');
            })
            ->when($request->filled('last_name'), function ($query) use ($request) {
                $query->where('tblstudent.last_name', 'like', '%'.$request->input('last_name').'%');
            })
            ->when($request->filled('mobile'), function ($query) use ($request) {
                $query->where('tblstudent.mobile', $request->input('mobile'));
            })
            ->when($request->filled('gr_no'), function ($query) use ($request) {
                $query->where('tblstudent.enrollment_no', $request->input('gr_no'));
            })
            ->when($request->filled('unique_id'), function ($query) use ($request) {
                $query->where('tblstudent.uniqueid', $request->input('unique_id'));
            })
            ->when(
                $request->input('user_profile_name') === 'Student' && $request->filled('user_id'),
                function ($query) use ($request) {
                    $query->where('tblstudent.id', $request->input('user_id'));
                }
            )
            ->when(! $includingInactive, function ($query) {
                $query->whereNull('tblstudent_enrollment.end_date');
            })
            ->get();

        return response()->json([
            'status' => 1,
            'message' => 'Student List',
            'data' => $students,
        ]);
    }

    public function update(Request $request, int $studentId): JsonResponse
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json([
                    'status' => 2,
                    'message' => 'Token Auth Failed',
                    'data' => [],
                ], 401);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'status' => 2,
                'message' => $exception->getMessage(),
                'data' => [],
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
            'name' => ['required', 'string', 'max:255'],
            'admission_no' => ['nullable', 'string', 'max:100'],
            'roll_no' => ['nullable', 'string', 'max:50'],
            'class_name' => ['nullable', 'string', 'max:100'],
            'section_name' => ['nullable', 'string', 'max:100'],
            'house' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'blood_group' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid student details.',
                'errors' => $validator->errors(),
                'data' => [],
            ], 422);
        }

        $student = tblstudentModel::where('id', $studentId)
            ->where('sub_institute_id', $request->input('sub_institute_id'))
            ->first();

        if (! $student) {
            return response()->json([
                'status' => 0,
                'message' => 'Student not found.',
                'data' => [],
            ], 404);
        }

        $nameParts = preg_split('/\s+/', trim($request->input('name')), -1, PREG_SPLIT_NO_EMPTY);
        $firstName = array_shift($nameParts) ?? '';
        $lastName = count($nameParts) > 0 ? array_pop($nameParts) : '';
        $middleName = implode(' ', $nameParts);

        $graphIds = ['log' => [], 'queue' => []];

        DB::transaction(function () use (
            $request,
            $student,
            $studentId,
            $firstName,
            $middleName,
            $lastName,
            &$graphIds
        ) {
            $student->update([
                'enrollment_no' => $request->input('admission_no'),
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'father_name' => $request->input('father_name'),
                'mother_name' => $request->input('mother_name'),
                'gender' => $request->input('gender'),
                'dob' => $request->input('dob'),
                'mobile' => $request->input('mobile'),
                'email' => $request->input('email'),
                'address' => $request->input('address'),
                'bloodgroup' => $request->input('blood_group'),
                'house' => $request->input('house'),
                'updated_on' => now(),
            ]);

            $enrollmentUpdate = [
                'roll_no' => $request->input('roll_no'),
                'updated_on' => now(),
            ];

            if ($request->filled('class_name')) {
                $standard = DB::table('standard')
                    ->where('sub_institute_id', $request->input('sub_institute_id'))
                    ->where('name', $request->input('class_name'))
                    ->first();
                if ($standard) {
                    $enrollmentUpdate['standard_id'] = $standard->id;
                    $enrollmentUpdate['grade_id'] = $standard->grade_id;
                }
            }

            if ($request->filled('section_name')) {
                $division = DB::table('division')
                    ->where('sub_institute_id', $request->input('sub_institute_id'))
                    ->where('name', $request->input('section_name'))
                    ->first();
                if ($division) {
                    $enrollmentUpdate['section_id'] = $division->id;
                }
            }

            DB::table('tblstudent_enrollment')
                ->where('student_id', $studentId)
                ->where('sub_institute_id', $request->input('sub_institute_id'))
                ->where('syear', $request->input('syear'))
                ->update($enrollmentUpdate);

            // Same projection as creation, so an edit re-MERGEs the existing
            // :StuDetail / :Student nodes in place rather than duplicating
            // them. A class change also emits old_target_id, so the stale
            // ENROLLED_IN edge is removed instead of the student appearing in
            // both standards.
            $graphIds = app(GraphSync::class)->enqueueStudent($studentId, (int) $request->input('sub_institute_id'));
        });

        app(GraphSync::class)->flush($graphIds);

        return response()->json([
            'status' => 1,
            'message' => 'Student updated successfully.',
            'data' => ['student_id' => $studentId],
        ]);
    }
}
