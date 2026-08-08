<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSindromRequest extends FormRequest
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
            'kode_romawi' => ['sometimes', 'required', 'string', 'max:5', Rule::unique('sindrom', 'kode_romawi')->ignore($this->route('sindrom'))],
            'nama' => ['sometimes', 'required', 'string', 'max:100'],
            'polaritas_inferred' => ['sometimes', 'required', 'string', 'in:HIJAU,MERAH'],
            'catatan_polaritas' => ['nullable', 'string'],
        ];
    }
}
