<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'judul' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'nilai_kontrak' => ['nullable', 'numeric', 'min:0'],
            'mulai_at' => ['required', 'date'],
            'berakhir_at' => ['nullable', 'date', 'after_or_equal:mulai_at'],
            'status' => ['required', 'string', 'in:draft,aktif,dihentikan'],
        ];
    }
}
