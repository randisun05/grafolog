<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeasurementVariableRequest extends FormRequest
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
            'kode' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('measurement_variable', 'kode')->ignore($this->route('measurementVariable'))],
            'axis' => ['sometimes', 'required', 'string', 'in:vertical,horizontal'],
            'nama' => ['sometimes', 'required', 'string', 'max:150'],
            'metodologi_id' => ['sometimes', 'nullable', 'integer', 'exists:metodologi_penilaian,id'],
        ];
    }
}
