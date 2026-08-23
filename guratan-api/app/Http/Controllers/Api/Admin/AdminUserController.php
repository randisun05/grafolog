<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Http\Requests\Admin\UpdateStaffUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by the 'role:administrator' middleware on its routes (routes/api.php)
 * - no per-method abort_unless needed here, unlike the older isGrafolog()
 * checks elsewhere in the codebase.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'company_id', 'is_active', 'created_at'])
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function store(StoreStaffUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->validated('role'),
            'company_id' => $request->validated('company_id'),
        ]);

        AuditLog::record('buat_akun_staf', User::class, $user->id, $request->user()->id, $request->ip());

        return response()->json($user->only(['id', 'name', 'email', 'role', 'company_id', 'is_active']), 201);
    }

    /**
     * Sebelum ini akun staf TIDAK BISA diedit/dinonaktifkan sama sekali
     * setelah dibuat (lihat ROADMAP.md "Kesiapan Publikasi") - satu-satunya
     * jalan adalah query DB manual, tidak sustainable untuk operasi nyata
     * (staf resign, salah ketik email, butuh reset password, dst).
     *
     * is_active BUKAN hard-delete - riwayat created_by/AuditLog/Assignment
     * yang mengacu ke user ini tetap valid. Menonaktifkan langsung mencabut
     * SEMUA token Sanctum aktif (bukan cuma memblokir login berikutnya) -
     * sesi yang sedang berjalan pun langsung putus di request berikutnya.
     */
    public function update(UpdateStaffUserRequest $request, User $user): JsonResponse
    {
        // Endpoint ini khusus akun staf (lihat StoreStaffUserRequest) - akun
        // klien ('user') tetap dikelola lewat alurnya sendiri, tidak lewat sini.
        abort_if($user->role === 'user', 404);

        abort_if(
            $user->id === $request->user()->id && ! $request->boolean('is_active'),
            422,
            'Tidak bisa menonaktifkan akun sendiri.'
        );

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            'company_id' => $request->validated('company_id'),
            'is_active' => $request->validated('is_active'),
        ]);

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        AuditLog::record('ubah_akun_staf', User::class, $user->id, $request->user()->id, $request->ip());

        return response()->json($user->only(['id', 'name', 'email', 'role', 'company_id', 'is_active']));
    }
}
