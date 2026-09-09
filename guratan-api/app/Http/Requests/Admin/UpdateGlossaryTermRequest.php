<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGlossaryTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'istilah' => ['sometimes', 'string', 'max:100'],
            'definisi' => ['sometimes', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
