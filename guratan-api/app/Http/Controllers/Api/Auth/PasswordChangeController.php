<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Ganti password sendiri; wajib bagi akun dengan password sementara (kandidat B2B). */
class PasswordChangeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Kata sandi saat ini salah.']]);
        }

        $user->update(['password' => $data['password'], 'must_change_password' => false]);
        // Cabut sesi lain (mis. HR memegang password sementara), pertahankan sesi ini.
        $current = $user->currentAccessToken();
        $others = $user->tokens();
        if (isset($current->id)) {
            $others->where('id', '!=', $current->id);
        }
        $others->delete();
        AuditLog::record('ganti_password', User::class, $user->id, $user->id, $request->ip());

        return response()->json(['user' => $user->fresh()]);
    }
}
