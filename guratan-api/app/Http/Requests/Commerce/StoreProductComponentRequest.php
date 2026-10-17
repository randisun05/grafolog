<?php

namespace App\Http\Requests\Commerce;

use App\Models\ProductComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductComponentRequest extends FormRequest
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
        $required = $this->route('component') ? 'sometimes' : 'required';

        return [
            'tipe' => [$required, Rule::in(ProductComponent::TIPE)],
            'nama' => [$required, 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'report_fields' => ['sometimes', 'nullable', 'array', 'max:30'],
            'report_fields.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'report_fields.*.label' => ['required', 'string', 'max:255'],
            'report_fields.*.type' => ['required', Rule::in(['textarea', 'score', 'choice'])],
            'report_fields.*.options' => ['required_if:report_fields.*.type,choice', 'array', 'min:2'],
            'report_fields.*.options.*' => ['string', 'max:100'],
        ];
    }
}
