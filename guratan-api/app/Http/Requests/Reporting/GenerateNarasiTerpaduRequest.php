<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateNarasiTerpaduRequest extends FormRequest
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
            'bahasa' => ['required', 'in:id,en'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}
