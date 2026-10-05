<?php

namespace App\Http\Requests\OrganizationManagement;

use App\Models\OrganizationManagement\ComplianceLibraryRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                      => 'sometimes|required|string|max:191',
            'description'               => 'nullable|string',
            'standard_name'             => 'nullable|string|max:191',
            'category_id'               => 'nullable|integer|exists:compliance_categories,id',
            'department'                => 'nullable|string|max:191',
            'department_id'             => 'nullable|integer|exists:hrms_departments,id',
            'assigned_to'               => 'sometimes|required|integer',
            'duedate'                   => 'sometimes|required|date',
            'frequency'                 => ['nullable', 'string', Rule::in(ComplianceLibraryRecord::FREQUENCIES)],
            'custom_frequency_details'  => 'nullable|string|max:191',
            'priority'                  => ['nullable', 'string', Rule::in(ComplianceLibraryRecord::PRIORITIES)],
            'status'                    => ['nullable', 'string', Rule::in(ComplianceLibraryRecord::STATUSES)],
            'attachment'                => 'nullable|file|max:20480',
            'oldAttachment'             => 'nullable|string',
        ];
    }
}
