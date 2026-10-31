<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTriviaQuestionRequest extends FormRequest
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
            'pertanyaan' => ['sometimes', 'string', 'max:1000'],
            'pilihan' => ['sometimes', 'array', 'size:4'],
            'pilihan.*' => ['required', 'string', 'max:255'],
            'jawaban_benar_index' => ['sometimes', 'integer', 'between:0,3'],
            'penjelasan' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
