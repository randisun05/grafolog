<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `password` opsional, sengaja TANPA `confirmed` (form-nya cuma 1 field
     * password, bukan alur registrasi mandiri) - kalau kosong,
     * UserLookupController::store() membuatkan password acak yang
     * ditampilkan sekali ke grafolog untuk disampaikan langsung ke klien
     * (lihat catatan di controller kenapa bukan email undangan).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            // Password tidak lagi diterima: klien mengatur sendiri lewat undangan email.
            'password' => ['prohibited'],
            'consent_confirmed' => ['accepted'],
        ];
    }
}
