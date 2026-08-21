<?php

namespace App\Http\Requests\TaskManagement;

use App\Rules\TaskManagement\SafeCsvFile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Ported from hp_erp's `App\Http\Requests\TaskManagement\BulkTaskImportRequest`.
 *
 * Either a CSV file or a `task_details` JSON payload, validated with the
 * freshly-written `SafeCsvFile` rule (see its doc-block — the source class it
 * references was never actually committed either).
 */
class BulkTaskImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => [
                'nullable',
                'required_without:task_details',
                'file',
                'max:2048',
                'extensions:csv',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
                new SafeCsvFile(),
            ],
            'task_details' => 'nullable|required_without:csv_file|json',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 0,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
