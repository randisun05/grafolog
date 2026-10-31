<?php

namespace App\Services\B2b;

use App\Models\Order;
use App\Models\PersonalityReport;
use App\Services\Reporting\TopikFilterService;
use Illuminate\Support\Collection;

/**
 * Meratakan breakdown internal (`personality_reports.data`, SUDAH ADA di
 * database) dari BANYAK laporan company sekaligus jadi 1 ringkasan agregat
 * - rata-rata skor + distribusi narasi_level per Aspek, dikelompokkan per
 * Sindrom. TIDAK PERNAH memanggil ulang mesin skoring/AI - murni
 * transformasi PHP atas data yang sudah tersimpan, sama filosofi
 * TopikFilterService (dipakai ulang APA ADANYA di sini, bukan ditulis
 * ulang) yang dipakai sejak "Topik (kategorisasi)" (2026-08-22).
 *
 * Satu method ini melayani 2 kapabilitas Supervisor sekaligus (fitur
 * Supervisor, 2026-09-08 - lihat CLAUDE.md):
 * - "Dashboard Potensi" (kapabilitas #4): panggil dengan $topikIds=[] -
 *   TopikFilterService::filter() dengan array kosong berarti tanpa filter,
 *   agregat penuh perusahaan.
 * - "Potensi sesuai kategori" (kapabilitas #5): panggil dengan $topikIds
 *   berisi ID Topik - tiap laporan disaring dulu ke Aspek/Kombinasi Temuan
 *   yang ditag topik itu sebelum diratakan.
 * Fase 3 (chat interaktif, menyusul) berencana memakai method PERSIS SAMA
 * ini ($topikIds=[]) untuk konteks yang dikirim ke LLM - jaminan konkret
 * jawaban chat tidak pernah menyimpang dari angka yang ditampilkan
 * Dashboard Potensi, karena satu sumber kebenaran yang sama.
 */
class PotensiAggregationService
{
    public function __construct(private readonly TopikFilterService $topikFilter) {}

    /**
     * Laporan SELESAI dan DISETUJUI milik kandidat perusahaan ini (rantai: pesanan batch perusahaan -> sampel -> laporan).
     * Laporan yang belum disetujui QA tidak ikut agregat, sama seperti yang boleh dilihat HR di halaman laporan.
     *
     * @return Collection<int, PersonalityReport>
     */
    public function reportsForCompany(int $companyId): Collection
    {
        $sampleIds = Order::whereHas('batch', fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('sample_id')->pluck('sample_id');

        return PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('status', 'completed')
            ->where('review_status', PersonalityReport::REVIEW_APPROVED)
            ->with('sample:id,user_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, PersonalityReport>  $reports
     * @param  array<int, int>  $topikIds
     * @return array{candidate_count: int, report_count: int, sindrom: array<int, array<string, mixed>>}
     */
    public function aggregate(Collection $reports, array $topikIds = []): array
    {
        $sindromMeta = [];
        $aspekMeta = [];
        $aspekStats = [];

        foreach ($reports as $report) {
            $filtered = $this->topikFilter->filter($report->data ?? [], $topikIds);

            foreach ($filtered['sindrom'] as $sindrom) {
                $sindromId = $sindrom['id'];
                $sindromMeta[$sindromId] ??= [
                    'id' => $sindromId,
                    'kode_romawi' => $sindrom['kode_romawi'],
                    'nama' => $sindrom['nama'],
                ];

                foreach ($sindrom['aspek'] as $aspek) {
                    $kode = $aspek['kode'];
                    $aspekMeta[$kode] ??= [
                        'kode' => $kode,
                        'nama' => $aspek['nama'],
                        'sindrom_id' => $sindromId,
                    ];

                    $aspekStats[$kode] ??= [
                        'sum' => 0,
                        'count' => 0,
                        'narasi_level' => ['low' => 0, 'medium' => 0, 'high' => 0, 'very_high' => 0],
                    ];
                    $aspekStats[$kode]['sum'] += $aspek['skor'];
                    $aspekStats[$kode]['count']++;

                    $level = $aspek['narasi_level'] ?? null;
                    if ($level !== null && array_key_exists($level, $aspekStats[$kode]['narasi_level'])) {
                        $aspekStats[$kode]['narasi_level'][$level]++;
                    }
                }
            }
        }

        $sindromOutput = [];
        foreach ($sindromMeta as $sindromId => $meta) {
            $aspekForSindrom = array_values(array_filter(
                $aspekMeta,
                fn ($a) => $a['sindrom_id'] === $sindromId
            ));

            $sindromOutput[] = [
                'id' => $meta['id'],
                'kode_romawi' => $meta['kode_romawi'],
                'nama' => $meta['nama'],
                'aspek' => array_map(function ($a) use ($aspekStats) {
                    $stats = $aspekStats[$a['kode']];

                    return [
                        'kode' => $a['kode'],
                        'nama' => $a['nama'],
                        'avg_skor' => $stats['count'] > 0 ? round($stats['sum'] / $stats['count'], 2) : null,
                        'narasi_level_distribution' => $stats['narasi_level'],
                    ];
                }, $aspekForSindrom),
            ];
        }

        return [
            'candidate_count' => $reports->pluck('sample.user_id')->filter()->unique()->count(),
            'report_count' => $reports->count(),
            'sindrom' => $sindromOutput,
        ];
    }
}
