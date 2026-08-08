<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIndikatorRequest extends FormRequest
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
            'kode' => ['required', 'string', 'max:20', 'unique:indikator,kode'],
            'aspek_id' => ['required', 'integer', 'exists:aspek,id'],
            'posisi' => ['required', 'integer', 'between:1,10'],
            'varian' => ['nullable', 'string', 'max:5'],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
