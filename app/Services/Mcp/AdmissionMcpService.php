<?php

namespace App\Services\Mcp;

use App\Http\Controllers\api\admissionRegistrationAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdmissionMcpService
{
    private array $requiredFields = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'admission_standard' => 'Standard',
        'admission_division' => 'Division',
        'student_quota' => 'Quota',
        'admission_date' => 'Admission date',
        'enrollment_no' => 'Enrollment number',
    ];

    public function listEnquiries(McpRequestContext $context, array $filters): array
    {
        $rows = DB::table('admission_enquiry as ae')
            ->leftJoin('standard as s', function ($join) {
                $join->on('s.id', '=', 'ae.admission_standard')
                    ->on('s.sub_institute_id', '=', 'ae.sub_institute_id');
            })
            ->selectRaw("ae.id, ae.enquiry_no, ae.status, ae.followup_date, ae.mobile, ae.admission_standard, s.name as standard_name, CONCAT_WS(' ', ae.first_name, ae.middle_name, ae.last_name) as student_name")
            ->where('ae.sub_institute_id', $context->selectedInstituteId)
            ->where('ae.syear', $context->academicYear)
            ->when(!empty($filters['search_text']), function ($query) use ($filters) {
                $search = trim((string) $filters['search_text']);
                $query->where(function ($builder) use ($search) {
                    $builder->where('ae.enquiry_no', 'like', '%' . $search . '%')
                        ->orWhere('ae.mobile', 'like', '%' . $search . '%')
                        ->orWhereRaw("CONCAT_WS(' ', ae.first_name, ae.middle_name, ae.last_name) like ?", ['%' . $search . '%']);
                });
            })
            ->orderByDesc('ae.id')
            ->limit((int) ($filters['limit'] ?? 25))
            ->get()
            ->map(function ($row) {
                return [
                    'enquiry_id' => (int) $row->id,
                    'enquiry_no' => (string) $row->enquiry_no,
                    'student_name' => trim((string) $row->student_name),
                    'mobile' => $row->mobile,
                    'standard_id' => $row->admission_standard ? (int) $row->admission_standard : null,
                    'standard_name' => $row->standard_name,
                    'status' => $row->status ?: 'new',
                    'followup_date' => $row->followup_date,
                ];
            })
            ->filter(function ($row) use ($filters) {
                if (!empty($filters['only_pending'])) {
                    return !in_array(strtolower((string) $row['status']), ['approved', 'converted', 'closed', 'cancel'], true);
                }
                return true;
            })
            ->values()
            ->all();

        return ToolResult::success(
            'admissions.listEnquiries',
            count($rows) > 0 ? 'Admission enquiries loaded successfully.' : 'No admission enquiries matched the current filters.',
            [
                'count' => count($rows),
                'enquiries' => $rows,
            ],
            [
                'conversationPatch' => [
                    'workflow' => 'admission_confirmation',
                    'currentStep' => 'awaiting_candidate_selection',
                    'candidateList' => $rows,
                    'selectedEntityType' => 'enquiry',
                    'workflowCompleted' => false,
                ],
            ]
        );
    }

    public function getEnquiryDetails(McpRequestContext $context, array $arguments): array
    {
        $enquiryId = (int) ($arguments['enquiry_id'] ?? 0);
        if ($enquiryId <= 0) {
            return ToolResult::failure('admissions.getEnquiryDetails', 'A valid enquiry is required.', 'MISSING_ENQUIRY_ID');
        }

        $record = DB::table('admission_enquiry as ae')
            ->leftJoin('admission_form as af', function ($join) use ($context) {
                $join->on('af.enquiry_id', '=', 'ae.id')
                    ->where('af.sub_institute_id', '=', $context->selectedInstituteId);
            })
            ->leftJoin('admission_registration as ar', function ($join) use ($context) {
                $join->on('ar.enquiry_id', '=', 'ae.id')
                    ->where('ar.sub_institute_id', '=', $context->selectedInstituteId);
            })
            ->leftJoin('standard as s', function ($join) {
                $join->on('s.id', '=', 'ae.admission_standard')
                    ->on('s.sub_institute_id', '=', 'ae.sub_institute_id');
            })
            ->leftJoin('division as d', function ($join) {
                $join->on('d.id', '=', 'ar.admission_division')
                    ->on('d.sub_institute_id', '=', 'ar.sub_institute_id');
            })
            ->leftJoin('student_quota as sq', function ($join) {
                $join->on('sq.id', '=', 'ar.student_quota')
                    ->on('sq.sub_institute_id', '=', 'ar.sub_institute_id');
            })
            ->selectRaw("ae.*, af.*, ar.*, ae.id as enquiry_id, ar.enquiry_id as registration_enquiry_id, CONCAT_WS(' ', ae.first_name, ae.middle_name, ae.last_name) as student_name, s.name as standard_name, d.name as division_name, sq.title as quota_name")
            ->where('ae.sub_institute_id', $context->selectedInstituteId)
            ->where('ae.syear', $context->academicYear)
            ->where('ae.id', $enquiryId)
            ->first();

        if (!$record) {
            return ToolResult::failure('admissions.getEnquiryDetails', 'Admission enquiry not found.', 'RECORD_NOT_FOUND');
        }

        $details = (array) $record;
        $validation = $this->validateConfirmation($context, ['enquiry_id' => $enquiryId]);

        return ToolResult::success(
            'admissions.getEnquiryDetails',
            'Admission enquiry details loaded successfully.',
            [
                'enquiry' => $this->presentAdmissionDetails($details),
                'validation' => $validation['data'],
            ],
            [
                'conversationPatch' => [
                    'workflow' => 'admission_confirmation',
                    'currentStep' => !empty($validation['data']['missing_fields']) ? 'collecting_missing_fields' : 'awaiting_confirmation',
                    'selectedEntityType' => 'enquiry',
                    'selectedEntityId' => $enquiryId,
                    'selectedCandidate' => $this->presentAdmissionDetails($details),
                    'requiredFields' => $validation['data']['missing_fields'] ?? [],
                    'workflowCompleted' => false,
                ],
            ]
        );
    }

    public function validateConfirmation(McpRequestContext $context, array $arguments): array
    {
        $enquiryId = (int) ($arguments['enquiry_id'] ?? 0);
        if ($enquiryId <= 0) {
            return ToolResult::failure('admissions.validateConfirmation', 'A valid enquiry is required.', 'MISSING_ENQUIRY_ID');
        }

        $record = DB::table('admission_enquiry as ae')
            ->leftJoin('admission_registration as ar', function ($join) use ($context) {
                $join->on('ar.enquiry_id', '=', 'ae.id')
                    ->where('ar.sub_institute_id', '=', $context->selectedInstituteId);
            })
            ->where('ae.sub_institute_id', $context->selectedInstituteId)
            ->where('ae.syear', $context->academicYear)
            ->where('ae.id', $enquiryId)
            ->selectRaw('ae.*, ar.*')
            ->first();

        if (!$record) {
            return ToolResult::failure('admissions.validateConfirmation', 'Admission enquiry not found.', 'RECORD_NOT_FOUND');
        }

        $details = (array) $record;
        $missingFields = [];
        foreach ($this->requiredFields as $field => $label) {
            $value = $details[$field] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $missingFields[] = [
                    'field' => $field,
                    'label' => $label,
                ];
            }
        }

        $existingStudent = DB::table('tblstudent')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('admission_year', $context->academicYear)
            ->where('admission_id', $enquiryId)
            ->first();

        return ToolResult::success(
            'admissions.validateConfirmation',
            empty($missingFields)
                ? 'Admission is ready for confirmation.'
                : 'Admission is missing required fields.',
            [
                'ready' => empty($missingFields) && !$existingStudent,
                'already_confirmed' => (bool) $existingStudent,
                'missing_fields' => $missingFields,
                'allowed_actions' => [
                    'confirm' => empty($missingFields) && !$existingStudent,
                    'open_confirmation_page' => true,
                ],
            ],
            [
                'uiAction' => [
                    'type' => 'navigate',
                    'path' => '/admissions/confirmation',
                    'params' => ['enquiry_id' => $enquiryId],
                ],
            ]
        );
    }

    public function previewConfirm(McpRequestContext $context, array $arguments): array
    {
        $validation = $this->validateConfirmation($context, $arguments);
        if (($validation['success'] ?? false) !== true) {
            return $validation;
        }

        if (!empty($validation['data']['already_confirmed'])) {
            return ToolResult::failure(
                'admissions.confirm',
                'This admission is already confirmed.',
                'ALREADY_CONFIRMED',
                [],
                [
                    'uiAction' => [
                        'type' => 'navigate',
                        'path' => '/admissions/confirmation',
                        'params' => ['enquiry_id' => (int) ($arguments['enquiry_id'] ?? 0)],
                    ],
                    'requiresConfirmation' => false,
                ]
            );
        }

        if (!empty($validation['data']['missing_fields'])) {
            return ToolResult::failure(
                'admissions.confirm',
                'Admission cannot be confirmed because required fields are missing.',
                'MISSING_REQUIRED_FIELDS',
                ['missing_fields' => $validation['data']['missing_fields']],
                [
                    'uiAction' => [
                        'type' => 'navigate',
                        'path' => '/admissions/confirmation',
                        'params' => ['enquiry_id' => (int) ($arguments['enquiry_id'] ?? 0)],
                    ],
                    'requiresConfirmation' => false,
                ]
            );
        }

        return ToolResult::success(
            'admissions.confirm',
            'Admission confirmation is ready. Explicit confirmation is required to continue.',
            [
                'enquiry_id' => (int) ($arguments['enquiry_id'] ?? 0),
            ],
            [
                'requiresConfirmation' => true,
                'uiAction' => [
                    'type' => 'navigate',
                    'path' => '/admissions/confirmation',
                    'params' => ['enquiry_id' => (int) ($arguments['enquiry_id'] ?? 0)],
                ],
            ]
        );
    }

    public function confirm(McpRequestContext $context, array $arguments): array
    {
        $enquiryId = (int) ($arguments['enquiry_id'] ?? 0);
        $beforeStudent = DB::table('tblstudent')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('admission_year', $context->academicYear)
            ->where('admission_id', $enquiryId)
            ->first();

        if ($beforeStudent) {
            return ToolResult::failure('admissions.confirm', 'This admission is already confirmed.', 'ALREADY_CONFIRMED');
        }

        $controller = app(admissionRegistrationAPIController::class);
        $request = Request::create('/api/admission_student', 'POST', [
            'type' => 'API',
            'id' => $enquiryId,
            'sub_institute_id' => $context->selectedInstituteId,
            'syear' => $context->academicYear,
            'term_id' => $context->termId,
            'user_id' => $context->userId,
        ]);

        $response = $controller->saveStudent($request);
        $payload = json_decode($response->getContent(), true) ?: [];

        $student = DB::table('tblstudent as s')
            ->leftJoin('tblstudent_enrollment as se', function ($join) use ($context) {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.syear', '=', $context->academicYear)
                    ->where('se.sub_institute_id', '=', $context->selectedInstituteId)
                    ->whereNull('se.end_date');
            })
            ->leftJoin('standard as std', 'std.id', '=', 'se.standard_id')
            ->leftJoin('division as div', 'div.id', '=', 'se.section_id')
            ->selectRaw("s.id as student_id, s.enrollment_no, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name, std.name as standard_name, div.name as division_name")
            ->where('s.sub_institute_id', $context->selectedInstituteId)
            ->where('s.admission_year', $context->academicYear)
            ->where('s.admission_id', $enquiryId)
            ->orderByDesc('s.id')
            ->first();

        if (!$student) {
            return ToolResult::failure('admissions.confirm', 'The backend did not confirm the admission.', 'BACKEND_CONFIRMATION_FAILED', ['backend' => $payload]);
        }

        return ToolResult::success(
            'admissions.confirm',
            trim((string) $student->student_name) . ' admission has been confirmed successfully.',
            [
                'student_id' => (int) $student->student_id,
                'student_name' => trim((string) $student->student_name),
                'admission_number' => $student->enrollment_no,
                'standard_name' => $student->standard_name,
                'division_name' => $student->division_name,
                'status' => 'Confirmed',
                'backend' => $payload,
            ],
            [
                'uiAction' => [
                    'type' => 'navigate',
                    'path' => '/admissions/confirmation',
                    'params' => ['student_id' => (int) $student->student_id],
                ],
                'conversationPatch' => [
                    'workflowCompleted' => true,
                    'pendingAction' => null,
                    'selectedEntityId' => $enquiryId,
                    'selectedCandidate' => [
                        'student_id' => (int) $student->student_id,
                        'student_name' => trim((string) $student->student_name),
                    ],
                ],
            ]
        );
    }

    private function presentAdmissionDetails(array $details): array
    {
        return [
            'enquiry_id' => (int) ($details['enquiry_id'] ?? $details['id'] ?? 0),
            'registration_enquiry_id' => isset($details['registration_enquiry_id']) ? (int) $details['registration_enquiry_id'] : null,
            'enquiry_no' => $details['enquiry_no'] ?? null,
            'student_name' => trim((string) ($details['student_name'] ?? '')),
            'mobile' => $details['mobile'] ?? null,
            'standard_id' => isset($details['admission_standard']) ? (int) $details['admission_standard'] : null,
            'standard_name' => $details['standard_name'] ?? null,
            'division_id' => isset($details['admission_division']) ? (int) $details['admission_division'] : null,
            'division_name' => $details['division_name'] ?? null,
            'quota_id' => isset($details['student_quota']) ? (int) $details['student_quota'] : null,
            'quota_name' => $details['quota_name'] ?? null,
            'admission_date' => $details['admission_date'] ?? null,
            'status' => $details['status'] ?? 'new',
        ];
    }
}
