<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Aspek;
use App\Models\CandidateProfile;
use App\Models\JobPosition;
use App\Models\Order;
use App\Models\PersonalityReport;
use App\Models\ServiceDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated 'role:hr'. Analisis kandidat (Fase C, 2026-10-17): menyandingkan skor 40 aspek dari
 * laporan grafologi yang SUDAH DISETUJUI dengan skor layanan wawancara, per posisi. Tidak ada
 * "skor kecocokan" gabungan - aspek kunci posisi hanya menentukan kolom yang ditonjolkan.
 * Prinsip produk: insight reflektif, bukan alat keputusan tunggal rekrutmen.
 */
class CandidateAnalysisController extends Controller
{
    public const DISCLAIMER = 'Analisis ini adalah bahan diskusi reflektif untuk tim rekrutmen, bukan alat keputusan '
        .'tunggal. Gunakan bersama wawancara, rekam jejak, dan penilaian kompetensi lain.';

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $positionId = $request->validate(['position_id' => ['sometimes', 'nullable', 'integer']])['position_id'] ?? null;

        $position = $positionId ? JobPosition::where('company_id', $companyId)->findOrFail($positionId) : null;
        $keyIds = collect($position?->key_aspek_ids ?? [])->map(fn ($id) => (int) $id);

        $profiles = CandidateProfile::where('company_id', $companyId)
            ->when($position, fn ($q) => $q->where('job_position_id', $position->id))
            ->with(['user:id,name', 'position:id,title'])
            ->get();
        $userIds = $profiles->pluck('user_id');

        $orders = Order::whereIn('user_id', $userIds)
            ->whereHas('batch', fn ($q) => $q->where('company_id', $companyId))
            ->latest('id')->get();

        // Laporan disetujui terbaru per kandidat (dari sampel pesanan B2B perusahaan ini).
        $reports = PersonalityReport::whereIn('sample_id', $orders->pluck('sample_id')->filter())
            ->where('review_status', PersonalityReport::REVIEW_APPROVED)
            ->with(['aspekScores:id,report_id,aspek_id,skor', 'sample:id,user_id'])
            ->latest('id')->get()
            ->unique(fn ($r) => $r->sample->user_id)
            ->keyBy(fn ($r) => $r->sample->user_id);

        $interviews = ServiceDelivery::whereIn('order_id', $orders->pluck('id'))
            ->where('status', 'selesai')
            ->whereHas('component', fn ($q) => $q->where('tipe', 'wawancara'))
            ->with('component:id,tipe,report_fields')
            ->latest('report_submitted_at')->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $interviewFields = $interviews->flatMap(fn ($d) => $d->component->report_fields ?? [])
            ->unique('key')->values();

        return response()->json([
            'position' => $position,
            'aspek' => Aspek::with('sindrom:id,kode_romawi,nama,nama_id')->orderBy('id')
                ->get(['id', 'kode', 'sindrom_id', 'nama', 'nama_id'])
                ->map(fn (Aspek $a) => $a->toArray() + ['is_key' => $keyIds->contains($a->id)]),
            'interview_fields' => $interviewFields,
            'candidates' => $profiles->map(function (CandidateProfile $p) use ($reports, $interviews) {
                $report = $reports[$p->user_id] ?? null;
                $interview = $interviews[$p->user_id] ?? null;

                return [
                    'id' => $p->id,
                    'user_id' => $p->user_id,
                    'name' => $p->user->name,
                    'position' => $p->position?->title,
                    'stage' => $p->stage,
                    'education' => $p->education,
                    'experience_years' => $p->experience_years,
                    'report_id' => $report?->id,
                    'scores' => $report?->aspekScores->pluck('skor', 'aspek_id') ?? (object) [],
                    'interview' => $interview?->report,
                ];
            })->values(),
            'disclaimer' => self::DISCLAIMER,
        ]);
    }
}
