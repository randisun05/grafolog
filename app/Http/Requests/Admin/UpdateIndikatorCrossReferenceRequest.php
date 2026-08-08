<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIndikatorCrossReferenceRequest extends FormRequest
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
            'indikator_sumber_raw' => ['sometimes', 'required', 'string', 'max:250'],
            'indikator_sumber_id' => ['nullable', 'integer', 'exists:indikator,id'],
            'mereferensikan_ke_kode' => ['sometimes', 'required', 'string', 'max:20'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }
}
