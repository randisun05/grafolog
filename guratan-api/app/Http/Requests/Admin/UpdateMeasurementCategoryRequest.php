<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeasurementCategoryRequest extends FormRequest
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
            'kategori' => ['sometimes', 'required', 'string', 'max:50'],
            'rentang' => ['nullable', 'string', 'max:30'],
            'unit' => ['nullable', 'string', 'max:20'],
            'urutan' => ['sometimes', 'required', 'integer', 'min:1', 'max:255'],
        ];
    }
}
