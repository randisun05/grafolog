<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sengaja TIDAK ada `exists:users,email` - kalau divalidasi di sini,
     * pesan error 422 jadi cara mengecek email mana yang terdaftar
     * (user enumeration). AuthController::forgotPassword() menangani
     * "email tidak ditemukan" secara diam-diam, respons sukses yang sama
     * baik email-nya ada maupun tidak.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }
}
