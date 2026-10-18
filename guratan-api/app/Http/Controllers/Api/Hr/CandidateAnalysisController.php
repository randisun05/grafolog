<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Aspek;
use App\Models\AuditLog;
use App\Models\CandidateAnalysis;
use App\Models\CandidateProfile;
use App\Models\JobPosition;
use App\Models\Order;
use App\Models\PersonalityReport;
use App\Models\ServiceDelivery;
use App\Services\B2b\CandidateAiAnalysisService;
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

    public function __construct(private CandidateAiAnalysisService $ai) {}

    /** Analisis AI tersimpan untuk satu kandidat (terbaru), atau null. */
    public function showAi(Request $request, CandidateProfile $candidate): JsonResponse
    {
        abort_unless($candidate->company_id === $request->user()->company_id, 404);

        return response()->json(CandidateAnalysis::where('candidate_profile_id', $candidate->id)->latest('id')->first());
    }

    /**
     * Buat analisis gabungan AI (sekali per data berbeda - hasil sama dipakai ulang). Hanya HR
     * perusahaan pemilik kandidat; butuh laporan grafologi yang sudah disetujui.
     */
    public function runAi(Request $request, CandidateProfile $candidate): JsonResponse
    {
        abort_unless($candidate->company_id === $request->user()->company_id, 404);
        $result = $this->ai->analyze($candidate->load('position'), $request->user());
        abort_if($result === null, 422, 'Belum ada laporan grafologi yang disetujui untuk kandidat ini.');
        AuditLog::record('analisis_ai_kandidat', CandidateProfile::class, $candidate->id, $request->user()->id, $request->ip());

        return response()->json(
            $result['analysis']->toArray() + ['cached' => $result['cached'], 'disclaimer' => self::DISCLAIMER],
            $result['cached'] ? 200 : 201,
        );
    }

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

        $analyses = CandidateAnalysis::whereIn('candidate_profile_id', $profiles->pluck('id'))
            ->latest('id')->get()->unique('candidate_profile_id')->keyBy('candidate_profile_id');

        return response()->json([
            'position' => $position,
            'aspek' => Aspek::with('sindrom:id,kode_romawi,nama,nama_id')->orderBy('id')
                ->get(['id', 'kode', 'sindrom_id', 'nama', 'nama_id'])
                ->map(fn (Aspek $a) => $a->toArray() + ['is_key' => $keyIds->contains($a->id)]),
            'interview_fields' => $interviewFields,
            'candidates' => $profiles->map(function (CandidateProfile $p) use ($reports, $interviews, $analyses) {
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
                    'ai' => ($a = $analyses[$p->id] ?? null) ? [
                        'summary' => $a->summary, 'source' => $a->source, 'created_at' => $a->created_at,
                    ] : null,
                ];
            })->values(),
            'disclaimer' => self::DISCLAIMER,
        ]);
    }
}
