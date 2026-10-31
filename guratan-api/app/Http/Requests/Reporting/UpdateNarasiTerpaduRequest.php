<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNarasiTerpaduRequest extends FormRequest
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
            'narasi_terpadu' => ['required', 'string', 'max:20000'],
            'bahasa' => ['required', 'in:id,en'],
            'status' => ['required', 'in:draft,final'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
