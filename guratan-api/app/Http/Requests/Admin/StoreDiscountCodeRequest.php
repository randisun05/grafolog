<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // 'token' tetap literal di samping daftar produk aktif yang
            // dinamis - itu pseudo-tier untuk pembelian token grafolog,
            // BUKAN baris di tabel products (lihat guratan-api/CLAUDE.md
            // "Sistem Products data-driven").
            'applicable_tiers.*' => ['string', Rule::in([...Product::activeCodes(), 'token'])],
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
