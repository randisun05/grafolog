<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\PersonalityReport;
use App\Services\Reporting\NarasiTerpaduService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Menjalankan NarasiTerpaduService::generateDraft() di background - dipisah
 * dari request HTTP grafolog (ReportController::generateNarasiTerpadu())
 * supaya generate laporan panjang (bisa 1-3 menit untuk 20-40 halaman,
 * lihat MAX_TOKENS di NarasiTerpaduService) tidak terikat batas waktu
 * request web. Membutuhkan queue worker berjalan (`php artisan queue:work`),
 * sama seperti SendReportCompletedNotification - QUEUE_CONNECTION=database.
 */
class GenerateNarasiTerpaduJob implements ShouldQueue
{
    use Queueable;

    /**
     * Batas waktu worker mem-paksa-matikan job ini (SIGALRM) - default
     * `queue:work` cuma 60 detik, jauh di bawah waktu generate laporan
     * panjang (bisa 1-3 menit, lihat NarasiTerpaduService::MAX_TOKENS).
     * Tanpa ini, worker akan membunuh proses PHP-nya di tengah panggilan
     * Anthropic - biaya API tetap kepotong tapi hasilnya hilang, laporan
     * macet di status 'generating'. 360 detik = margin di atas timeout
     * HTTP client (300s) di NarasiTerpaduService.
     */
    public $timeout = 360;

    public function __construct(
        public int $reportId,
        public string $bahasa,
        public ?int $actorUserId,
        public string $inputHash,
    ) {}

    public function handle(NarasiTerpaduService $narasiTerpadu): void
    {
        $report = PersonalityReport::find($this->reportId);
        if (! $report) {
            return;
        }

        try {
            $draft = $narasiTerpadu->generateDraft($report, $this->bahasa);
        } catch (RuntimeException $e) {
            $report->update([
                // Kembali ke status sebelum 'generating' - kalau sebelumnya
                // sudah pernah ada draft/final, JANGAN dihapus, biarkan
                // grafolog tetap punya versi lama sambil tahu percobaan
                // regenerate barusan gagal (narasi_generation_error).
                'narasi_status' => $report->narasi_terpadu ? 'draft' : 'belum_dibuat',
                'narasi_generation_error' => $e->getMessage(),
            ]);

            return;
        }

        $report->update([
            'narasi_terpadu' => $draft,
            'narasi_bahasa' => $this->bahasa,
            'narasi_status' => 'draft',
            'narasi_input_hash' => $this->inputHash,
            'narasi_generation_error' => null,
            'pdf_path_klien' => null,
        ]);

        AuditLog::record('generate_narasi_terpadu', PersonalityReport::class, $report->id, $this->actorUserId, null);
    }
}
