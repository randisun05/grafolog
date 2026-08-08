<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIndikatorCrossReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `match_status` sengaja tidak divalidasi di sini - itu dihitung server
     * (lihat IndikatorCrossReferenceController), bukan input admin, supaya
     * tidak mungkin ada baris yang mengklaim "matched" padahal datanya
     * sendiri tidak benar-benar cocok.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'indikator_sumber_raw' => ['required', 'string', 'max:250'],
            'indikator_sumber_id' => ['nullable', 'integer', 'exists:indikator,id'],
            'mereferensikan_ke_kode' => ['required', 'string', 'max:20'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }
}
