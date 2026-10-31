<?php

namespace App\Http\Requests;

use App\Services\Payment\PaymentMethods;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
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
            'discount_code' => ['nullable', 'string', 'max:32'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentMethods::keys())],
            'gateway' => ['nullable', 'in:doku,xendit'],
        ];
    }
}
