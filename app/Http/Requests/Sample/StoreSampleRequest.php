<?php

namespace App\Http\Requests\Sample;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSampleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isGrafolog = $this->user()->isGrafolog();

        return [
            'tier' => ['required', 'string', 'in:comprehensive,master'],
            'client_user_id' => [$isGrafolog ? 'required' : 'prohibited', 'integer', 'exists:users,id'],
            'image' => ['prohibited'],
        ];
    }
}
