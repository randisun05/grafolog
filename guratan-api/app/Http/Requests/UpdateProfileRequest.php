<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\RegisterCompanyRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Profil per role (Fase C, 2026-10-17): grafolog (dipakai HR memilih grafolog), klien (data
 * diri opsional), HR (profil perusahaan). Field role lain ditolak (prohibited) supaya tidak
 * tersimpan diam-diam.
 */
class UpdateProfileRequest extends FormRequest
{
    public const EDUCATION = ['SMA', 'D3', 'S1', 'S2', 'S3', 'Lainnya'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $role = $this->user()->role;
        $only = fn (string ...$roles) => in_array($role, $roles, true) ? [] : ['prohibited'];

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],

            'email_notifications' => ['sometimes', 'boolean'],
            'bio' => [...$only('grafolog'), 'sometimes', 'nullable', 'string', 'max:2000'],
            'specializations' => [...$only('grafolog'), 'sometimes', 'nullable', 'array', 'max:8'],
            'specializations.*' => ['string', 'max:60'],
            'experience_years' => [...$only('grafolog'), 'sometimes', 'nullable', 'integer', 'min:0', 'max:60'],

            'birth_date' => [...$only('user'), 'sometimes', 'nullable', 'date', 'before:today'],
            'gender' => [...$only('user'), 'sometimes', 'nullable', 'in:L,P'],
            'education' => [...$only('user'), 'sometimes', 'nullable', 'in:'.implode(',', self::EDUCATION)],
            'occupation' => [...$only('user'), 'sometimes', 'nullable', 'string', 'max:100'],

            'company' => [...$only('hr'), 'sometimes', 'array'],
            'company.industry' => ['sometimes', 'string', 'max:100'],
            'company.employee_range' => ['sometimes', 'in:'.implode(',', RegisterCompanyRequest::EMPLOYEE_RANGES)],
            'company.phone' => ['sometimes', 'string', 'max:30'],
            'company.address' => ['sometimes', 'string', 'max:500'],
            'company.website' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }
}
