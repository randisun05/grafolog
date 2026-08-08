<?php

namespace App\Http\Requests\Admin;

use App\Models\MeasurementCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIndikatorRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi lintas-field (mis. "category_label wajib kalau rule_type
     * category, terlarang kalau comparison") ditaruh di withValidator(),
     * bukan dipaksakan lewat kombinasi required_if/prohibited_if di sini -
     * lebih mudah dibaca untuk 2 tipe aturan yang field-nya saling eksklusif.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rule_type' => ['required', 'string', 'in:category,comparison'],
            'variable_a_id' => ['required', 'integer', 'exists:measurement_variable,id'],
            'category_label' => ['nullable', 'string', 'max:50'],
            'operator' => ['nullable', 'string', 'in:equals,greater_than,less_than,greater_or_equal,less_or_equal'],
            'koefisien' => ['nullable', 'numeric', 'min:0'],
            'variable_b_id' => ['nullable', 'integer', 'exists:measurement_variable,id', 'different:variable_a_id'],
            'compare_value' => ['nullable', 'numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $type = $data['rule_type'] ?? null;

            if ($type === 'category') {
                if (empty($data['category_label'])) {
                    $validator->errors()->add('category_label', 'Wajib diisi untuk tipe aturan "category".');
                }
                foreach (['operator', 'variable_b_id', 'compare_value'] as $field) {
                    if (! empty($data[$field])) {
                        $validator->errors()->add($field, 'Tidak boleh diisi untuk tipe aturan "category".');
                    }
                }
                if (! empty($data['category_label']) && ! empty($data['variable_a_id'])) {
                    $exists = MeasurementCategory::where('variable_id', $data['variable_a_id'])
                        ->where('kategori', $data['category_label'])
                        ->exists();
                    if (! $exists) {
                        $validator->errors()->add('category_label', 'Kategori ini tidak ditemukan pada variabel yang dipilih.');
                    }
                }
            } elseif ($type === 'comparison') {
                if (empty($data['operator'])) {
                    $validator->errors()->add('operator', 'Wajib diisi untuk tipe aturan "comparison".');
                }
                if (! empty($data['category_label'])) {
                    $validator->errors()->add('category_label', 'Tidak boleh diisi untuk tipe aturan "comparison".');
                }
                $hasVariableB = ! empty($data['variable_b_id']);
                $hasCompareValue = array_key_exists('compare_value', $data) && $data['compare_value'] !== null && $data['compare_value'] !== '';
                if ($hasVariableB === $hasCompareValue) {
                    $validator->errors()->add('variable_b_id', 'Isi tepat salah satu: variabel B ATAU angka tetap, tidak keduanya/tidak kosong dua-duanya.');
                }
            }
        });
    }
}
