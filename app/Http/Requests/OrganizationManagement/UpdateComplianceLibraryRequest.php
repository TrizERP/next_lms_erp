<?php

namespace App\Http\Requests\OrganizationManagement;

use Illuminate\Foundation\Http\FormRequest;

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
            'department'                => 'nullable|string|max:191',
            'assigned_to'               => 'sometimes|required|integer',
            'duedate'                   => 'sometimes|required|date',
            'frequency'                 => 'nullable|string|max:100',
            'custom_frequency_details'  => 'nullable|string|max:191',
            'attachment'                => 'nullable|file|max:20480',
            'oldAttachment'             => 'nullable|string',
        ];
    }
}
