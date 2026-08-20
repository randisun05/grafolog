<?php

namespace App\Http\Requests\Scoring;

use App\Models\Aspek;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitScoresRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'skor' => ['required', 'array'],
            'skor.*.kode' => ['required', 'string', 'distinct', 'exists:aspek,kode'],
            'skor.*.skor' => ['required', 'integer', 'between:1,10'],
            'skor.*.catatan_grafolog' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Form grafolog wajib mengisi ke-40 aspek sekaligus, bukan sebagian -
     * laporan yang tidak lengkap akan menyesatkan.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $kodeMasuk = collect($this->input('skor', []))->pluck('kode')->filter()->unique();
            $totalAspek = Aspek::count();

            if ($kodeMasuk->count() !== $totalAspek) {
                $validator->errors()->add(
                    'skor',
                    "Form harus diisi untuk semua $totalAspek aspek, diterima {$kodeMasuk->count()}."
                );
            }
        });
    }
}
