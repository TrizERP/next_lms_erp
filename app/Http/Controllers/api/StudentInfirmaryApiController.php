<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\student\studentInfirmaryModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentInfirmaryApiController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $records = DB::table('student_infirmary as si')
            ->join('tblstudent as s', 's.id', '=', 'si.student_id')
            ->selectRaw("si.*, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name")
            ->where('si.sub_institute_id', $request->input('sub_institute_id'))
            ->orderByDesc('si.id')
            ->get();

        $nextCaseNumber = ((int) DB::table('student_infirmary')
            ->where('sub_institute_id', $request->input('sub_institute_id'))
            ->where('syear', $request->input('syear'))
            ->selectRaw('MAX(CAST(medical_case_no AS UNSIGNED)) as max_case')
            ->value('max_case')) + 1;

        return response()->json([
            'status' => 1,
            'message' => 'Student infirmary list.',
            'data' => ['records' => $records, 'next_case_number' => $nextCaseNumber],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
            'query' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $search = $request->input('query');
        $students = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
            ->where('se.sub_institute_id', $request->input('sub_institute_id'))
            ->where('se.syear', $request->input('syear'))
            ->whereNull('se.end_date')
            ->where('s.status', 1)
            ->where(function ($query) use ($search) {
                $query->where('s.enrollment_no', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ?", ['%'.$search.'%']);
            })
            ->selectRaw("s.id, s.enrollment_no, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name")
            ->groupBy('s.id')
            ->orderBy('s.first_name')
            ->limit(20)
            ->get();

        return response()->json(['status' => 1, 'message' => 'Students.', 'data' => $students]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }
        if ($validationError = $this->validateRecord($request)) {
            return $validationError;
        }

        $data = $this->recordData($request);
        $data['syear'] = $request->input('syear');
        $data['marking_period_id'] = $request->input('term_id');
        $data['sub_institute_id'] = $request->input('sub_institute_id');
        $data['created_by'] = $request->input('user_id');
        $data['created_on'] = now();
        $id = studentInfirmaryModel::insertGetId($data);

        return response()->json([
            'status' => 1,
            'message' => 'Student infirmary successfully created.',
            'data' => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }
        if ($validationError = $this->validateRecord($request)) {
            return $validationError;
        }

        $updated = studentInfirmaryModel::where('id', $id)
            ->where('sub_institute_id', $request->input('sub_institute_id'))
            ->update($this->recordData($request));

        if (! $updated) {
            return response()->json(['status' => 0, 'message' => 'Infirmary record not found.', 'data' => []], 404);
        }

        return response()->json(['status' => 1, 'message' => 'Student infirmary successfully updated.', 'data' => []]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($authError = $this->authenticate()) {
            return $authError;
        }

        $deleted = studentInfirmaryModel::where('id', $id)
            ->where('sub_institute_id', $request->input('sub_institute_id'))
            ->delete();

        if (! $deleted) {
            return response()->json(['status' => 0, 'message' => 'Infirmary record not found.', 'data' => []], 404);
        }

        return response()->json(['status' => 1, 'message' => 'Student infirmary deleted successfully.', 'data' => []]);
    }

    private function validateRecord(Request $request): ?JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
            'student_id' => ['required', 'integer'],
            'medical_case_no' => ['required', 'string', 'max:50'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_contact' => ['nullable', 'string', 'max:30'],
            'date' => ['required', 'date'],
            'complaint' => ['nullable', 'string', 'max:1000'],
            'symptoms' => ['nullable', 'string', 'max:1000'],
            'disease' => ['nullable', 'string', 'max:1000'],
            'treatments' => ['nullable', 'string', 'max:1000'],
            'medical_close_date' => ['nullable', 'date'],
            'health_center' => ['nullable', 'string', 'max:255'],
        ]);
        return $validator->fails() ? $this->validationError($validator) : null;
    }

    private function recordData(Request $request): array
    {
        return $request->only([
            'student_id',
            'medical_case_no',
            'doctor_name',
            'doctor_contact',
            'date',
            'complaint',
            'symptoms',
            'disease',
            'treatments',
            'medical_close_date',
            'health_center',
        ]);
    }

    private function authenticate(): ?JsonResponse
    {
        try {
            if ($this->jwtToken()->validate()) {
                return null;
            }
            return response()->json(['status' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
        } catch (\Exception $exception) {
            return response()->json(['status' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'message' => 'Invalid infirmary details.',
            'errors' => $validator->errors(),
            'data' => [],
        ], 422);
    }
}
