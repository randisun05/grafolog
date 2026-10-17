<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreB2bOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'product' => ['required', 'string', 'exists:products,kode'],
            'candidate_ids' => ['required', 'array', 'min:1', 'max:200'],
            'candidate_ids.*' => ['integer', 'distinct'],
            'grafolog_id' => ['required', 'integer'],
            'discount_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
