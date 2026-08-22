<?php

namespace App\Services\Reporting;

use App\Models\Aspek;
use App\Models\KombinasiTemuan;

/**
 * Memfilter breakdown internal (`personality_reports.data`) yang SUDAH
 * ADA berdasarkan Topik - bahan buat "produk turunan" (segmen B2B, chat
 * interaktif per-topik, dst - belum diputuskan yang mana, lihat CLAUDE.md
 * "Topik (kategorisasi)"). SENGAJA murni baca/susun ulang data yang sudah
 * dihitung ScoringEngineService/KombinasiTemuanService - TIDAK memanggil
 * ulang mesin skoring, TIDAK memanggil AI, TIDAK mengubah `data` yang
 * tersimpan di database. Mekanisme generate laporan utama tidak disentuh
 * sama sekali oleh class ini.
 */
class TopikFilterService
{
    /**
     * @param  array{sindrom?: array<int, array<string, mixed>>, kombinasi_ditemukan?: array<int, array<string, mixed>>}  $data
     * @param  array<int, int>  $topikIds
     * @return array{sindrom: array<int, array<string, mixed>>, kombinasi_ditemukan: array<int, array<string, mixed>>}
     */
    public function filter(array $data, array $topikIds): array
    {
        if ($topikIds === []) {
            return ['sindrom' => $data['sindrom'] ?? [], 'kombinasi_ditemukan' => $data['kombinasi_ditemukan'] ?? []];
        }

        $topikIdsByAspekKode = Aspek::with('topik:id')->get()
            ->mapWithKeys(fn (Aspek $a) => [$a->kode => $a->topik->pluck('id')->all()]);
        $topikIdsByKombinasiId = KombinasiTemuan::with('topik:id')->get()
            ->mapWithKeys(fn (KombinasiTemuan $k) => [$k->id => $k->topik->pluck('id')->all()]);

        $sindromFiltered = [];
        foreach ($data['sindrom'] ?? [] as $sindrom) {
            $aspekCocok = array_values(array_filter(
                $sindrom['aspek'] ?? [],
                fn ($aspek) => array_intersect($topikIdsByAspekKode->get($aspek['kode'], []), $topikIds) !== []
            ));
            if ($aspekCocok !== []) {
                $sindromFiltered[] = [...$sindrom, 'aspek' => $aspekCocok];
            }
        }

        $kombinasiFiltered = array_values(array_filter(
            $data['kombinasi_ditemukan'] ?? [],
            fn ($temuan) => array_intersect($topikIdsByKombinasiId->get($temuan['id'], []), $topikIds) !== []
        ));

        return ['sindrom' => $sindromFiltered, 'kombinasi_ditemukan' => $kombinasiFiltered];
    }
}
