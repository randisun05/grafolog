<?php

namespace App\Services\Reporting;

use App\Models\Aspek;
use App\Models\Sindrom;
use App\Services\Llm\NarasiCacheService;

/**
 * Menyiapkan isi laporan (`data`) dalam satu bahasa untuk tampilan web/PDF.
 *
 * Laporan baru menyimpan narasi_en & narasi_id per aspek (snapshot saat
 * dibuat). Laporan lama (sebelum 2026-10-15) hanya punya `narasi` (Inggris);
 * versi Indonesianya diambil dari terjemahan KB saat ini berdasarkan
 * narasi_level. Override manual grafolog disimpan per bahasa
 * (narasi_diedit_manual_{en|id}); override lama tanpa penanda bahasa
 * ditampilkan di kedua bahasa.
 */
class ReportLocalizer
{
    public const BAHASA = ['id', 'en'];

    private const BAND_EN = ['Nilai Rendah' => 'Low', 'Nilai Sedang' => 'Moderate', 'Nilai Tinggi' => 'High'];

    private const POLARITAS_EN = ['HIJAU' => 'GREEN', 'MERAH' => 'RED'];

    public function __construct(private NarasiCacheService $narasi) {}

    public function localize(array $data, string $bahasa): array
    {
        $sindromNamaId = Sindrom::pluck('nama_id', 'kode_romawi');
        $aspekByKode = Aspek::all()->keyBy('kode');

        foreach ($data['sindrom'] ?? [] as $i => $sindrom) {
            if ($bahasa === 'id') {
                $data['sindrom'][$i]['nama'] = $sindrom['nama_id'] ?? $sindromNamaId[$sindrom['kode_romawi']] ?? $sindrom['nama'];
            } else {
                $data['sindrom'][$i]['band_label_rata_rata'] = self::BAND_EN[$sindrom['band_label_rata_rata'] ?? ''] ?? ($sindrom['band_label_rata_rata'] ?? null);
                $data['sindrom'][$i]['polaritas'] = self::POLARITAS_EN[$sindrom['polaritas'] ?? ''] ?? ($sindrom['polaritas'] ?? null);
            }

            foreach ($sindrom['aspek'] ?? [] as $j => $aspek) {
                $data['sindrom'][$i]['aspek'][$j] = $this->localizeAspek($aspek, $bahasa, $aspekByKode[$aspek['kode']] ?? null);
            }
        }

        $data['bahasa'] = $bahasa;

        return $data;
    }

    private function localizeAspek(array $aspek, string $bahasa, ?Aspek $kb): array
    {
        $isLegacy = ! array_key_exists('narasi_en', $aspek);
        $legacyOverride = $isLegacy && ! empty($aspek['narasi_diedit_manual']);

        if ($bahasa === 'en') {
            $aspek['narasi'] = $isLegacy ? ($aspek['narasi'] ?? '') : $aspek['narasi_en'];
            $aspek['band_label'] = self::BAND_EN[$aspek['band_label'] ?? ''] ?? ($aspek['band_label'] ?? null);
            $aspek['narasi_diedit_manual'] = $legacyOverride || ! empty($aspek['narasi_diedit_manual_en']);
            $aspek['narasi_bahasa'] = 'en';

            return $aspek;
        }

        $aspek['nama'] = $aspek['nama_id'] ?? $kb?->nama_id ?? $aspek['nama'];

        if ($legacyOverride || ! empty($aspek['narasi_diedit_manual_id'])) {
            $aspek['narasi'] = $legacyOverride ? $aspek['narasi'] : $aspek['narasi_id'];
            $aspek['narasi_diedit_manual'] = true;
            $aspek['narasi_bahasa'] = 'id';

            return $aspek;
        }

        $level = $aspek['narasi_level'] ?? null;
        $teksId = $aspek['narasi_id'] ?? (($kb && $level) ? $this->narasi->teksIndonesia($kb, $level) : null);
        $aspek['narasi'] = $teksId ?? ($aspek['narasi_en'] ?? $aspek['narasi'] ?? '');
        $aspek['narasi_bahasa'] = $teksId ? 'id' : 'en';
        $aspek['narasi_diedit_manual'] = false;

        return $aspek;
    }
}
