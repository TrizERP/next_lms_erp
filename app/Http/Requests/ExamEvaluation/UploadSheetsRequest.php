<?php

namespace App\Http\Requests\ExamEvaluation;

/**
 * Only the envelope is validated here. Each file is checked on its own inside
 * ExamEvaluationStorage and reported back by name, because rejecting the whole
 * request for one bad scan would throw away the rest of a class set.
 */
class UploadSheetsRequest extends ExamEvaluationRequest
{
    public function rules(): array
    {
        return [
            'sub_institute_id' => ['required', 'integer', 'min:1'],
            'sheets' => ['sometimes', 'array'],
        ];
    }
}
