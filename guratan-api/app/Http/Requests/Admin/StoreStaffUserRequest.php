<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreStaffUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dipakai Administrator untuk membuat akun staf (administrator/supervisor/
     * grafolog/hr) - BUKAN akun klien ('user'), yang tetap lewat
     * /auth/register publik seperti biasa. Ini mekanisme provisioning yang
     * disepakati: admin pertama dari seeder, sisanya dibuat lewat sini,
     * bukan pendaftaran publik. `company_id` wajib untuk `hr` (MGA Fase 06)
     * DAN `supervisor` (fitur Supervisor, 2026-09-08 - company-scoped persis
     * seperti HR, keputusan produk eksplisit: bukan lintas-perusahaan) -
     * keduanya selalu terikat ke satu company, dibuat lebih dulu lewat
     * POST /api/admin/companies.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', 'string', 'in:administrator,supervisor,grafolog,hr'],
            'company_id' => [
                'nullable',
                in_array($this->input('role'), ['hr', 'supervisor'], true) ? 'required' : 'prohibited',
                'integer', 'exists:companies,id',
            ],
        ];
    }
}
