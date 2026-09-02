<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `role` cuma boleh `user` - sebelum 2026-09-02 `grafolog` juga
     * diizinkan di sini (self-register langsung, tanpa review), tapi itu
     * digantikan jalur verifikasi data baru: POST /api/grafolog-applications
     * (lihat GrafologApplicationController) - pengajuan masuk status
     * `pending` dan baru jadi akun grafolog sungguhan kalau administrator
     * approve. Jangan tambahkan `grafolog` kembali ke daftar `in:` ini.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['sometimes', 'string', 'in:user'],
        ];
    }
}
