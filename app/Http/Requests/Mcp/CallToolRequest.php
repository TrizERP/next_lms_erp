<?php

namespace App\Http\Requests\Mcp;

use Illuminate\Foundation\Http\FormRequest;

class CallToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tool' => ['required', 'string', 'max:150'],
            'arguments' => ['nullable', 'array'],
            'confirmation_token' => ['nullable', 'string', 'uuid'],
            'meta' => ['nullable', 'array'],
            'meta.institute_id' => ['nullable', 'integer'],
            'meta.academic_year' => ['nullable', 'integer'],
            'meta.term_id' => ['nullable', 'integer'],
        ];
    }
}
