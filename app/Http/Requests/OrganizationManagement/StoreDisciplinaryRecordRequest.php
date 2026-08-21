<?php

namespace App\Http\Requests\OrganizationManagement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * G2G's discliplinaryManagementController only ever validated
 * `sub_institute_id`. Real per-field validation is added here per the port
 * instructions (new file, not a behavior change to any existing G2G code path).
 */
class StoreDisciplinaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id'      => 'required|integer',
            'employee_id'        => 'required|integer',
            'incident_datetime'  => 'required|date',
            'location'           => 'nullable|string|max:191',
            'misconduct_type'    => 'required|in:Late Arrival,Absenteeism,Misbehavior,Violation of Policy,Others',
            'description'        => 'nullable|string',
            'witness_id'         => 'nullable|integer',
            'action_taken'       => 'required|in:Warning,Suspension,Termination,Counseling,Others',
            'remarks'            => 'nullable|string',
            'reported_by'        => 'required|integer',
            'date_of_report'     => 'required|date',
        ];
    }
}
