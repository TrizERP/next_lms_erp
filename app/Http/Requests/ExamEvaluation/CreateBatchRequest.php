<?php

namespace App\Http\Requests\ExamEvaluation;

class CreateBatchRequest extends ExamEvaluationRequest
{
    public function rules(): array
    {
        return [
            'sub_institute_id' => ['required', 'integer', 'min:1'],
            'question_paper_id' => ['required', 'integer', 'min:1'],
            'syear' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_paper_id.required' => 'Choose the exam this evaluation is for.',
            'question_paper_id.min' => 'Choose the exam this evaluation is for.',
        ];
    }
}
