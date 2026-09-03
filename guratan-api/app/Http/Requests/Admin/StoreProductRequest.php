<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `code` berbentuk slug (huruf kecil/angka/strip/underscore) karena
     * dipakai apa adanya sebagai nilai `tier` di handwriting_samples/
     * personality_reports/pricing_plans/token_costs - bukan sekadar label
     * tampilan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', 'unique:products,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
