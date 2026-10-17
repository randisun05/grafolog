<?php

namespace App\Services\Knowledge;

use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use App\Models\Sindrom;
use App\Support\CiriKey;
use Illuminate\Support\Facades\DB;

/**
 * Matriks keterkaitan knowledge base (2026-10-16), murni baca:
 *  - variabel ukur x aspek (lewat rule category/comparison),
 *  - aspek -> aspek (lewat rule indikator_checked),
 *  - ciri bersama (Indikator bernama sama di >= 2 aspek, App\Support\CiriKey),
 *  - celah (variabel/aspek/indikator yang belum tersambung aturan),
 *  - korelasi empiris dari data sampel nyata (empiris()).
 *
 * Semua matriks struktural menggambarkan ATURAN pakem, bukan korelasi yang
 * terbukti - hanya empiris() yang dihitung dari data.
 */
class KnowledgeMatrixService
{
    public function struktur(): array
    {
        $sindrom = Sindrom::orderBy('id')->get(['id', 'kode_romawi', 'nama', 'nama_id']);
        $aspek = Aspek::orderBy('kode')->get(['id', 'kode', 'nama', 'nama_id', 'sindrom_id']);
        $indikator = Indikator::get(['id', 'kode', 'nama', 'aspek_id', 'posisi'])->keyBy('id');
        $variabel = MeasurementVariable::orderBy('id')->get(['id', 'kode', 'nama']);
        $rules = IndikatorRule::get(['id', 'indikator_id', 'rule_type', 'variable_a_id', 'variable_b_id', 'depends_on_indikator_id']);

        $ref = fn (int $id) => [
            'id' => $id, 'kode' => $indikator[$id]->kode, 'nama' => $indikator[$id]->nama, 'aspek_id' => $indikator[$id]->aspek_id,
        ];

        // Variabel ukur -> aspek
        $va = [];
        $terukur = [];
        $beraturan = [];
        $variabelDipakai = [];
        foreach ($rules as $r) {
            $beraturan[$r->indikator_id] = true;
            if ($r->rule_type === 'indikator_checked') {
                continue;
            }
            $terukur[$r->indikator_id] = true;
            foreach (array_filter([$r->variable_a_id, $r->variable_b_id]) as $vid) {
                $variabelDipakai[$vid] = true;
                $va[$vid][$indikator[$r->indikator_id]->aspek_id][$r->indikator_id] = true;
            }
        }
        $variabelAspek = [];
        foreach ($va as $vid => $perAspek) {
            foreach ($perAspek as $aid => $ids) {
                $variabelAspek[] = [
                    'variable_id' => $vid, 'aspek_id' => $aid,
                    'indikator' => array_map($ref, array_keys($ids)),
                ];
            }
        }

        // Aspek -> aspek (indikator_checked: dependsOn tercentang -> indikator ikut)
        $aa = [];
        $edges = [];
        foreach ($rules as $r) {
            if ($r->rule_type !== 'indikator_checked' || ! $r->depends_on_indikator_id) {
                continue;
            }
            $dari = $indikator[$r->depends_on_indikator_id];
            $ke = $indikator[$r->indikator_id];
            $aa[$dari->aspek_id][$ke->aspek_id][] = ['dari' => $ref($dari->id), 'ke' => $ref($ke->id)];
            $edges[$dari->id][] = $ke->id;
        }
        $aspekAspek = [];
        $kodeAspek = $aspek->pluck('kode', 'id');
        $arah = ['maju' => 0, 'mundur' => 0, 'dalam_aspek' => 0];
        foreach ($aa as $dari => $perKe) {
            foreach ($perKe as $ke => $tautan) {
                $aspekAspek[] = ['dari_aspek_id' => $dari, 'ke_aspek_id' => $ke, 'tautan' => $tautan];
                $key = $dari === $ke ? 'dalam_aspek' : ($kodeAspek[$dari] < $kodeAspek[$ke] ? 'maju' : 'mundur');
                $arah[$key] += count($tautan);
            }
        }
        $pasanganTertaut = [];
        foreach ($edges as $a => $targets) {
            foreach ($targets as $b) {
                $pasanganTertaut[min($a, $b).'-'.max($a, $b)][] = $a;
            }
        }
        $duaArah = count(array_filter($pasanganTertaut, fn ($srcs) => count(array_unique($srcs)) > 1));

        // Ciri bersama
        $grup = [];
        foreach (CiriKey::groups($indikator) as $ids) {
            $total = 0;
            $tertaut = 0;
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    if ($indikator[$ids[$i]]->aspek_id === $indikator[$ids[$j]]->aspek_id) {
                        continue;
                    }
                    $total++;
                    $tertaut += isset($pasanganTertaut[min($ids[$i], $ids[$j]).'-'.max($ids[$i], $ids[$j])]) ? 1 : 0;
                }
            }
            $grup[] = [
                'nama' => $indikator[$ids[0]]->nama,
                'indikator' => array_map($ref, $ids),
                'jumlah_aspek' => count(array_unique(array_map(fn ($id) => $indikator[$id]->aspek_id, $ids))),
                'pasangan' => $total,
                'pasangan_tertaut' => $tertaut,
            ];
        }
        usort($grup, fn ($x, $y) => [$y['jumlah_aspek'], $y['pasangan'] - $y['pasangan_tertaut']] <=> [$x['jumlah_aspek'], $x['pasangan'] - $x['pasangan_tertaut']]);

        // Rantai kaskade terpanjang + deteksi siklus (DFS, graf kecil).
        [$rantai, $siklus] = $this->rantai($edges);

        $perAspek = $indikator->groupBy('aspek_id');
        $aspekOut = $aspek->map(function ($a) use ($perAspek, $terukur, $beraturan, $sindrom) {
            $ids = ($perAspek[$a->id] ?? collect())->pluck('id');

            return [
                'id' => $a->id, 'kode' => $a->kode, 'nama' => $a->nama, 'nama_id' => $a->nama_id,
                'sindrom_kode' => $sindrom->firstWhere('id', $a->sindrom_id)?->kode_romawi,
                'indikator_count' => $ids->count(),
                'indikator_terukur' => $ids->filter(fn ($id) => isset($terukur[$id]))->count(),
                'indikator_beraturan' => $ids->filter(fn ($id) => isset($beraturan[$id]))->count(),
            ];
        })->values();

        return [
            'sindrom' => $sindrom,
            'aspek' => $aspekOut,
            'variabel' => $variabel->map(fn ($v) => [
                'id' => $v->id, 'kode' => $v->kode, 'nama' => $v->nama, 'dipakai' => isset($variabelDipakai[$v->id]),
            ])->values(),
            'variabel_aspek' => $variabelAspek,
            'aspek_aspek' => $aspekAspek,
            'ciri_bersama' => [
                'aktif' => (bool) config('checklist.ciri_bersama', true),
                'grup' => $grup,
            ],
            'celah' => [
                'variabel_tanpa_aturan' => $variabel->reject(fn ($v) => isset($variabelDipakai[$v->id]))->pluck('id')->values(),
                'aspek_tanpa_indikator_terukur' => $aspekOut->where('indikator_terukur', 0)->pluck('id')->values(),
                'aspek_tanpa_aturan' => $aspekOut->where('indikator_beraturan', 0)->pluck('id')->values(),
                'indikator_tanpa_aturan' => $indikator->count() - count($beraturan),
                'pasangan_ciri_belum_tertaut' => array_sum(array_map(fn ($g) => $g['pasangan'] - $g['pasangan_tertaut'], $grup)),
                'arah_tautan' => $arah + ['dua_arah' => $duaArah],
                'rantai_terpanjang' => $rantai,
                'ada_siklus' => $siklus,
            ],
            'ringkasan' => [
                'aturan_ukur' => $rules->where('rule_type', '!=', 'indikator_checked')->count(),
                'tautan_indikator' => $rules->where('rule_type', 'indikator_checked')->count(),
                'variabel' => $variabel->count(),
                'variabel_dipakai' => count($variabelDipakai),
                'indikator' => $indikator->count(),
                'indikator_beraturan' => count($beraturan),
            ],
        ];
    }

    /**
     * Korelasi Pearson antar-variabel ukur (measurement_readings.nilai per
     * sample) dan antar-skor aspek (report_aspek_scores laporan selesai).
     * Hanya pasangan dengan >= $minN pengamatan bersama yang dihitung.
     */
    public function empiris(int $minN): array
    {
        $readings = DB::table('measurement_readings')->whereNotNull('nilai')
            ->get(['sample_id', 'variable_id', 'nilai']);
        $perVar = [];
        foreach ($readings as $r) {
            $perVar[$r->variable_id][$r->sample_id] = (float) $r->nilai;
        }

        $scores = DB::table('report_aspek_scores as s')
            ->join('personality_reports as r', 'r.id', '=', 's.report_id')
            ->where('r.status', 'completed')
            ->get(['s.report_id', 's.aspek_id', 's.skor']);
        $perAspek = [];
        foreach ($scores as $s) {
            $perAspek[$s->aspek_id][$s->report_id] = (float) $s->skor;
        }

        return [
            'min_n' => $minN,
            'variabel' => [
                'n_sampel' => $readings->pluck('sample_id')->unique()->count(),
                'sel' => $this->korelasi($perVar, $minN),
            ],
            'aspek' => [
                'n_laporan' => $scores->pluck('report_id')->unique()->count(),
                'sel' => $this->korelasi($perAspek, $minN),
            ],
        ];
    }

    /**
     * @param  array<int, array<int, float>>  $series  id => [observasi_id => nilai]
     * @return array<int, array{a:int, b:int, r:float, n:int}>
     */
    private function korelasi(array $series, int $minN): array
    {
        $ids = array_keys($series);
        sort($ids);
        $out = [];
        foreach ($ids as $i => $a) {
            foreach (array_slice($ids, $i + 1) as $b) {
                $common = array_intersect_key($series[$a], $series[$b]);
                $n = count($common);
                if ($n < $minN) {
                    continue;
                }
                $x = array_values($common);
                $y = array_values(array_intersect_key($series[$b], $common));
                $r = $this->pearson($x, $y);
                if ($r !== null) {
                    $out[] = ['a' => $a, 'b' => $b, 'r' => round($r, 3), 'n' => $n];
                }
            }
        }

        return $out;
    }

    private function pearson(array $x, array $y): ?float
    {
        $n = count($x);
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxy = $sxx = $syy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $mx;
            $dy = $y[$i] - $my;
            $sxy += $dx * $dy;
            $sxx += $dx * $dx;
            $syy += $dy * $dy;
        }
        if ($sxx == 0.0 || $syy == 0.0) {
            return null; // salah satu variabel konstan - korelasi tidak terdefinisi
        }

        return $sxy / sqrt($sxx * $syy);
    }

    /**
     * @param  array<int, int[]>  $edges
     * @return array{0:int, 1:bool} [jumlah node rantai terpanjang, ada siklus]
     */
    private function rantai(array $edges): array
    {
        $memo = [];
        $state = [];
        $siklus = false;
        $depth = function (int $u) use (&$depth, &$memo, &$state, &$siklus, $edges): int {
            if (isset($memo[$u])) {
                return $memo[$u];
            }
            if (($state[$u] ?? 0) === 1) {
                $siklus = true;

                return 0;
            }
            $state[$u] = 1;
            $best = 0;
            foreach ($edges[$u] ?? [] as $w) {
                $best = max($best, $depth($w));
            }
            $state[$u] = 2;

            return $memo[$u] = 1 + $best;
        };
        $max = 0;
        foreach (array_keys($edges) as $u) {
            $max = max($max, $depth($u));
        }

        return [$max, $siklus];
    }
}
