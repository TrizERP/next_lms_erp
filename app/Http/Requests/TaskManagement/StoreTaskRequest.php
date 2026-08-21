<?php

namespace App\Http\Requests\TaskManagement;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Ported from hp_erp's `App\Http\Requests\TaskManagement\StoreTaskRequest`.
 *
 * Validation for the legacy task-create form (single / multiUser / BulkTask
 * shapes). Kept as-is for parity with the source frontend's payload; not
 * wired to a controller in this port (LegacyTaskController validates its own,
 * narrower JSON payload inline, matching this target's session-based
 * conventions) — provided so a legacy-form-shaped caller can still type-hint
 * against it.
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_title' => 'required|string|max:255',
            'task_description' => 'nullable|string|max:10000',
            // Comma-separated user ids, or a department id when the form
            // assigns by department instead of by person.
            'TASK_ALLOCATED_TO' => 'nullable|string|max:2000',
            'manageby' => 'nullable',
            // System priorities plus any custom priority the tenant defined.
            'selType' => 'nullable|string|max:100',
            'repeat_days' => 'nullable|integer|min:1|max:365',
            'repeat_until' => 'nullable|date',
            'TASK_DATE' => 'nullable|date',
            'KRA' => 'nullable|string|max:1000',
            'KPA' => 'nullable|string|max:1000',
            'skills' => 'nullable|string|max:2000',
            'skill_id' => 'nullable|string|max:2000',
            'observation_point' => 'nullable|string|max:2000',
            'formType' => 'nullable|string|max:30',
            'task_details' => 'nullable|json',
            'idempotency_key' => 'nullable|string|max:100',
            'TASK_ATTACHMENT' => 'nullable|file|max:5120|extensions:jpg,jpeg,png,pdf,doc,docx',
            'sub_institute_id' => 'required',
            'syear' => 'required',
        ];
    }

    /** JSON errors, not a redirect - every caller of this endpoint is an API client. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 0,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
