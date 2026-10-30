<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'discount_code' => ['nullable', 'string', 'max:32'],
            'delivery_mode' => ['nullable', 'in:online,offline'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:today'],
            'upload_deadline' => ['nullable', 'date', 'after_or_equal:today', ...($this->filled('scheduled_for') ? ['before_or_equal:scheduled_for'] : [])],
            'location_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
