<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Services\Payment\PaymentMethods;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'code' => ['required', 'string', 'max:32', 'unique:discount_codes,code'],
            'type' => ['required', 'string', 'in:percentage,fixed'],
            'value' => [
                'required', 'integer', 'min:1',
                $this->input('type') === 'percentage' ? 'max:100' : 'max:1000000000',
            ],
            'applicable_tiers' => ['nullable', 'array'],
            // Berisi kode produk (Kelola Produk) dan/atau 'token' (pembelian token grafolog).
            'applicable_tiers.*' => ['string', function (string $attr, $value, \Closure $fail) {
                if ($value !== 'token' && ! Product::where('kode', $value)->exists()) {
                    $fail("Produk '{$value}' tidak ditemukan.");
                }
            }],
            'audiences' => ['nullable', 'array'],
            'audiences.*' => ['string', 'in:client,grafolog,b2b'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'in:'.implode(',', PaymentMethods::keys())],
            'is_public' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }
    }
}
