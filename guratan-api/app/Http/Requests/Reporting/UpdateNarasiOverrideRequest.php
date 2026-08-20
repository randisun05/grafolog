<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNarasiOverrideRequest extends FormRequest
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
            'narasi' => ['required', 'string', 'max:5000'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
