<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserLookupController extends Controller
{
    /**
     * Cari 1 klien (role=user) berdasarkan email PERSIS - dipakai grafolog
     * untuk memilih pemilik sample baru. Sengaja bukan endpoint listing/pencarian
     * bebas supaya tidak jadi direktori email semua pengguna (data sensitif).
     */
    public function byEmail(Request $request): JsonResponse
    {
        abort_unless($request->user()->isGrafolog(), 403);

        $request->validate(['email' => ['required', 'email']]);

        $client = User::where('email', $request->query('email'))
            ->where('role', 'user')
            ->first(['id', 'name', 'email']);

        abort_unless($client, 404, 'Klien tidak ditemukan.');

        return response()->json($client);
    }

    /**
     * Daftarkan klien baru langsung dari Portal Grafolog (2026-08-07) - untuk
     * klien walk-in yang belum pernah /auth/register sendiri. Grafolog TIDAK
     * bisa membuat akun untuk role lain (staf tetap lewat Admin\AdminUserController,
     * lihat catatannya) - endpoint ini hanya pernah membuat role 'user'.
     *
     * `password` boleh dikosongkan - kalau begitu, sistem generate password
     * acak dan mengembalikannya SEKALI di response (`generated_password`)
     * supaya grafolog bisa sampaikan langsung ke klien secara lisan/tertulis.
     * Ini BUKAN dikirim lewat email undangan seperti pola SaaS umumnya,
     * karena MAIL_MAILER proyek ini masih `log` (belum tersambung SMTP
     * sungguhan) - jalur email baru masuk akal setelah itu beres.
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        abort_unless($request->user()->isGrafolog(), 403);

        $password = $request->validated('password');
        $wasGenerated = ! $password;
        $password ??= Str::password(10);

        $client = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $password,
            'role' => 'user',
        ]);

        AuditLog::record('daftarkan_klien', User::class, $client->id, $request->user()->id, $request->ip());

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'generated_password' => $wasGenerated ? $password : null,
        ], 201);
    }

    /**
     * Daftar semua grafolog - dipakai HR/Administrator untuk memilih siapa
     * yang di-assign ke sebuah sample (AssignmentController). Gated lewat
     * middleware 'role:hr,administrator' di routes/api.php, bukan inline
     * abort_unless seperti byEmail() di atas - pola baru sejak MGA Fase 05.
     */
    public function grafologs(): JsonResponse
    {
        return response()->json(User::where('role', 'grafolog')->get(['id', 'name', 'email']));
    }
}
