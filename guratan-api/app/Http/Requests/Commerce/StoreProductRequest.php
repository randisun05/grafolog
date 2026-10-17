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
            'sort' => ['sometimes', 'integer', 'min:0'],
            'price' => [$product ? 'prohibited' : 'required', 'integer', 'min:0'],
        ];
    }
}
