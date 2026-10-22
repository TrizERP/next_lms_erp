<?php

namespace App\Http\Requests\ExamEvaluation;

/**
 * Confirming the suggested student on several sheets at once.
 *
 * Which of the named sheets actually qualify is decided per sheet in the
 * controller -- only clean matches on a GR or roll number do -- so this only
 * checks the shape of the request and bounds its size.
 */
class ConfirmIdentitiesRequest extends ExamEvaluationRequest
{
    public function rules(): array
    {
        return [
            'sub_institute_id' => ['required', 'integer', 'min:1'],
            'sheet_ids' => ['required', 'array', 'min:1', 'max:200'],
            'sheet_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'sheet_ids.required' => 'Choose at least one sheet to confirm.',
            'sheet_ids.max' => 'Confirm at most 200 sheets at a time.',
        ];
    }
}
