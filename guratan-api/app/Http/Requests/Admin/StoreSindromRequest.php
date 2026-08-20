<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSindromRequest extends FormRequest
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
            'kode_romawi' => ['required', 'string', 'max:5', 'unique:sindrom,kode_romawi'],
            'nama' => ['required', 'string', 'max:100'],
            'polaritas_inferred' => ['required', 'string', 'in:HIJAU,MERAH'],
            'catatan_polaritas' => ['nullable', 'string'],
        ];
    }
}
