<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\UpdateNarasiOverrideRequest;
use App\Models\AuditLog;
use App\Models\PersonalityReport;
use App\Models\ReportRevision;
use App\Models\User;
use App\Services\Reporting\ReportLocalizer;
use App\Services\Reporting\ReportPdfService;
use App\Services\Reporting\ReportRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportLocalizer $localizer,
        private ReportPdfService $pdfService,
        private ReportRevisionService $reportRevisions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reports = PersonalityReport::query()
            ->whereHas('sample', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('assignment', fn ($a) => $a->where('grafolog_id', $user->id));
            })
            ->when(! $user->isGrafolog(), fn ($q) => $q->where('review_status', PersonalityReport::REVIEW_APPROVED))
            ->when($request->query('review_status'), fn ($q, $status) => $q->where('review_status', $status))
            ->with('sample:id,user_id,created_by,tier', 'sample.user:id,name,email')
            ->latest()
            ->paginate(20);

        return response()->json($reports);
    }

    public function show(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeAccess($request, $report);

        $bahasa = $this->bahasa($request);
        $payload = $report->load('sample.consultation', 'aspekScores.aspek', 'reviewer:id,name')->toArray();
        $payload['data'] = $report->data ? $this->localizer->localize($report->data, $bahasa) : null;
        // Sinyal UI: apakah pemakai ini grafolog penanganan (boleh edit/terbit/tugaskan supervisi)
        // dan/atau validator yang ditugaskan. Otorisasi sungguhan tetap di endpoint masing-masing.
        $user = $request->user();
        $payload['can_handle'] = $user->isGrafolog() && $report->sample->isScorableBy($user);
        // Grafolog pemeriksa (untuk menampilkan CV-nya ke klien/HR): yang ditugaskan, atau pembuat sampel bila grafolog.
        $payload['examiner_id'] = $report->sample->assignment?->grafolog_id
            ?? (User::whereKey($report->sample->created_by)->where('role', 'grafolog')->value('id'));
        $payload['is_validator'] = $user->isGrafolog() && $report->isValidatedBy($user);

        return response()->json($payload);
    }

    private function bahasa(Request $request): string
    {
        $bahasa = $request->query('lang', 'id');
        abort_unless(in_array($bahasa, ReportLocalizer::BAHASA, true), 422, 'Bahasa tidak didukung (id|en).');

        return $bahasa;
    }

    public function pdf(Request $request, PersonalityReport $report): StreamedResponse
    {
        $this->authorizeAccess($request, $report);
        abort_unless($report->status === 'completed', 422, 'Laporan belum selesai dibuat.');

        $bahasa = $this->bahasa($request);
        $path = $this->pdfService->generate($report, $bahasa);
        $nama = $bahasa === 'en' ? "graphology-report-{$report->id}-en.pdf" : "laporan-grafologi-{$report->id}-id.pdf";

        return Storage::disk('local')->download($path, $nama);
    }

    /**
     * Timpa teks narasi 1 Aspek dalam laporan secara manual - lepas dari
     * knowledge base, dipakai grafolog untuk kasus khusus di luar apa yang
     * bisa digenerate otomatis. Versi laporan SEBELUM edit disimpan dulu ke
     * report_revisions. Field `narasi_diedit_manual: true` ditambahkan ke
     * entri Aspek itu di JSON `data` (bukan kolom terpisah) supaya
     * ReportDocument.vue bisa menandainya - dan supaya kalau nanti laporan
     * ini dikoreksi skornya (ScoringController::correct), regenerasi penuh
     * dari KB otomatis menghapus flag & teks override ini (tidak
     * dipertahankan lintas regenerasi - lihat CLAUDE.md kenapa ini sengaja).
     */
    public function updateNarasi(UpdateNarasiOverrideRequest $request, PersonalityReport $report, string $kode): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat mengedit narasi laporan.');
        abort_unless($report->sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($report->status !== 'completed', 422, 'Laporan belum selesai dibuat.');
        abort_if($report->hasActiveValidation(), 422, 'Laporan sedang dalam proses supervisi - tidak bisa diedit sampai selesai.');

        $report = DB::transaction(function () use ($report, $kode, $request, $user) {
            $this->reportRevisions->snapshotBeforeChange($report, 'edit_manual', $user, $request->input('catatan'));

            $data = $report->data;
            $bahasa = $request->validated('bahasa', 'id');
            $found = $this->applyNarasiOverride($data, $kode, $request->validated('narasi'), $bahasa);
            abort_unless($found, 404, "Aspek dengan kode '{$kode}' tidak ditemukan di laporan ini.");

            $report->update(['data' => $data]);
            $report->sendBackToReview();

            AuditLog::record('edit_narasi_laporan', PersonalityReport::class, $report->id, $user->id, $request->ip());

            return $report;
        });

        $fresh = $report->fresh();
        $payload = $fresh->toArray();
        $payload['data'] = $this->localizer->localize($fresh->data, $request->validated('bahasa', 'id'));

        return response()->json($payload);
    }

    /**
     * @return bool true kalau Aspek dengan $kode ditemukan & diubah
     */
    private function applyNarasiOverride(array &$data, string $kode, string $narasi, string $bahasa): bool
    {
        foreach ($data['sindrom'] as &$sindrom) {
            foreach ($sindrom['aspek'] as &$aspek) {
                if ($aspek['kode'] === $kode) {
                    // Laporan lama (tanpa narasi_en): `narasi` di sana berbahasa Inggris.
                    if (! array_key_exists('narasi_en', $aspek)) {
                        $aspek['narasi_en'] = $aspek['narasi'];
                        $aspek['narasi_id'] = null;
                    }

                    $aspek["narasi_{$bahasa}"] = $narasi;
                    $aspek["narasi_diedit_manual_{$bahasa}"] = true;
                    $aspek['narasi'] = $aspek['narasi_id'] ?? $aspek['narasi_en'];
                    $aspek['narasi_diedit_manual'] = true;

                    return true;
                }
            }
        }

        return false;
    }

    public function revisions(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeAccess($request, $report);

        return response()->json(
            $report->revisions()->with('actor:id,name')->latest()->get(['id', 'jenis', 'catatan', 'actor_user_id', 'created_at'])
        );
    }

    public function showRevision(Request $request, PersonalityReport $report, ReportRevision $revision): JsonResponse
    {
        $this->authorizeAccess($request, $report);
        abort_unless($revision->report_id === $report->id, 404);

        return response()->json($revision->load('actor:id,name'));
    }

    private function authorizeAccess(Request $request, PersonalityReport $report): void
    {
        abort_unless($report->isVisibleTo($request->user()), 403);
    }
}
