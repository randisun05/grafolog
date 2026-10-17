<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'key_aspek_ids' => ['sometimes', 'nullable', 'array', 'max:10'],
            'key_aspek_ids.*' => ['integer', 'distinct', 'exists:aspek,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
