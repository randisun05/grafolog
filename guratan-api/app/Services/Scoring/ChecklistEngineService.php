<?php

namespace App\Services\Scoring;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\SampleIndikatorCheck;
use App\Support\CiriKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KM-G, diperluas 2026-08-19: mengevaluasi indikator_rules terhadap
 * measurement_readings 1 sample untuk auto-centang Indikator (measurement
 * worksheet -> checklist), lalu menghitung skor per Aspek dari jumlah
 * posisi tercentang. Sumber kebenaran status "tercentang" adalah tabel
 * sample_indikator_checks - lihat migrasi & CLAUDE.md.
 *
 * **Unifikasi cross-reference (2026-08-19)**: dulu cascade "centang A ->
 * ikut centang B" adalah mekanisme TERPISAH (tabel indikator_cross_reference
 * + applyCascadeFrom() satu-hop, dijalankan setelah loop utama). Sekarang
 * itu HANYA rule_type='indikator_checked' biasa di indikator_rules,
 * dievaluasi lewat mesin AND/OR yang sama persis dengan rule
 * measurement/irregularity - satu sistem kriteria, bukan dua. Konsekuensi:
 * rantai bisa berlapis (A men-trigger B, B men-trigger C) dan bisa
 * dikombinasi AND/OR dengan rule measurement (keputusan eksplisit user,
 * lebih kuat dari cascade satu-hop lama).
 *
 * Baris `sumber=manual` beku begitu diputuskan grafolog - tidak pernah
 * ditimpa evaluasi ulang. Baris `sumber=auto`/`cascade` SENGAJA terus
 * direkonsiliasi ulang setiap evaluateSample() dipanggil (termasuk yang
 * dulu berasal dari rule indikator_checked) - konsisten dengan filosofi
 * "cascade dan auto sama-sama derivasi otomatis, bukan keputusan manusia."
 */
class ChecklistEngineService
{
    /**
     * Jalankan seluruh indikator_rules atas hasil ukur + status tercentang
     * Indikator lain, sampai titik tetap (fixed point) tercapai. Baris
     * `sumber=manual` tidak pernah disentuh.
     *
     * **Titik tetap TERKECIL, dibangun ulang tiap panggilan (2026-10-16).**
     * Baris `cascade` dan `ciri_bersama` TIDAK dipakai sebagai titik awal -
     * keduanya mulai dari "tidak tercentang" lalu diturunkan ulang dari
     * baris manual + hasil ukur. Dulu loop mulai dari state tersimpan, jadi
     * B yang tercentang karena A tetap tercentang setelah A batal (mis. ukuran
     * dikoreksi) karena rule indikator_checked hanya bisa true/null - dan
     * dua Indikator yang saling bergantung bisa saling mengunci. Baris
     * `auto` tetap jadi titik awal: hasil ukur yang belum lengkap (null)
     * membiarkan keputusan auto terakhir apa adanya, seperti sebelumnya.
     *
     * **Berhenti dalam <= N+1 iterasi**: setelah pass pertama, hasil rule
     * measurement stabil (measurement_readings tidak berubah selama satu
     * panggilan), rule indikator_checked hanya true/null, dan ciri bersama
     * hanya bergantung pada status non-ciri - jadi status hanya bisa naik
     * (false -> true) setelah pass pertama.
     *
     * **Ciri bersama** (config checklist.ciri_bersama): Indikator bernama sama
     * di aspek lain (App\Support\CiriKey) ikut tercentang bila salah satu
     * kembarannya tercentang oleh sumber selain ciri bersama. Kembaran yang
     * di-uncheck manual oleh grafolog tetap tidak tercentang.
     */
    public function evaluateSample(HandwritingSample $sample): void
    {
        $readings = $sample->measurementReadings()->get(['variable_id', 'nilai', 'nilai_min', 'nilai_max']);
        $values = $readings->pluck('nilai', 'variable_id')->all();
        // Selisih (range) = nilai_max - nilai_min, dihitung di sini - bukan
        // kolom tersimpan - dipakai rule bertipe variable_a_value_mode=range
        // (ambang "Range is more than..." dari user, lihat IrregularityRuleSeeder).
        $ranges = $readings
            ->filter(fn ($r) => $r->nilai_min !== null && $r->nilai_max !== null)
            ->mapWithKeys(fn ($r) => [$r->variable_id => $r->nilai_max - $r->nilai_min])
            ->all();

        $indikatorList = Indikator::with(['rules.variableA.kategori', 'rules.variableB', 'rules.dependsOnIndikator'])
            ->get()
            ->keyBy('id');

        $existing = $sample->indikatorChecks()->get()->keyBy('indikator_id');

        $ciriGroups = config('checklist.ciri_bersama', true) ? CiriKey::groups($indikatorList) : [];

        // State kerja: indikator_id => [checked, sumber, rule_id, reason].
        // Manual & auto jadi titik awal; cascade/ciri_bersama diturunkan ulang.
        $state = [];
        foreach ($existing as $id => $row) {
            if (in_array($row->sumber, ['manual', 'auto'], true)) {
                $state[$id] = [(bool) $row->checked, $row->sumber, $row->rule_id, $row->keterangan_pemicu];
            }
        }
        $isManual = fn (int $id) => ($state[$id][1] ?? null) === 'manual';

        $maxIterations = $indikatorList->count() + 1;
        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $anyChange = false;
            $checkedMap = array_map(fn ($s) => $s[0], $state);

            foreach ($indikatorList as $id => $indikator) {
                if ($isManual($id)) {
                    continue; // keputusan manual beku, tidak pernah ditimpa
                }

                $eval = $this->evaluateIndikator($indikator, $values, $ranges, $checkedMap);
                $current = $state[$id] ?? null;

                if ($eval['result'] === true) {
                    $next = [true, $eval['sumber'], $eval['rule_id'], $eval['reason']];
                } elseif ($eval['result'] === false && ($current[1] ?? null) !== 'ciri_bersama') {
                    $next = [false, 'auto', null, null];
                } else {
                    continue; // data belum cukup / dipegang ciri bersama - biarkan
                }

                if ($current !== $next) {
                    $state[$id] = $next;
                    $checkedMap[$id] = $next[0];
                    $anyChange = true;
                }
            }

            foreach ($ciriGroups as $ids) {
                $sumberAsli = null;
                foreach ($ids as $id) {
                    if (($state[$id][0] ?? false) && $state[$id][1] !== 'ciri_bersama') {
                        $sumberAsli = $id;
                        break;
                    }
                }
                if ($sumberAsli === null) {
                    continue;
                }
                $kodeAsli = $indikatorList[$sumberAsli]->kode;
                foreach ($ids as $id) {
                    if ($isManual($id) || ($state[$id][0] ?? false)) {
                        continue;
                    }
                    $state[$id] = [true, 'ciri_bersama', null, "Ciri tulisan sama dengan Indikator {$kodeAsli} yang tercentang."];
                    $anyChange = true;
                }
            }

            if (! $anyChange) {
                break;
            }
        }

