<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->validated('role', 'user'),
        ]);

        $token = $user->createToken('guratan-web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi salah.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('guratan-web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    /**
     * Sengaja balas pesan yang SAMA baik email-nya terdaftar maupun tidak -
     * kalau beda, itu jadi cara mengecek email mana yang punya akun (user
     * enumeration). Token disimpan HASHED di `password_reset_tokens`, cuma
     * versi mentahnya yang dikirim lewat email (dipakai user), sama pola
     * dengan password user sendiri.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );

            $resetUrl = rtrim(config('app.frontend_url'), '/')
                .'/reset-password?email='.urlencode($email).'&token='.$token;

            Mail::to($email)->send(new ResetPasswordMail($resetUrl));
        }

        return response()->json(['message' => 'Kalau email terdaftar, tautan reset kata sandi sudah dikirim.']);
    }

    /**
     * Reset berhasil juga mencabut SEMUA token API lama milik user
     * (`tokens()->delete()`) - kalau akun dibajak, ganti password harus
     * memutus akses token yang sudah bocor juga, bukan cuma ganti password
     * lalu token lama tetap valid selamanya (Sanctum tidak expire token
     * secara default, lihat CLAUDE.md).
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        $valid = $row
            && Carbon::parse($row->created_at)->diffInMinutes(now()) <= 60
            && Hash::check($request->validated('token'), $row->token);
        abort_unless($valid, 422, 'Tautan reset tidak valid atau sudah kedaluwarsa.');

        $user = User::where('email', $email)->firstOrFail();
        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'Kata sandi berhasil direset. Silakan login.']);
    }
}
