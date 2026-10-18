<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterGrafologRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran mandiri grafolog (2026-10-17). Akun dibuat berstatus `pending` - bisa login
 * untuk melihat status, tetapi semua aksi grafolog ditolak (User::isGrafolog()) sampai admin
 * memverifikasi sertifikatnya. Ini yang menutup celah registrasi grafolog publik sebelumnya
 * (grafolog palsu bisa lookup klien mana pun) tanpa menutup jalur pendaftaran.
 */
class GrafologRegistrationController extends Controller
{
    public const CERT_DISK = 'local';

    public function store(RegisterGrafologRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'password' => $request->validated('password'),
                'role' => 'grafolog',
                'grafolog_status' => 'pending',
                'certificate_number' => $request->validated('certificate_number'),
                'consented_at' => now(),
                'consent_source' => 'self',
                'consent_version' => User::CONSENT_VERSION,
            ]);
            $user->update([
                'certificate_path' => $request->file('certificate')->store("grafolog-certificates/{$user->id}", self::CERT_DISK),
            ]);

            return $user;
        });

        AuditLog::record('daftar_grafolog', User::class, $user->id, $user->id, $request->ip());
        app(Notifier::class)->toAdmins('verifikasi_baru', 'Pendaftar grafolog baru', "{$user->name} mendaftar sebagai grafolog dan menunggu verifikasi sertifikat.", '/admin/verifikasi?tab=grafolog');

        return response()->json([
            'user' => $user->fresh(),
            'token' => $user->createToken('guratan-web')->plainTextToken,
        ], 201);
    }
}
