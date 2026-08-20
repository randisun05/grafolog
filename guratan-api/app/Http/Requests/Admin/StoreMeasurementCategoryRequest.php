<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementCategoryRequest extends FormRequest
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
            'kategori' => ['required', 'string', 'max:50'],
            'rentang' => ['nullable', 'string', 'max:30'],
            'unit' => ['nullable', 'string', 'max:20'],
            'urutan' => ['required', 'integer', 'min:1', 'max:255'],
        ];
    }
}
