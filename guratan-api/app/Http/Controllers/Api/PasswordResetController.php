<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Lupa kata sandi untuk semua role - termasuk klien walk-in & kandidat HR
 * yang akunnya dibuat dengan password acak. Respons forgot() selalu generik
 * supaya endpoint ini tidak bisa dipakai menebak email terdaftar.
 */
class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Jika email tersebut terdaftar, tautan atur ulang kata sandi sudah kami kirim.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                AuditLog::record('reset_kata_sandi', User::class, $user->id, $user->id, $request->ip());
            }
        );

        abort_unless($status === Password::PASSWORD_RESET, 422, 'Tautan atur ulang kata sandi tidak valid atau sudah kedaluwarsa.');

        return response()->json(['message' => 'Kata sandi berhasil diubah. Silakan masuk.']);
    }
}
