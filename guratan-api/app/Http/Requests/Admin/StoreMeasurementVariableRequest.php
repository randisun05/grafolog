<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementVariableRequest extends FormRequest
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
            'kode' => ['required', 'string', 'max:30', 'unique:measurement_variable,kode'],
            'axis' => ['required', 'string', 'in:vertical,horizontal'],
            'nama' => ['required', 'string', 'max:150'],
            'metodologi_id' => ['nullable', 'integer', 'exists:metodologi_penilaian,id'],
        ];
    }
}
