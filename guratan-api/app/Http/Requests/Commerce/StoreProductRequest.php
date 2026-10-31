<?php

namespace App\Http\Requests\Commerce;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('commission_type') === 'percent' && (int) $this->input('commission_value', 0) > 100) {
                $v->errors()->add('commission_value', 'Komisi persen maksimal 100.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('kode')) {
            $this->merge(['kode' => str((string) $this->input('kode'))->slug()->value()]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $required = $product ? 'sometimes' : 'required';

        return [
            'kode' => [$required, 'string', 'max:50', Rule::notIn(['token']), Rule::unique('products', 'kode')->ignore($product?->id)],
            'nama' => [$required, 'string', 'max:255'],
            'kategori' => ['sometimes', Rule::in(Product::KATEGORI)],
            'ringkasan' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'target' => ['sometimes', Rule::in(Product::TARGET)],
            'requires_handwriting' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'promo_label' => ['nullable', 'string', 'max:60'],
            'token_cost' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'commission_type' => ['sometimes', Rule::in(Product::COMMISSION_TYPES)],
            'commission_value' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'modes' => ['sometimes', 'array', 'min:1'],
            'modes.*' => ['string', Rule::in(Product::MODES)],
            'requirements' => ['sometimes', 'nullable', 'array', 'max:15'],
            'requirements.*.judul' => ['required', 'string', 'max:120'],
            'requirements.*.keterangan' => ['nullable', 'string', 'max:500'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'price' => [$product ? 'prohibited' : 'required', 'integer', 'min:0'],
        ];
    }
}
