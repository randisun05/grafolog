<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewReportRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:approve,request_revision'],
            'note' => ['nullable', 'required_if:decision,request_revision', 'string', 'max:2000'],
        ];
    }
}
