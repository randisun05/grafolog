<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dipakai untuk sync tag Topik ke Aspek maupun KombinasiTemuan - bentuk
 * payload-nya sama persis di kedua tempat (array id Topik), tidak perlu 2
 * request class terpisah.
 */
class SyncTopikRequest extends FormRequest
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
            'topik_ids' => ['present', 'array'],
            'topik_ids.*' => ['integer', 'exists:topik,id'],
        ];
    }
}
