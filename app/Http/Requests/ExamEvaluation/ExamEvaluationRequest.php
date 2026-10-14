<?php

namespace App\Http\Requests\ExamEvaluation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for the exam-evaluation requests.
 *
 * Authentication, school scoping and the staff-only rule are already enforced
 * for these URLs by RequireApiJwt (config/api_guard.php), and ownership of the
 * batch or sheet is checked on the row in the controller, so `authorize()` has
 * nothing left to decide.
 *
 * A failed validation is returned in the same envelope the controller's own
 * failure() uses -- `status_code`, `message`, `errors` -- so the frontend shows
 * the message the same way whichever layer rejected the request.
 */
abstract class ExamEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        throw new HttpResponseException(response()->json([
            'status_code' => 0,
            'message' => (string) ($errors->first() ?: 'The request was not valid.'),
            'errors' => $errors->all(),
        ], 422));
    }
}
