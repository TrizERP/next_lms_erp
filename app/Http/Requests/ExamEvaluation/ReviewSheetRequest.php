<?php

namespace App\Http\Requests\ExamEvaluation;

/**
 * A teacher's pass over one sheet: who it belongs to, marks and feedback per
 * question, and whether to approve or re-open it.
 *
 * The upper bound on a mark is the question's own maximum, which lives on the
 * answer row, so that check is made in the controller where the rows are loaded.
 * Everything that can be decided from the request alone is decided here.
 */
class ReviewSheetRequest extends ExamEvaluationRequest
{
    public function rules(): array
    {
        return [
            'sub_institute_id' => ['required', 'integer', 'min:1'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'marks' => ['sometimes', 'nullable', 'array'],
            'marks.*.question_no' => ['required', 'integer', 'min:1'],
            'marks.*.teacher_marks' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'marks.*.teacher_feedback' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'approve' => ['sometimes', 'boolean'],
            'unapprove' => ['sometimes', 'boolean'],
            // "This is the right student" -- turns the suggested student into an assignment.
            'confirm_identity' => ['sometimes', 'boolean'],
            // Why a sheet that was already confirmed or marked is being moved to another student.
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'marks.*.teacher_marks.numeric' => 'Marks must be a number.',
            'marks.*.teacher_marks.min' => 'Marks cannot be negative.',
            'marks.*.teacher_feedback.max' => 'Feedback can be up to 2,000 characters per question.',
        ];
    }
}
