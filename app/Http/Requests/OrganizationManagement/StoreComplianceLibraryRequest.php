<?php

namespace App\Http\Requests\OrganizationManagement;

use App\Models\OrganizationManagement\ComplianceLibraryRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * G2G's compliance_library store branch had NO validation at all. Real
 * validation is added here per the port instructions (new file, not a
 * behavior change to any existing G2G code path).
 *
 * Compliance Management, frontend-completion pass: category_id/department_id/
 * priority added alongside the legacy `department` free-text field (kept
 * required|nullable as before - the transition column, see
 * 2026_09_28_100100_add_compliance_management_fields_to_org_compliance_library's
 * docblock). `status` is intentionally NOT accepted on create - every new
 * record starts at the model default (Upcoming); only complete()/verify
 * actions and update() may change it.
 */
class StoreComplianceLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                      => 'required|string|max:191',
            'description'               => 'nullable|string',
            'standard_name'             => 'nullable|string|max:191',
            'category_id'               => 'nullable|integer|exists:compliance_categories,id',
            'department'                => 'nullable|string|max:191',
            'department_id'             => 'nullable|integer|exists:hrms_departments,id',
            'assigned_to'               => 'required|integer',
            'duedate'                   => 'required|date',
            'frequency'                 => ['nullable', 'string', Rule::in(ComplianceLibraryRecord::FREQUENCIES)],
            'custom_frequency_details'  => 'nullable|string|max:191',
            'priority'                  => ['nullable', 'string', Rule::in(ComplianceLibraryRecord::PRIORITIES)],
            'attachment'                => 'nullable|file|max:20480',
        ];
    }
}
