<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentCertificateApiController extends studentCertificateController
{
    protected function bootstrapRequestContext(Request $request): array
    {
        $context = [
            'sub_institute_id' => (string) $request->input('sub_institute_id', $request->session()->get('sub_institute_id')),
            'syear' => (string) $request->input('syear', $request->session()->get('syear')),
            'user_id' => (string) $request->input('user_id', $request->session()->get('user_id')),
            'term_id' => (string) $request->input('term_id', $request->session()->get('term_id')),
            'user_profile_id' => (string) $request->input('user_profile_id', $request->session()->get('user_profile_id')),
            'user_profile_name' => (string) $request->input('user_profile_name', $request->session()->get('user_profile_name')),
            'client_id' => (string) $request->input('client_id', $request->session()->get('client_id')),
        ];

        foreach ($context as $key => $value) {
            if ($value !== '') {
                $request->session()->put($key, $value);
            }
        }

        return $context;
    }

    protected function validateContext(array $context, bool $requiresAcademicYear = true): ?JsonResponse
    {
        if ($context['sub_institute_id'] === '') {
            return response()->json([
                'status' => 0,
                'message' => 'sub_institute_id is required.',
            ], 422);
        }

        if ($requiresAcademicYear && $context['syear'] === '') {
            return response()->json([
                'status' => 0,
                'message' => 'syear is required.',
            ], 422);
        }

        return null;
    }

    protected function ensureJsonResponse($response): JsonResponse
    {
        if ($response instanceof JsonResponse) {
            return $response;
        }

        if (is_array($response)) {
            return response()->json($response);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Unexpected response from student certificate workflow.',
        ], 500);
    }

    public function templates(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context, false);
        if ($validation) {
            return $validation;
        }

        $reportTypes = DB::table('template_master')
            ->where([
                'sub_institute_id' => $context['sub_institute_id'],
                'status' => 1,
            ])
            ->select('id', 'module_name')
            ->get()
            ->toArray();

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'report_types' => $reportTypes,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $request->merge(['type' => 'API']);

        return $this->ensureJsonResponse($this->showStudent($request));
    }

    public function preview(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $students = $request->input('students', []);
        if (!is_array($students)) {
            $students = array_filter(array_map('trim', explode(',', (string) $students)));
        }

        if (empty($students)) {
            return response()->json([
                'status' => 0,
                'message' => 'Please select at least one student.',
            ], 422);
        }

        $request->merge([
            'type' => 'API',
            'students' => $students,
        ]);

        return $this->ensureJsonResponse($this->showStudentCertificate($request));
    }

    public function save(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        if ((string) $request->input('insert_student_ids') === '') {
            return response()->json([
                'status' => 0,
                'message' => 'insert_student_ids is required.',
            ], 422);
        }

        if ((string) $request->input('template') === '') {
            return response()->json([
                'status' => 0,
                'message' => 'template is required.',
            ], 422);
        }

        $request->merge(['type' => 'API']);

        return $this->ensureJsonResponse($this->ajax_saveData($request));
    }

    public function history(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $request->merge(['type' => 'API']);

        $controller = app(student_certificate_reportController::class);

        return $this->ensureJsonResponse($controller->create($request));
    }
}
