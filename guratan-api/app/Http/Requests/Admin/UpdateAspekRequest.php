<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAspekRequest extends FormRequest
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
            'kode' => ['sometimes', 'required', 'string', 'max:10', Rule::unique('aspek', 'kode')->ignore($this->route('aspek'))],
            'sindrom_id' => ['sometimes', 'required', 'integer', 'exists:sindrom,id'],
            'nama' => ['sometimes', 'required', 'string', 'max:150'],
            'keterangan_umum' => ['nullable', 'string'],
            'narasi_very_high' => ['nullable', 'string'],
            'narasi_high' => ['nullable', 'string'],
            'narasi_medium' => ['nullable', 'string'],
            'narasi_low' => ['nullable', 'string'],
            'nama_id' => ['nullable', 'string', 'max:150'],
            'keterangan_umum_id' => ['nullable', 'string'],
            'narasi_very_high_id' => ['nullable', 'string'],
            'narasi_high_id' => ['nullable', 'string'],
            'narasi_medium_id' => ['nullable', 'string'],
            'narasi_low_id' => ['nullable', 'string'],
        ];
    }
}
