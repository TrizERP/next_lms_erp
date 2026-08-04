<?php

namespace App\Http\Requests\Mcp;

use Illuminate\Foundation\Http\FormRequest;

class InitializeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client' => ['nullable', 'array'],
            'client.name' => ['nullable', 'string', 'max:255'],
            'client.version' => ['nullable', 'string', 'max:50'],
            'capabilities' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
            'meta.institute_id' => ['nullable', 'integer'],
            'meta.academic_year' => ['nullable', 'integer'],
            'meta.term_id' => ['nullable', 'integer'],
        ];
    }
}
