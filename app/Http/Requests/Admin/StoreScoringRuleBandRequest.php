<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreScoringRuleBandRequest extends FormRequest
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
            'polaritas' => ['required', 'string', 'in:HIJAU,MERAH'],
            'label' => ['required', 'string', 'max:30'],
            'rentang_skor' => ['required', 'string', 'max:10'],
        ];
    }
}
