<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\UpdateNarasiOverrideRequest;
use App\Models\AuditLog;
use App\Models\PersonalityReport;
use App\Models\ReportRevision;
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
            ->with('sample:id,user_id,created_by,tier')
            ->latest()
            ->paginate(20);

        return response()->json($reports);
    }

    public function show(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeAccess($request, $report);

        return response()->json($report->load('sample', 'aspekScores.aspek'));
    }

    public function pdf(Request $request, PersonalityReport $report): StreamedResponse
    {
        $this->authorizeAccess($request, $report);
        abort_unless($report->status === 'completed', 422, 'Laporan belum selesai dibuat.');

        $path = $this->pdfService->generate($report);

        return Storage::disk('local')->download($path, "laporan-{$report->id}.pdf");
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

        $report = DB::transaction(function () use ($report, $kode, $request, $user) {
            $this->reportRevisions->snapshotBeforeChange($report, 'edit_manual', $user, $request->input('catatan'));

            $data = $report->data;
            $found = $this->applyNarasiOverride($data, $kode, $request->validated('narasi'));
            abort_unless($found, 404, "Aspek dengan kode '{$kode}' tidak ditemukan di laporan ini.");

            $report->update(['data' => $data]);

            AuditLog::record('edit_narasi_laporan', PersonalityReport::class, $report->id, $user->id, $request->ip());

            return $report;
        });

        return response()->json($report->fresh());
    }

    /**
     * @return bool true kalau Aspek dengan $kode ditemukan & diubah
     */
    private function applyNarasiOverride(array &$data, string $kode, string $narasi): bool
    {
        foreach ($data['sindrom'] as &$sindrom) {
            foreach ($sindrom['aspek'] as &$aspek) {
                if ($aspek['kode'] === $kode) {
                    $aspek['narasi'] = $narasi;
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
        abort_unless($report->sample->isViewableBy($request->user()), 403);
    }
}
