<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Auth\GrafologRegistrationController;
use App\Http\Controllers\Controller;
use App\Mail\GrafologVerificationMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Gated 'role:administrator'. Verifikasi pendaftar grafolog mandiri. */
class GrafologVerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['sometimes', 'in:pending,verified,rejected']])['status'] ?? 'pending';

        return response()->json(
            User::where('role', 'grafolog')->where('grafolog_status', $status)
                ->latest()
                ->get()
                ->map(fn (User $u) => $u->only([
                    'id', 'name', 'email', 'phone', 'certificate_number', 'grafolog_status',
                    'grafolog_review_note', 'grafolog_verified_at', 'created_at',
                ]) + ['has_certificate' => (bool) $u->certificate_path])
                ->values()
        );
    }

    public function certificate(Request $request, User $user): StreamedResponse
    {
        abort_unless($user->role === 'grafolog' && $user->certificate_path, 404);
        AuditLog::record('lihat_sertifikat_grafolog', User::class, $user->id, $request->user()->id, $request->ip());

        return Storage::disk(GrafologRegistrationController::CERT_DISK)->response($user->certificate_path);
    }

    public function decide(Request $request, User $user, Notifier $notifier): JsonResponse
    {
        abort_unless($user->role === 'grafolog', 404);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);

        $approve = $data['decision'] === 'approve';
        $user->update([
            'grafolog_status' => $approve ? 'verified' : 'rejected',
            'grafolog_verified_at' => $approve ? now() : null,
            'grafolog_verified_by' => $request->user()->id,
            'grafolog_review_note' => $data['note'] ?? null,
        ]);

        AuditLog::record($approve ? 'verifikasi_grafolog' : 'tolak_grafolog', User::class, $user->id, $request->user()->id, $request->ip());
        Mail::to($user->email)->queue(new GrafologVerificationMail($user));
        $notifier->send($user, 'verifikasi', $approve ? 'Akun grafolog diverifikasi' : 'Pendaftaran grafolog belum disetujui',
            $approve ? 'Anda kini bisa mendaftarkan klien dan menerima penugasan.' : ($data['note'] ?? 'Lihat email untuk catatan admin.'), '/status-grafolog', mail: false);

        return response()->json($user->only(['id', 'name', 'email', 'grafolog_status', 'grafolog_review_note', 'grafolog_verified_at']));
    }
}
