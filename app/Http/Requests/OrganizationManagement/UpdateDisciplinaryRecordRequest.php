<?php

namespace App\Http\Requests\OrganizationManagement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisciplinaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id'      => 'sometimes|required|integer',
            'employee_id'        => 'sometimes|required|integer',
            'incident_datetime'  => 'sometimes|required|date',
            'location'           => 'nullable|string|max:191',
            'misconduct_type'    => 'sometimes|required|in:Late Arrival,Absenteeism,Misbehavior,Violation of Policy,Others',
            'description'        => 'nullable|string',
            'witness_id'         => 'nullable|integer',
            'action_taken'       => 'sometimes|required|in:Warning,Suspension,Termination,Counseling,Others',
            'remarks'            => 'nullable|string',
            'reported_by'        => 'sometimes|required|integer',
            'date_of_report'     => 'sometimes|required|date',
        ];
    }
}
