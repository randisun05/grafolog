<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PersonalityReport extends Model
{
    protected $fillable = [
        'sample_id', 'tier', 'status', 'data', 'pdf_path', 'generated_at',
        'narasi_terpadu', 'narasi_bahasa', 'narasi_status', 'pdf_path_klien',
        'narasi_input_hash', 'narasi_generation_error',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function aspekScores(): HasMany
    {
        return $this->hasMany(ReportAspekScore::class, 'report_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReportRevision::class, 'report_id');
    }

    /**
     * Rata-rata durasi pengerjaan (sample.created_at -> report.generated_at)
     * dalam hari, untuk sekumpulan sample_id manapun (per-user, per-company,
     * per-grafolog, dst - pemanggil yang tentukan lingkupnya). Diekstrak
     * 2026-09-03 dari duplikasi byte-identik di DashboardController dan
     * Admin\CompanyController (2 tempat, akan jadi 3-4 dengan fitur rekap -
     * di titik itu ekstraksi ke model, bukan controller lain, jadi masuk
     * akal - lihat guratan-api/CLAUDE.md).
     */
    public static function avgTurnaroundDaysFor(Collection $sampleIds): ?float
    {
        $reports = self::whereIn('sample_id', $sampleIds)
            ->where('status', 'completed')
            ->whereNotNull('generated_at')
            ->with('sample:id,created_at')
            ->get();

        if ($reports->isEmpty()) {
            return null;
        }

        $totalDays = $reports->sum(
            fn (self $report) => $report->sample->created_at->diffInHours($report->generated_at) / 24
        );

        return round($totalDays / $reports->count(), 1);
    }
}
