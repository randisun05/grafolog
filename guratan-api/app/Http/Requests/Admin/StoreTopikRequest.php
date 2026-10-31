<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopikRequest extends FormRequest
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
        $topikId = $this->route('topik')?->id;

        return [
            'nama' => ['required', 'string', 'max:100', 'unique:topik,nama,'.($topikId ?? 'NULL').',id'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
