<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectGrafologApplicationRequest;
use App\Models\AuditLog;
use App\Models\GrafologApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gated by 'role:administrator' middleware di routes/api.php - lihat
 * migrasi `create_grafolog_applications_table` untuk konteks alur penuh.
 */
class GrafologApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $applications = GrafologApplication::query()
            ->with('reviewer:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20);

        return response()->json($applications);
    }

    /**
     * Streaming lewat disk private ('local', bukan 'public') - sama pola
     * dengan ReportController::pdf(), bukan URL publik langsung. Bukti
     * profesi calon grafolog termasuk data pribadi sensitif juga.
     */
    public function document(GrafologApplication $grafologApplication): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($grafologApplication->document_path), 404);

        return Storage::disk('local')->download(
            $grafologApplication->document_path,
            $grafologApplication->document_original_name,
        );
    }

    /**
     * Password sudah HASHED di GrafologApplication (cast 'hashed') -
     * dipakai apa adanya di sini, Laravel's hashed cast mendeteksi string
     * yang sudah ter-hash lewat Hash::isHashed() dan tidak hash ulang.
     */
    public function approve(Request $request, GrafologApplication $grafologApplication): JsonResponse
    {
        abort_if($grafologApplication->status !== 'pending', 422, 'Pengajuan ini sudah diproses sebelumnya.');
        abort_if(
            User::where('email', $grafologApplication->email)->exists(),
            422,
            'Email ini sudah terdaftar sebagai akun - tidak bisa disetujui otomatis.'
        );

        $user = DB::transaction(function () use ($grafologApplication, $request) {
            $user = User::create([
                'name' => $grafologApplication->name,
                'email' => $grafologApplication->email,
                'password' => $grafologApplication->password,
                'role' => 'grafolog',
                'is_active' => true,
            ]);

            $grafologApplication->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return $user;
        });

        AuditLog::record('setujui_akun_grafolog', User::class, $user->id, $request->user()->id, $request->ip());

        return response()->json($user->only(['id', 'name', 'email', 'role', 'is_active']));
    }

    public function reject(RejectGrafologApplicationRequest $request, GrafologApplication $grafologApplication): JsonResponse
    {
        abort_if($grafologApplication->status !== 'pending', 422, 'Pengajuan ini sudah diproses sebelumnya.');

        $grafologApplication->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->validated('review_note'),
        ]);

        AuditLog::record(
            'tolak_akun_grafolog',
            GrafologApplication::class,
            $grafologApplication->id,
            $request->user()->id,
            $request->ip(),
        );

        return response()->json($grafologApplication);
    }
}
