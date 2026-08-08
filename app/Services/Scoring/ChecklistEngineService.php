<?php

namespace App\Services\Scoring;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\Indikator;
use App\Models\IndikatorCrossReference;
use App\Models\IndikatorRule;
use App\Models\SampleIndikatorCheck;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KM-G: mengevaluasi indikator_rules terhadap measurement_readings 1 sample
 * untuk auto-centang Indikator (measurement worksheet -> checklist), lalu
 * menghitung skor per Aspek dari jumlah posisi tercentang. Sumber kebenaran
 * status "tercentang" adalah tabel sample_indikator_checks - lihat migrasi
 * & CLAUDE.md. Baris `sumber=manual`/`cascade` beku begitu diputuskan
 * (keputusan grafolog harus tahan re-evaluasi); baris `sumber=auto`
 * SENGAJA terus direkonsiliasi ulang setiap evaluateSample() dipanggil,
 * supaya koreksi hasil ukur tidak meninggalkan centang/alasan yang basi
 * (bug ditemukan lewat review 2026-08-08 - versi awal melewati baris auto
 * yang sudah ada juga, bukan cuma yang manual).
 */
class ChecklistEngineService
{
    /**
     * Jalankan aturan operator utk semua Indikator, buat/perbarui baris
     * `sumber=auto` sesuai hasil terbaru, lalu terapkan cascade referensi
     * silang (satu hop) dari yang baru transisi ke tercentang. Baris
     * `manual`/`cascade` tidak pernah disentuh. Aman dipanggil berkali-kali
     * (idempoten - re-run tanpa perubahan data ukur tidak menulis apa pun).
     */
    public function evaluateSample(HandwritingSample $sample): void
    {
        $values = $sample->measurementReadings()->pluck('nilai', 'variable_id')->all();

        $indikatorList = Indikator::with(['rules.variableA.kategori', 'rules.variableB'])
            ->get()
            ->keyBy('id');

        $existing = $sample->indikatorChecks()->get()->keyBy('indikator_id');

        $newlyCheckedIds = [];
        foreach ($indikatorList as $indikator) {
            $row = $existing->get($indikator->id);
            if ($row && $row->sumber !== 'auto') {
                continue; // keputusan manual/cascade beku, tidak pernah ditimpa
            }

            $eval = $this->evaluateIndikator($indikator, $values);
            if ($eval['result'] === null) {
                continue; // data belum cukup - biarkan state terakhir apa adanya
            }

            $nowChecked = $eval['result'] === true;
            $wasChecked = $row?->checked ?? false;
            $unchanged = $row
                && $row->checked === $nowChecked
                && $row->rule_id === $eval['rule_id']
                && $row->keterangan_pemicu === $eval['reason'];
            if ($unchanged) {
                continue;
            }

            $check = SampleIndikatorCheck::updateOrCreate(
                ['sample_id' => $sample->id, 'indikator_id' => $indikator->id],
                [
                    'checked' => $nowChecked,
                    'sumber' => 'auto',
                    'rule_id' => $nowChecked ? $eval['rule_id'] : null,
                    'keterangan_pemicu' => $nowChecked ? $eval['reason'] : null,
                ],
            );
            $existing->put($indikator->id, $check);

            if (! $wasChecked && $nowChecked) {
                $newlyCheckedIds[] = $indikator->id;
            }
        }

        foreach ($newlyCheckedIds as $indikatorId) {
            $this->applyCascadeFrom($sample, $indikatorList[$indikatorId], $existing);
        }
    }

    /**
     * Cascade satu-arah, satu-hop saja (bukan rekursif) dari 1 Indikator
     * sumber yang baru tercentang ke target referensi silangnya yang
     * aktif+matched - sesuai rencana KM §3.3. Tidak pernah menimpa baris
     * yang sudah ada (baik manual maupun auto/cascade sebelumnya).
     */
    private function applyCascadeFrom(HandwritingSample $sample, Indikator $source, Collection $existing): void
    {
        $refs = IndikatorCrossReference::where('indikator_sumber_id', $source->id)
            ->where('aktif', true)
            ->where('match_status', 'matched')
            ->get();

        foreach ($refs as $ref) {
            $target = Indikator::where('kode', $ref->mereferensikan_ke_kode)->first();
            if (! $target || $existing->has($target->id)) {
                continue;
            }

            $check = SampleIndikatorCheck::create([
                'sample_id' => $sample->id,
                'indikator_id' => $target->id,
                'checked' => true,
                'sumber' => 'cascade',
                'cross_reference_id' => $ref->id,
                'keterangan_pemicu' => "Ikut tercentang karena Indikator {$source->kode} tercentang (referensi silang).",
            ]);
            $existing->put($target->id, $check);
        }
    }

