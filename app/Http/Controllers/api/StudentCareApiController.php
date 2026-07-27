<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StudentCareApiController extends Controller
{
    use GetsJwtToken;

    private const MODULES = [
        'vaccination' => [
            'table' => 'student_vaccination',
            'fields' => ['doctor_name', 'doctor_contact', 'date', 'vaccination_type', 'note'],
            'required' => ['date'],
        ],
        'height-weight' => [
            'table' => 'student_height_weight',
            'fields' => ['doctor_name', 'doctor_contact', 'date', 'height', 'weight'],
            'required' => ['date'],
        ],
        'health' => [
            'table' => 'student_health',
            'fields' => ['doctor_name', 'doctor_contact', 'date', 'remarks', 'file'],
            'required' => ['date'],
        ],
        'discipline' => [
            'table' => 'dicipline',
            'fields' => ['dicipline', 'message', 'flag', 'date_'],
            'required' => ['message', 'date_'],
        ],
    ];

    public function index(Request $request, string $module): JsonResponse
    {
        if ($authError = $this->authenticate()) return $authError;
        if (! $config = $this->config($module)) return $this->unknownModule();
        if ($error = $this->validateContext($request)) return $error;

        $records = DB::table($config['table'].' as r')
            ->join('tblstudent as s', 's.id', '=', 'r.student_id')
            ->selectRaw("r.*, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name")
            ->where('r.sub_institute_id', $request->input('sub_institute_id'))
            ->orderByDesc('r.id')
            ->get();

        return response()->json(['status' => 1, 'message' => 'Records.', 'data' => $records]);
    }

    public function store(Request $request, string $module): JsonResponse
    {
        if ($authError = $this->authenticate()) return $authError;
        if (! $config = $this->config($module)) return $this->unknownModule();
        if ($error = $this->validatePayload($request, $config)) return $error;

        $data = $this->payload($request, $module, $config);
        $data['student_id'] = $request->input('student_id');
        $data['syear'] = $request->input('syear');
        $data['sub_institute_id'] = $request->input('sub_institute_id');
        $data['created_by'] = $request->input('user_id');

        if ($module === 'discipline') {
            $data['created_at'] = now();
            $data['name'] = $this->userName($request->input('user_id'));
        } else {
            $data['created_on'] = now();
            $data['marking_period_id'] = $request->input('term_id');
        }

        $id = DB::table($config['table'])->insertGetId($data);
        return response()->json(['status' => 1, 'message' => 'Record created successfully.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, string $module, int $id): JsonResponse
    {
        if ($authError = $this->authenticate()) return $authError;
        if (! $config = $this->config($module)) return $this->unknownModule();
        if ($error = $this->validatePayload($request, $config)) return $error;

        $data = $this->payload($request, $module, $config);
        $data['student_id'] = $request->input('student_id');
        $updated = DB::table($config['table'])->where('id', $id)
            ->where('sub_institute_id', $request->input('sub_institute_id'))
            ->update($data);

        if (! $updated) return response()->json(['status' => 0, 'message' => 'Record not found.', 'data' => []], 404);
        return response()->json(['status' => 1, 'message' => 'Record updated successfully.', 'data' => []]);
    }

    public function destroy(Request $request, string $module, int $id): JsonResponse
    {
        if ($authError = $this->authenticate()) return $authError;
        if (! $config = $this->config($module)) return $this->unknownModule();
        if ($error = $this->validateContext($request)) return $error;

        $deleted = DB::table($config['table'])->where('id', $id)
            ->where('sub_institute_id', $request->input('sub_institute_id'))
            ->delete();
        if (! $deleted) return response()->json(['status' => 0, 'message' => 'Record not found.', 'data' => []], 404);
        return response()->json(['status' => 1, 'message' => 'Record deleted successfully.', 'data' => []]);
    }

    private function payload(Request $request, string $module, array $config): array
    {
        $data = $request->only(array_filter($config['fields'], fn ($field) => $field !== 'file'));
        if ($module === 'health' && $request->filled('file_data')) {
            $parts = explode(',', $request->input('file_data'), 2);
            $binary = base64_decode($parts[1] ?? '', true);
            if ($binary !== false) {
                $extension = pathinfo($request->input('file_name', ''), PATHINFO_EXTENSION) ?: 'bin';
                $fileName = 'health_document_'.date('YmdHis').'_'.uniqid().'.'.$extension;
                Storage::put('public/frontdesk/'.$fileName, $binary);
                $data['file'] = $fileName;
                $data['file_size'] = strlen($binary);
                $data['file_type'] = $extension;
            }
        }
        return $data;
    }

    private function validatePayload(Request $request, array $config): ?JsonResponse
    {
        $rules = [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
            'student_id' => ['required', 'integer'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_contact' => ['nullable', 'string', 'max:30'],
            'date' => ['nullable', 'date'],
            'date_' => ['nullable', 'date'],
            'vaccination_type' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'height' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'dicipline' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:3000'],
            'flag' => ['nullable', 'integer', 'in:-1,0,1'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_data' => ['nullable', 'string'],
        ];
        foreach ($config['required'] as $field) $rules[$field][0] = 'required';
        $validator = Validator::make($request->all(), $rules);
        return $validator->fails() ? $this->validationError($validator) : null;
    }

    private function validateContext(Request $request): ?JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => ['required', 'integer'],
            'syear' => ['required'],
        ]);
        return $validator->fails() ? $this->validationError($validator) : null;
    }

    private function config(string $module): ?array
    {
        return self::MODULES[$module] ?? null;
    }

    private function userName($userId): string
    {
        $user = DB::table('tbluser')->where('id', $userId)->first();
        return $user ? trim($user->first_name.' '.$user->last_name) : '';
    }

    private function authenticate(): ?JsonResponse
    {
        try {
            return $this->jwtToken()->validate()
                ? null
                : response()->json(['status' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
        } catch (\Exception $exception) {
            return response()->json(['status' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json(['status' => 0, 'message' => 'Invalid record details.', 'errors' => $validator->errors(), 'data' => []], 422);
    }

    private function unknownModule(): JsonResponse
    {
        return response()->json(['status' => 0, 'message' => 'Unknown student care module.', 'data' => []], 404);
    }
}
