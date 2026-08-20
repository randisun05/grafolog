<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAspekRequest extends FormRequest
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
            'kode' => ['required', 'string', 'max:10', 'unique:aspek,kode'],
            'sindrom_id' => ['required', 'integer', 'exists:sindrom,id'],
            'nama' => ['required', 'string', 'max:150'],
            'keterangan_umum' => ['nullable', 'string'],
            'narasi_very_high' => ['nullable', 'string'],
            'narasi_high' => ['nullable', 'string'],
            'narasi_medium' => ['nullable', 'string'],
            'narasi_low' => ['nullable', 'string'],
        ];
    }
}
