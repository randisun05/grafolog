<?php

namespace App\Services\Scoring;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\KombinasiSyarat;
use App\Models\KombinasiTemuan;

/**
 * Evaluasi "Kombinasi Temuan" - syarat lintas Indikator/Aspek/Sindrom yang
 * digabung AND/OR menghasilkan 1 teks interpretasi BARU, beda dari
 * indikator_rules yang sekedar memperluas bukti yang sama (lihat migrasi
 * kombinasi_temuan & CLAUDE.md). Dipanggil dari ScoringController::submit()/
 * correct() setelah ScoringEngineService::generate() + attachIndikatorNarasi(),
 * hasilnya ditulis ke `data.kombinasi_ditemukan` (top-level, bukan nested per
 * Aspek - karena 1 temuan bisa merentang beberapa Aspek/Sindrom sekaligus).
 */
class KombinasiTemuanService
{
    /**
     * @param  array<string,int>  $skorPerAspek  kode Aspek => skor 1-10 (input yang sama persis dengan ScoringEngineService::generate())
     * @return array<int, array{id:int, nama:string, teks_interpretasi:string}>
     */
    public function evaluate(array $skorPerAspek, HandwritingSample $sample): array
    {
        [$levelByAspekId, $levelBySindromId] = $this->hitungLevel($skorPerAspek);
        $checkedIndikatorIds = $sample->indikatorChecks()->where('checked', true)->pluck('indikator_id')->flip();

        $matched = [];
        foreach (KombinasiTemuan::with('syarat')->get() as $temuan) {
            if ($temuan->syarat->isEmpty()) {
                continue;
            }

            $results = $temuan->syarat->map(
                fn (KombinasiSyarat $syarat) => $this->evaluateSyarat($syarat, $levelByAspekId, $levelBySindromId, $checkedIndikatorIds)
            );

            $isMatch = $temuan->logika_gabung === 'AND'
                ? $results->every(fn ($r) => $r === true)
                : $results->contains(true);

            if ($isMatch) {
                $matched[] = ['id' => $temuan->id, 'nama' => $temuan->nama, 'teks_interpretasi' => $temuan->teks_interpretasi];
            }
        }

        return $matched;
    }

    /**
     * @return array{0: array<int,string>, 1: array<int,string>} [level per aspek_id, level per sindrom_id]
     *
     * @param  array<string,int>  $skorPerAspek
     */
    private function hitungLevel(array $skorPerAspek): array
    {
        $aspekByKode = Aspek::whereIn('kode', array_keys($skorPerAspek))->get()->keyBy('kode');

        $levelByAspekId = [];
        $sindromIdByAspekId = [];
        $skorByAspekId = [];
        foreach ($skorPerAspek as $kode => $skor) {
            $aspek = $aspekByKode->get((string) $kode);
            if (! $aspek) {
                continue;
            }
            $skor = (int) $skor;
            $levelByAspekId[$aspek->id] = ScoringEngineService::narasiLevelUntukSkor($skor);
            $sindromIdByAspekId[$aspek->id] = $aspek->sindrom_id;
            $skorByAspekId[$aspek->id] = $skor;
        }

        // Rata-rata skor per Sindrom (Aspek yang ADA di input ini saja - sama
        // filosofi "tolerate partial input" dengan ScoringEngineService::generate()),
        // lalu dipetakan ke bucket level yang sama (low/medium/high/very_high).
        $sumBySindrom = [];
        $countBySindrom = [];
        foreach ($skorByAspekId as $aspekId => $skor) {
            $sindromId = $sindromIdByAspekId[$aspekId];
            $sumBySindrom[$sindromId] = ($sumBySindrom[$sindromId] ?? 0) + $skor;
            $countBySindrom[$sindromId] = ($countBySindrom[$sindromId] ?? 0) + 1;
        }

        $levelBySindromId = [];
        foreach ($sumBySindrom as $sindromId => $sum) {
            $rataRata = (int) round($sum / $countBySindrom[$sindromId]);
            $levelBySindromId[$sindromId] = ScoringEngineService::narasiLevelUntukSkor($rataRata);
        }

        return [$levelByAspekId, $levelBySindromId];
    }

    /**
     * @param  array<int,string>  $levelByAspekId
     * @param  array<int,string>  $levelBySindromId
     * @param  \Illuminate\Support\Collection<int,int>  $checkedIndikatorIds  hasil pluck('indikator_id')->flip() - dipakai isset() O(1)
     */
    private function evaluateSyarat(KombinasiSyarat $syarat, array $levelByAspekId, array $levelBySindromId, $checkedIndikatorIds): bool
    {
        return match ($syarat->level) {
            'indikator' => $syarat->kondisi === 'tercentang'
                ? isset($checkedIndikatorIds[$syarat->indikator_id])
                : ! isset($checkedIndikatorIds[$syarat->indikator_id]),
            'aspek' => ($levelByAspekId[$syarat->aspek_id] ?? null) === $syarat->kondisi,
            'sindrom' => ($levelBySindromId[$syarat->sindrom_id] ?? null) === $syarat->kondisi,
            default => false,
        };
    }
}
