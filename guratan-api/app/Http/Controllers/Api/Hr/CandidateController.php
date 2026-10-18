<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreCandidateRequest;
use App\Models\AuditLog;
use App\Models\CandidateProfile;
use App\Models\Order;
use App\Services\B2b\CandidateRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gated 'role:hr'. Kandidat perusahaan + status asesmen terakhirnya (pesanan B2B, grafolog,
 * jumlah scan, laporan, layanan pendukung).
 */
class CandidateController extends Controller
{
    public function __construct(private CandidateRegistrar $registrar) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $filters = $request->validate([
            'position_id' => ['sometimes', 'nullable', 'integer'],
            'stage' => ['sometimes', 'nullable', 'in:'.implode(',', CandidateProfile::STAGES)],
        ]);

        $profiles = CandidateProfile::where('company_id', $companyId)
            ->when($filters['position_id'] ?? null, fn ($q, $id) => $q->where('job_position_id', $id))
            ->when($filters['stage'] ?? null, fn ($q, $s) => $q->where('stage', $s))
            ->with(['user:id,name,email,phone,must_change_password,acquisition_source', 'position:id,title'])
            ->latest()
            ->get();

        $orders = Order::whereIn('user_id', $profiles->pluck('user_id'))
            ->whereHas('batch', fn ($q) => $q->where('company_id', $companyId))
            ->with([
                'product:id,kode,nama', 'grafolog:id,name',
                'sample' => fn ($q) => $q->withCount('images')->with(['reports' => fn ($r) => $r->select('id', 'sample_id', 'review_status')]),
                'deliveries.component:id,tipe,nama',
            ])
            ->latest('id')
            ->get()
            ->groupBy('user_id');

        return response()->json($profiles->map(fn (CandidateProfile $p) => $p->toArray() + [
            'orders' => ($orders[$p->user_id] ?? collect())->map(fn (Order $o) => [
                'id' => $o->id,
                'batch_id' => $o->batch_id,
                'status' => $o->status,
                'product' => $o->product?->nama,
                'grafolog' => $o->grafolog?->name,
                'grafolog_id' => $o->grafolog?->id,
                'sample_id' => $o->sample_id,
                'sample_status' => $o->sample?->status,
                'image_count' => $o->sample?->images_count ?? 0,
                'report_id' => $o->sample?->reports->firstWhere('review_status', 'disetujui')?->id,
                'deliveries' => $o->deliveries->map(fn ($d) => [
                    'id' => $d->id, 'tipe' => $d->component?->tipe, 'nama' => $d->component?->nama, 'status' => $d->status,
                ]),
            ])->values(),
        ]));
    }

    public function store(StoreCandidateRequest $request): JsonResponse
    {
        $hr = $request->user();
        abort_unless($hr->company_id, 422, 'Akun HR ini belum terhubung ke company.');
        $data = $request->validated();

        if ($rejection = $this->registrar->rejectionFor($data['email'], $hr)) {
            return response()->json(['message' => $rejection, 'errors' => ['email' => [$rejection]]], 422);
        }

        [$profile, $temp] = DB::transaction(fn () => $this->registrar->register($data, $hr));
        AuditLog::record('tambah_kandidat', CandidateProfile::class, $profile->id, $hr->id, $request->ip());

        return response()->json(
            $profile->load(['user:id,name,email,phone', 'position:id,title'])->toArray() + ['temp_password' => $temp],
            201,
        );
    }

    /**
     * Buat password sementara baru untuk akun kandidat yang dibuat lewat B2B (kandidat lupa / HR
     * menyerahkan ulang). Tidak diemail; semua sesi login kandidat dicabut. Akun klien mandiri
     * (bukan dibuat HR) tidak boleh disentuh HR.
     */
    public function resetPassword(Request $request, CandidateProfile $candidate): JsonResponse
    {
        abort_unless($candidate->company_id === $request->user()->company_id, 404);
        $user = $candidate->user;
        abort_unless($user->acquisition_source === 'b2b', 403, 'Akun ini bukan akun yang dibuat perusahaan - kandidat memakai fitur lupa password sendiri.');

        $temp = CandidateRegistrar::tempPassword();
        $user->update(['password' => $temp, 'must_change_password' => true]);
        $user->tokens()->delete();
        AuditLog::record('reset_password_kandidat', CandidateProfile::class, $candidate->id, $request->user()->id, $request->ip());

        return response()->json(['email' => $user->email, 'temp_password' => $temp]);
    }

    public function update(StoreCandidateRequest $request, CandidateProfile $candidate): JsonResponse
    {
        abort_unless($candidate->company_id === $request->user()->company_id, 404);
        $data = $request->validated();

        DB::transaction(function () use ($candidate, $data) {
            $candidate->update(collect($data)->except('phone')->all());
            // Telepon hanya boleh diubah HR untuk akun yang lahir dari B2B (bukan klien mandiri).
            if (array_key_exists('phone', $data) && $candidate->user->acquisition_source === 'b2b') {
                $candidate->user->update(['phone' => $data['phone']]);
            }
        });
        AuditLog::record('ubah_kandidat', CandidateProfile::class, $candidate->id, $request->user()->id, $request->ip());

        return response()->json($candidate->fresh()->load(['user:id,name,email,phone', 'position:id,title']));
    }
}
