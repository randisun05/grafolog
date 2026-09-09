<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGlossaryTermRequest extends FormRequest
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
            'istilah' => ['required', 'string', 'max:100'],
            'definisi' => ['required', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
