<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Edit akun staf yang sudah ada (administrator/supervisor/grafolog/hr) -
     * password/company_id opsional (`sometimes`), field lain wajib supaya
     * tidak ada state "setengah terisi" akibat request parsial. Aturan
     * role/company_id sama dengan StoreStaffUserRequest.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', 'string', 'in:administrator,supervisor,grafolog,hr'],
            'company_id' => [
                'nullable',
                $this->input('role') === 'hr' ? 'required' : 'prohibited',
                'integer', 'exists:companies,id',
            ],
            'is_active' => ['required', 'boolean'],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
