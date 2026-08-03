<?php

namespace App\Http\Requests\Scoring;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewScoresRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sama seperti SubmitScoresRequest tapi TANPA syarat ke-40 aspek harus
     * lengkap - endpoint ini untuk pratinjau live saat grafolog masih
     * mengisi form, bukan submit final.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'skor' => ['present', 'array'],
            'skor.*.kode' => ['required', 'string', 'distinct', 'exists:aspek,kode'],
            'skor.*.skor' => ['required', 'integer', 'between:1,10'],
        ];
    }
}