        foreach ($indikatorList as $id => $indikator) {
            if ($isManual($id)) {
                continue;
            }
            $row = $existing->get($id);
            if (isset($state[$id])) {
                [$checked, $sumber, $ruleId, $reason] = $state[$id];
            } elseif ($row) {
                // Dulu cascade/ciri_bersama, sekarang tidak lagi terturunkan.
                [$checked, $sumber, $ruleId, $reason] = [false, 'auto', null, null];
            } else {
                continue;
            }

            $unchanged = $row
                && $row->checked === $checked
                && $row->sumber === $sumber
                && $row->rule_id == $ruleId
                && $row->keterangan_pemicu === $reason;
            if ($unchanged) {
                continue;
            }

            SampleIndikatorCheck::updateOrCreate(
                ['sample_id' => $sample->id, 'indikator_id' => $id],
                ['checked' => $checked, 'sumber' => $sumber, 'rule_id' => $ruleId, 'keterangan_pemicu' => $reason],
            );
        }
    }

    /**
     * @param  array<int,float>  $values  variable_id => nilai
     * @param  array<int,float>  $ranges  variable_id => selisih (nilai_max - nilai_min)
     * @param  array<int,bool>  $checkedMap  indikator_id => status tercentang saat ini
     * @return array{result: ?bool, reason: ?string, rule_id: ?int, sumber: ?string}
     */
    private function evaluateIndikator(Indikator $indikator, array $values, array $ranges, array $checkedMap): array
    {
        if ($indikator->rules->isEmpty()) {
            return ['result' => null, 'reason' => null, 'rule_id' => null, 'sumber' => null];
        }

        $results = $indikator->rules->map(fn (IndikatorRule $rule) => [
            'rule' => $rule,
            ...$this->evaluateRule($rule, $values, $ranges, $checkedMap),
        ]);

        // Filter dengan closure (strict ===), BUKAN Collection::where('result', ...)
        // - where() membandingkan longgar (==), dan di PHP null == false bernilai
        // true, sehingga aturan yang belum bisa dievaluasi (result: null, data
        // ukur belum lengkap) ikut kehitung sebagai "pasti salah". Bug ditemukan
        // lewat review 2026-08-08.
        $trueOnes = $results->filter(fn ($r) => $r['result'] === true);
        $falseOnes = $results->filter(fn ($r) => $r['result'] === false);

        if ($indikator->rule_group_logic === 'AND') {
            if ($falseOnes->isNotEmpty()) {
                return ['result' => false, 'reason' => null, 'rule_id' => null, 'sumber' => null];
            }
            if ($trueOnes->count() === $results->count()) {
                return $this->winningResult($trueOnes);
            }

            return ['result' => null, 'reason' => null, 'rule_id' => null, 'sumber' => null];
        }

        // OR (default)
        if ($trueOnes->isNotEmpty()) {
            return $this->winningResult($trueOnes);
        }
        if ($falseOnes->count() === $results->count()) {
            return ['result' => false, 'reason' => null, 'rule_id' => null, 'sumber' => null];
        }

        return ['result' => null, 'reason' => null, 'rule_id' => null, 'sumber' => null];
    }

    /**
     * `sumber` dipilih dari rule PEMENANG (yang pertama membuktikan true) -
     * 'cascade' kalau lewat rule indikator_checked (badge "Terkait" di UI,
     * dulu berasal dari tabel cross-reference terpisah), 'auto' kalau lewat
     * measurement (category/comparison) - mempertahankan label UI yang sudah
     * ada meski mekanismenya sekarang satu sistem.
     */
    private function winningResult(Collection $trueOnes): array
    {
        $winning = $trueOnes->first()['rule'];

        return [
            'result' => true,
            'reason' => $trueOnes->pluck('reason')->implode('; '),
            'rule_id' => $winning->id,
            'sumber' => $winning->rule_type === 'indikator_checked' ? 'cascade' : 'auto',
        ];
    }

    /**
     * @param  array<int,float>  $values
     * @param  array<int,float>  $ranges
     * @param  array<int,bool>  $checkedMap
     * @return array{result: ?bool, reason: ?string}
     */
    private function evaluateRule(IndikatorRule $rule, array $values, array $ranges, array $checkedMap): array
    {
        if ($rule->rule_type === 'indikator_checked') {
            $depChecked = $checkedMap[$rule->depends_on_indikator_id] ?? false;
            if (! $depChecked) {
                // Belum tercentang SEKARANG bukan berarti tidak akan pernah -
                // unresolved (null), bukan false, supaya AND-group tidak
                // salah short-circuit ke false saat dependensi belum sempat
                // dievaluasi di iterasi ini (lihat evaluateSample()).
                return ['result' => null, 'reason' => null];
            }

            $dep = $rule->dependsOnIndikator;

            return [
                'result' => true,
                'reason' => "Ikut tercentang karena Indikator {$dep->kode} tercentang.",
            ];
        }

        // Rule kategori selalu pakai nilai titik - konsep "range" cuma
        // relevan untuk perbandingan ambang irregularity (comparison).
        $isRange = $rule->rule_type === 'comparison' && $rule->variable_a_value_mode === 'range';
        $nilaiA = $isRange ? ($ranges[$rule->variable_a_id] ?? null) : ($values[$rule->variable_a_id] ?? null);
        if ($nilaiA === null) {
            return ['result' => null, 'reason' => null];
        }
        $nilaiA = (float) $nilaiA;
        $labelA = $isRange ? "Range {$rule->variableA->nama}" : $rule->variableA->nama;

        if ($rule->rule_type === 'category') {
            $kategori = $rule->variableA->kategoriUntukNilai($nilaiA);
            if (! $kategori) {
                return ['result' => false, 'reason' => "{$labelA}: {$nilaiA} (tidak cocok kategori manapun)"];
            }

            $cocok = strcasecmp(trim($kategori->kategori), trim($rule->category_label)) === 0;

            return ['result' => $cocok, 'reason' => "{$labelA}: {$nilaiA} → {$kategori->kategori}"];
        }

        // comparison
        if ($rule->variable_b_id !== null) {
            $nilaiB = $values[$rule->variable_b_id] ?? null;
            if ($nilaiB === null) {
                return ['result' => null, 'reason' => null];
            }
            $nilaiB = (float) $nilaiB;
            $right = (float) $rule->koefisien * $nilaiB;
            $koefisienPrefix = (float) $rule->koefisien !== 1.0 ? number_format((float) $rule->koefisien, 2).'× ' : '';
            $rightLabel = "{$koefisienPrefix}{$rule->variableB->nama} ({$nilaiB})";
        } else {
            $right = (float) $rule->compare_value;
            $rightLabel = (string) $rule->compare_value;
        }

        $result = match ($rule->operator) {
            'equals' => abs($nilaiA - $right) < 0.0001,
            'greater_than' => $nilaiA > $right,
            'less_than' => $nilaiA < $right,
            'greater_or_equal' => $nilaiA >= $right,
            'less_or_equal' => $nilaiA <= $right,
            default => false,
        };

        $opSymbol = [
            'equals' => '=', 'greater_than' => '>', 'less_than' => '<',
            'greater_or_equal' => '≥', 'less_or_equal' => '≤',
        ][$rule->operator] ?? $rule->operator;

        return ['result' => $result, 'reason' => "{$labelA}: {$nilaiA} {$opSymbol} {$rightLabel}"];
    }

    /**
     * Skor per Aspek = jumlah posisi (1-10) unik yang punya minimal 1
     * Indikator tercentang (varian a/b/c di-OR-kan dalam 1 posisi yang
     * sama - sesuai rencana KM §3.2). Diclamp ke 1-10 karena
     * ScoringEngineService::generate() tidak menerima 0 - lihat CLAUDE.md.
     *
     * @return array<string, array{skor:int, posisi_tercentang:int, total_posisi:int}>
     */
    public function tallyPerAspek(HandwritingSample $sample): array
    {
        $checks = $sample->indikatorChecks()->where('checked', true)->with('indikator')->get();

        $posisiByAspek = [];
        foreach ($checks as $check) {
            $ind = $check->indikator;
            $posisiByAspek[$ind->aspek_id][$ind->posisi] = true;
        }

        $totalPosisiByAspek = Indikator::select('aspek_id', DB::raw('COUNT(DISTINCT posisi) as total'))
            ->groupBy('aspek_id')
            ->pluck('total', 'aspek_id');

        $result = [];
        foreach (Aspek::all() as $aspek) {
            $checkedCount = count($posisiByAspek[$aspek->id] ?? []);
            $result[$aspek->kode] = [
                'skor' => max(1, min(10, $checkedCount)),
                'posisi_tercentang' => $checkedCount,
                'total_posisi' => (int) ($totalPosisiByAspek[$aspek->id] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Bentuk lengkap untuk layar checklist grafolog: dikelompokkan
     * Sindrom -> Aspek -> Indikator, plus tally per Aspek. Memanggil
     * evaluateSample() dulu supaya hasil ukur terbaru selalu ter-refresh.
     */
    public function checklistFor(HandwritingSample $sample): array
    {
        $this->evaluateSample($sample);

        $checks = $sample->indikatorChecks()->get()->keyBy('indikator_id');
        $tally = $this->tallyPerAspek($sample);

        $indikatorList = Indikator::with('aspek.sindrom')
            ->orderBy('aspek_id')->orderBy('posisi')->orderBy('varian')
            ->get();

        $sindromMap = [];
        foreach ($indikatorList as $ind) {
            $aspek = $ind->aspek;
            $sindrom = $aspek->sindrom;

            $sindromMap[$sindrom->id]['sindrom'] ??= [
                'id' => $sindrom->id, 'kode_romawi' => $sindrom->kode_romawi, 'nama' => $sindrom->nama,
            ];
            $sindromMap[$sindrom->id]['aspek'][$aspek->id] ??= [
                'id' => $aspek->id, 'kode' => $aspek->kode, 'nama' => $aspek->nama,
                'skor' => $tally[$aspek->kode]['skor'] ?? 1,
                'posisi_tercentang' => $tally[$aspek->kode]['posisi_tercentang'] ?? 0,
                'total_posisi' => $tally[$aspek->kode]['total_posisi'] ?? 0,
                'indikator' => [],
            ];

            $check = $checks->get($ind->id);
            $sindromMap[$sindrom->id]['aspek'][$aspek->id]['indikator'][] = [
                'id' => $ind->id, 'kode' => $ind->kode, 'posisi' => $ind->posisi, 'varian' => $ind->varian,
                'nama' => $ind->nama,
                'checked' => $check?->checked ?? false,
                'sumber' => $check?->sumber,
                'keterangan_pemicu' => $check?->keterangan_pemicu,
            ];
        }

        $sindromList = [];
        foreach ($sindromMap as $entry) {
            $sindromList[] = $entry['sindrom'] + ['aspek' => array_values($entry['aspek'])];
        }

        return ['sindrom' => $sindromList];
    }

    /**
     * Toggle manual 1 Indikator. Centang: langsung tersimpan (checked=true,
     * sumber=manual), lalu evaluateSample() dipanggil supaya rule
     * indikator_checked yang depends_on Indikator ini langsung ikut
     * tercentang (dulu applyCascadeFrom() terpisah - sekarang cuma
     * evaluasi ulang biasa, sudah mencakup itu). Uncheck: baris TETAP
     * disimpan dengan checked=false (bukan dihapus) - supaya evaluateSample()
     * berikutnya tahu ini sudah pernah diputuskan grafolog dan tidak
     * mencentangnya lagi otomatis. Kalau baris ini dulu memicu Indikator
     * lain (lewat rule indikator_checked) yang masih tercentang, TIDAK
     * otomatis ikut di-uncheck (sesuai rencana KM §3.3) - dikembalikan
     * sebagai cascade_candidates supaya frontend bisa menawarkan pilihan
     * eksplisit. $confirmed HARUS true untuk melanjutkan setelah prompt itu
     * ditampilkan - $alsoUncheckCascaded yang kosong TIDAK cukup sebagai
     * sinyal "sudah dijawab, tolak" karena itu juga nilai default sebelum
     * grafolog sempat menjawab sama sekali (bug ditemukan lewat review
     * 2026-08-08). Target yang ikut di-uncheck lewat $alsoUncheckCascaded
     * DIBEKUKAN jadi sumber=manual juga (2026-08-19) - kalau tidak, karena
     * baris `cascade` sekarang direkonsiliasi ulang seperti `auto` (lihat
     * evaluateSample()), 1 rule OR dengan sumber lain yang masih valid bisa
     * diam-diam mencentangnya lagi, membatalkan keputusan grafolog barusan.
     *
     * @param  int[]  $alsoUncheckCascaded
     * @return array{ok:bool, requires_confirmation?:bool, cascade_candidates?:array}
     */
    public function toggle(HandwritingSample $sample, int $indikatorId, bool $checked, array $alsoUncheckCascaded = [], bool $confirmed = false): array
    {
        $indikator = Indikator::findOrFail($indikatorId);

        if ($checked) {
            SampleIndikatorCheck::updateOrCreate(
                ['sample_id' => $sample->id, 'indikator_id' => $indikator->id],
                ['checked' => true, 'sumber' => 'manual', 'rule_id' => null, 'keterangan_pemicu' => null],
            );

            $this->evaluateSample($sample);

            return ['ok' => true];
        }

        $dependentRuleIds = IndikatorRule::where('rule_type', 'indikator_checked')
            ->where('depends_on_indikator_id', $indikator->id)
            ->pluck('id');
        $cascadeCandidates = $sample->indikatorChecks()
            ->whereIn('rule_id', $dependentRuleIds)
            ->where('checked', true)
            ->with('indikator')
            ->get();

        if ($cascadeCandidates->isNotEmpty() && ! $confirmed) {
            return [
                'ok' => false,
                'requires_confirmation' => true,
                'cascade_candidates' => $cascadeCandidates->map(fn ($c) => [
                    'id' => $c->indikator->id, 'kode' => $c->indikator->kode, 'nama' => $c->indikator->nama,
                ])->values()->all(),
            ];
        }

        SampleIndikatorCheck::updateOrCreate(
            ['sample_id' => $sample->id, 'indikator_id' => $indikator->id],
            ['checked' => false, 'sumber' => 'manual', 'rule_id' => null, 'keterangan_pemicu' => null],
        );
        if ($alsoUncheckCascaded !== []) {
            $sample->indikatorChecks()->whereIn('indikator_id', $alsoUncheckCascaded)
                ->update(['checked' => false, 'sumber' => 'manual', 'rule_id' => null, 'keterangan_pemicu' => null]);
        }
        // Kandidat yang DIPERTAHANKAN grafolog dibekukan jadi manual-tercentang:
        // evaluateSample() membangun ulang baris cascade dari nol, jadi tanpa
        // ini target ikut batal walau grafolog memilih mempertahankannya.
        $kept = $cascadeCandidates->reject(fn ($c) => in_array($c->indikator_id, $alsoUncheckCascaded));
        foreach ($kept as $c) {
            $c->update([
                'checked' => true, 'sumber' => 'manual', 'rule_id' => null,
                'keterangan_pemicu' => "Dipertahankan grafolog saat Indikator {$indikator->kode} dibatalkan.",
            ]);
        }

        $this->evaluateSample($sample);

        return ['ok' => true];
    }
}