    /**
     * @param  array<int,float>  $values  variable_id => nilai
     * @return array{result: ?bool, reason: ?string, rule_id: ?int}
     */
    private function evaluateIndikator(Indikator $indikator, array $values): array
    {
        if ($indikator->rules->isEmpty()) {
            return ['result' => null, 'reason' => null, 'rule_id' => null];
        }

        $results = $indikator->rules->map(fn (IndikatorRule $rule) => [
            'rule' => $rule,
            ...$this->evaluateRule($rule, $values),
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
                return ['result' => false, 'reason' => null, 'rule_id' => null];
            }
            if ($trueOnes->count() === $results->count()) {
                return [
                    'result' => true,
                    'reason' => $trueOnes->pluck('reason')->implode('; '),
                    'rule_id' => $trueOnes->first()['rule']->id,
                ];
            }

            return ['result' => null, 'reason' => null, 'rule_id' => null];
        }

        // OR (default)
        if ($trueOnes->isNotEmpty()) {
            return [
                'result' => true,
                'reason' => $trueOnes->pluck('reason')->implode('; '),
                'rule_id' => $trueOnes->first()['rule']->id,
            ];
        }
        if ($falseOnes->count() === $results->count()) {
            return ['result' => false, 'reason' => null, 'rule_id' => null];
        }

        return ['result' => null, 'reason' => null, 'rule_id' => null];
    }

    /**
     * @param  array<int,float>  $values
     * @return array{result: ?bool, reason: ?string}
     */
    private function evaluateRule(IndikatorRule $rule, array $values): array
    {
        $nilaiA = $values[$rule->variable_a_id] ?? null;
        if ($nilaiA === null) {
            return ['result' => null, 'reason' => null];
        }
        $nilaiA = (float) $nilaiA;

        if ($rule->rule_type === 'category') {
            $kategori = $rule->variableA->kategoriUntukNilai($nilaiA);
            if (! $kategori) {
                return ['result' => false, 'reason' => "{$rule->variableA->nama}: {$nilaiA} (tidak cocok kategori manapun)"];
            }

            $cocok = strcasecmp(trim($kategori->kategori), trim($rule->category_label)) === 0;

            return ['result' => $cocok, 'reason' => "{$rule->variableA->nama}: {$nilaiA} → {$kategori->kategori}"];
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

        return ['result' => $result, 'reason' => "{$rule->variableA->nama}: {$nilaiA} {$opSymbol} {$rightLabel}"];
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
     * sumber=manual) + cascade satu-hop dijalankan. Uncheck: baris TETAP
     * disimpan dengan checked=false (bukan dihapus) - supaya evaluateSample()
     * berikutnya tahu ini sudah pernah diputuskan grafolog dan tidak
     * mencentangnya lagi otomatis. Kalau baris ini dulu memicu cascade ke
     * Indikator lain yang masih tercentang, TIDAK otomatis ikut di-uncheck
     * (sesuai rencana KM §3.3) - dikembalikan sebagai cascade_candidates
     * supaya frontend bisa menawarkan pilihan eksplisit. $confirmed HARUS
     * true untuk melanjutkan setelah prompt itu ditampilkan - $alsoUncheckCascaded
     * yang kosong TIDAK cukup sebagai sinyal "sudah dijawab, tolak" karena
     * itu juga nilai default sebelum grafolog sempat menjawab sama sekali
     * (bug ditemukan lewat review 2026-08-08: sebelum ada $confirmed,
     * menolak cascade selalu memicu ulang prompt yang sama, tidak pernah
     * benar-benar meng-uncheck sumbernya).
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
                ['checked' => true, 'sumber' => 'manual', 'rule_id' => null, 'cross_reference_id' => null, 'keterangan_pemicu' => null],
            );

            $existing = $sample->indikatorChecks()->get()->keyBy('indikator_id');
            $this->applyCascadeFrom($sample, $indikator, $existing);

            return ['ok' => true];
        }

        $refIds = IndikatorCrossReference::where('indikator_sumber_id', $indikator->id)->pluck('id');
        $cascadeCandidates = $sample->indikatorChecks()
            ->whereIn('cross_reference_id', $refIds)
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
            ['checked' => false, 'sumber' => 'manual', 'rule_id' => null, 'cross_reference_id' => null, 'keterangan_pemicu' => null],
        );
        if ($alsoUncheckCascaded !== []) {
            $sample->indikatorChecks()->whereIn('indikator_id', $alsoUncheckCascaded)->update(['checked' => false]);
        }

        return ['ok' => true];
    }
}
