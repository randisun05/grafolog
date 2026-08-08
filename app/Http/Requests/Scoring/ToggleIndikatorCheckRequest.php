<?php

namespace App\Http\Requests\Scoring;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToggleIndikatorCheckRequest extends FormRequest
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
            'indikator_id' => ['required', 'integer', 'exists:indikator,id'],
            'checked' => ['required', 'boolean'],
            'also_uncheck_cascaded' => ['sometimes', 'array'],
            'also_uncheck_cascaded.*' => ['integer', 'exists:indikator,id'],
        ];
    }
}
