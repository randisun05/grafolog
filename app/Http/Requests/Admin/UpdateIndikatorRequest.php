<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIndikatorRequest extends FormRequest
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
            'kode' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('indikator', 'kode')->ignore($this->route('indikator'))],
            'aspek_id' => ['sometimes', 'required', 'integer', 'exists:aspek,id'],
            'posisi' => ['sometimes', 'required', 'integer', 'between:1,10'],
            'varian' => ['nullable', 'string', 'max:5'],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
