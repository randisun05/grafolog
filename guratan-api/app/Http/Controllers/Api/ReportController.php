<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\GenerateNarasiTerpaduRequest;
use App\Http\Requests\Reporting\UpdateNarasiOverrideRequest;
use App\Http\Requests\Reporting\UpdateNarasiTerpaduRequest;
use App\Jobs\GenerateNarasiTerpaduJob;
use App\Models\AuditLog;
use App\Models\PersonalityReport;
use App\Models\ReportRevision;
use App\Models\User;
use App\Services\Reporting\ReportLocalizer;
use App\Services\Reporting\NarasiTerpaduService;
use App\Services\Reporting\ReportPdfService;
use App\Services\Reporting\ReportResumeService;
use App\Services\Reporting\ReportRevisionService;
use App\Services\Reporting\TopikFilterService;
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
        private TopikFilterService $topikFilter,
    ) {}

    /**
     * Kolom dibatasi eksplisit - `data` (breakdown Sindrom/Aspek/Indikator)
     * dan `narasi_terpadu` sengaja TIDAK disertakan di sini untuk siapa pun
     * (frontend RiwayatView.vue cuma pakai id/tier/status), supaya klien
     * tidak bisa mengintip breakdown internal lewat endpoint list meski
     * show() sudah membatasinya - satu titik konsisten, bukan 2 aturan yang
     * bisa drift.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reports = PersonalityReport::query()
            ->select(['id', 'sample_id', 'tier', 'status', 'narasi_status', 'generated_at', 'created_at'])
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

    /** Resume (ringkasan) laporan untuk pembaca yang berhak melihat laporan. */
    public function resume(Request $request, PersonalityReport $report, ReportResumeService $resume): JsonResponse
    {
        $this->authorizeAccess($request, $report);
        abort_unless($report->data, 404, 'Laporan belum siap.');
        $bahasa = $this->bahasa($request);

        return response()->json($resume->build($this->localizer->localize($report->data, $bahasa), $bahasa));
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
        // Narasi terpadu (draf AI yang disunting grafolog): klien hanya melihatnya setelah ditandai final.
        if ($this->isClientViewer($user) && $report->narasi_status !== 'final') {
            $payload['narasi_terpadu'] = null;
            $payload['narasi_bahasa'] = null;
        }

        return response()->json($payload);
    }

    private function bahasa(Request $request): string
    {
        $bahasa = $request->query('lang', 'id');
        abort_unless(in_array($bahasa, ReportLocalizer::BAHASA, true), 422, 'Bahasa tidak didukung (id|en).');

        return $bahasa;
    }

    /**
     * Baca breakdown internal `data`, disaring ke Aspek/Kombinasi Temuan
     * yang ditag salah satu Topik yang diminta - contoh konkret "produk
     * turunan" (mis. B2B minta laporan segmen Karier saja) tanpa mengubah
     * proses generate utama sama sekali (murni `TopikFilterService`
     * membaca ulang `data` yang sudah tersimpan). Staff-only, sama seperti
     * breakdown internal biasa - klien tetap TIDAK PERNAH mengakses ini.
     */
    public function segmen(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeAccess($request, $report);
        abort_if($this->isClientViewer($request->user()), 403, 'Segmen internal tidak tersedia untuk klien.');

        $topikIds = collect($request->input('topik_ids', []))->map(fn ($id) => (int) $id)->all();

        return response()->json($this->topikFilter->filter($report->data ?? [], $topikIds));
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

    /** PDF narasi terpadu (tanpa rincian skor). Klien hanya bila final; grafolog/admin/HR melihat draf juga. */
    public function pdfNarasi(Request $request, PersonalityReport $report): StreamedResponse
    {
        $this->authorizeAccess($request, $report);
        abort_unless($report->status === 'completed' && filled($report->narasi_terpadu), 422, 'Narasi terpadu belum dibuat.');
        if ($this->isClientViewer($request->user())) {
            abort_unless($report->narasi_status === 'final', 403, 'Narasi Anda belum final, belum bisa diunduh.');
        }

        return Storage::disk('local')->download($this->pdfService->generateKlien($report), "narasi-{$report->id}.pdf");
    }

    /**
     * Antre draft narasi terpadu lewat LLM di background
     * (GenerateNarasiTerpaduJob - lihat docblocknya & NarasiTerpaduService
     * kenapa ini beda dari NarasiCacheService, dan kenapa asinkron sejak
     * 2026-08-22: laporan panjang bisa butuh 1-3 menit generate, terlalu
     * lama untuk 1 request HTTP). Selalu jadi status 'draft' - TIDAK PERNAH
     * langsung 'final', grafolog wajib review/edit dulu lewat
     * updateNarasiTerpadu() sebelum klien bisa melihatnya.
     *
     * Dedup-guard: kalau data skor (`data.sindrom`) + bahasa PERSIS sama
     * dengan generate terakhir yang berhasil (`narasi_input_hash`), tolak
     * dengan 409 kecuali `force: true` - supaya klik ganda/generate ulang
     * tanpa perubahan tidak membakar LLM call percuma. Ruang kombinasi skor
     * (lihat CLAUDE.md) terlalu besar untuk di-cache permanen; ini cuma
     * dedup pada level "1 laporan spesifik ini belum berubah", bukan
     * caching lintas laporan.
     *
     * Cek status + tulis 'generating' dibungkus `lockForUpdate()` dalam 1
     * transaksi - tanpa ini, 2 request yang datang nyaris bersamaan (klik
     * ganda sebelum tombol sempat ke-disable di frontend) bisa dua-duanya
     * lolos pengecekan status SEBELUM salah satu sempat menulis
     * 'generating' (race condition klasik) - dua job Anthropic jalan
     * bersamaan untuk laporan yang sama. `lockForUpdate()` membuat request
     * ke-2 menunggu request pertama commit dulu baru baca status terbaru.
     */
    public function generateNarasiTerpadu(GenerateNarasiTerpaduRequest $request, PersonalityReport $report): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat membuat narasi terpadu.');
        abort_unless($report->sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($report->status !== 'completed', 422, 'Laporan belum selesai dibuat.');
        abort_if($report->hasActiveValidation(), 422, 'Laporan sedang dalam proses supervisi - tidak bisa diedit sampai selesai.');

        $bahasa = $request->validated('bahasa');
        $force = $request->boolean('force');
        $hash = NarasiTerpaduService::inputHashFor($report, $bahasa);

        $outcome = DB::transaction(function () use ($report, $hash, $force) {
            $locked = PersonalityReport::whereKey($report->id)->lockForUpdate()->first();

            if ($locked->narasi_status === 'generating') {
                return 'already_generating';
            }

            $dataBelumBerubah = $locked->narasi_input_hash === $hash && $locked->narasi_status !== 'belum_dibuat';
            if ($dataBelumBerubah && ! $force) {
                return 'unchanged';
            }

            $locked->update(['narasi_status' => 'generating', 'narasi_generation_error' => null]);

            return 'dispatched';
        });

        abort_if($outcome === 'already_generating', 409, 'Narasi terpadu sedang diproses, tunggu sampai selesai.');
        abort_if($outcome === 'unchanged', 409, 'Data skor belum berubah sejak generate terakhir. Kirim ulang dengan force untuk tetap generate.');

        GenerateNarasiTerpaduJob::dispatch($report->id, $bahasa, $user->id, $hash);

        return response()->json($report->fresh());
    }

    /**
     * Grafolog edit manual draft narasi terpadu (hasil AI atau tulisan
     * sendiri dari nol) dan/atau menandainya final. Versi sebelumnya
     * disimpan ke report_revisions - sama pola dengan updateNarasi() di
     * bawah, method snapshot beda karena bentuk datanya beda.
     */
    public function updateNarasiTerpadu(UpdateNarasiTerpaduRequest $request, PersonalityReport $report): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog(), 403, 'Hanya grafolog yang dapat mengedit narasi terpadu.');
        abort_unless($report->sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani sample ini.');
        abort_if($report->status !== 'completed', 422, 'Laporan belum selesai dibuat.');
        abort_if($report->hasActiveValidation(), 422, 'Laporan sedang dalam proses supervisi - tidak bisa diedit sampai selesai.');

        $report = DB::transaction(function () use ($report, $request, $user) {
            $this->reportRevisions->snapshotNarasiTerpaduBeforeChange($report, $user, $request->input('catatan'));

            $report->update([
                'narasi_terpadu' => $request->validated('narasi_terpadu'),
                'narasi_bahasa' => $request->validated('bahasa'),
                'narasi_status' => $request->validated('status'),
                'pdf_path_klien' => null,
            ]);

            AuditLog::record('edit_narasi_terpadu', PersonalityReport::class, $report->id, $user->id, $request->ip());

            return $report;
        });

        return response()->json($report->fresh());
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

    /**
     * role 'user' selalu berarti subjek tes (klien self-service ATAUPUN
     * kandidat HR - "Candidate" sengaja bukan model terpisah, lihat
     * CLAUDE.md "HR: Company, Candidate import"), tidak pernah staf.
     * Jadi pengecekan by-role di sini sudah cukup, tidak perlu tahu jalur
     * mana sample-nya berasal.
     */
    private function isClientViewer(User $user): bool
    {
        return $user->role === 'user';
    }
}
