<?php

namespace App\Http\Requests\OrganizationManagement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * G2G's compliance_library store branch had NO validation at all. Real
 * validation is added here per the port instructions (new file, not a
 * behavior change to any existing G2G code path).
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
            'department'                => 'nullable|string|max:191',
            'assigned_to'               => 'required|integer',
            'duedate'                   => 'required|date',
            'frequency'                 => 'nullable|string|max:100',
            'custom_frequency_details'  => 'nullable|string|max:191',
            'attachment'                => 'nullable|file|max:20480',
        ];
    }
}
