<?php

namespace Database\Seeders;

use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 7 aturan tipe 'comparison' (operator equals, koefisien 1.0), dianalisis
 * 2026-08-08 dari Indikator yang namanya membandingkan 2 Variabel Ukur
 * secara eksplisit ("X equals Y"). Bagian dari batch analisis nama
 * Indikator yang sama dengan `IrregularityRuleSeeder`/
 * `CategoryMatchRuleSeeder` tapi disimpan terpisah karena polanya beda
 * (perbandingan 2 variabel, bukan 1 variabel vs kategori/ambang).
 *
 * SENGAJA TIDAK termasuk (dari 28 Indikator berbahasa perbandingan yang
 * diperiksa, cuma 7 ini yang jelas): perbandingan tanda tangan vs teks (9
 * Indikator, tidak ada variabel terpisah untuk itu), margin kiri/kanan
 * atau atas/bawah (3 Indikator, datanya sudah digabung 1 kolom di
 * `measurement_variable`, tidak bisa dibandingkan sisi-ke-sisi), bentuk
 * huruf spesifik (4 Indikator, terlalu spesifik untuk variabel umum),
 * "Initials in signature higher than capitals in text" (1, konteks
 * "in signature/in text" ambigu terhadap variabel yang ada), "Signature
 * middle zone height larger than middle zone height mode" (1, butuh
 * variabel terpisah yang tidak ada), "Alignment ascending more than 10°"
 * (1, konvensi tanda/arah "ascending" belum dikonfirmasi user), dan
 * "Score for Mental Orientation ... higher than score for Physical
 * Energy" (1, PERBANDINGAN SKOR ASPEK bukan measurement mentah - di luar
 * kemampuan skema `indikator_rules` saat ini sama sekali).
 *
 * Idempoten (updateOrCreate).
 */
class VariableEqualityRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $mzOvalsWidth = MeasurementVariable::where('nama', 'M, Z, Ovals width')->firstOrFail()->id;
            $mzh = MeasurementVariable::where('nama', 'Middle zone height')->firstOrFail()->id;
            $ovalsHeight = MeasurementVariable::where('nama', 'Ovals height')->firstOrFail()->id;

            // "Middle zone width equals middle zone height"
            foreach (['11-1a', '13-4a', '17-7', '20-6'] as $kode) {
                $this->equalsRule($kode, $mzOvalsWidth, $mzh);
            }

            // "Ovals width equals ovals height"
            foreach (['11-4', '13-4b', '17-8'] as $kode) {
                $this->equalsRule($kode, $mzOvalsWidth, $ovalsHeight);
            }
        });
    }

    private function equalsRule(string $kode, int $variableAId, int $variableBId): void
    {
        $indikatorId = Indikator::where('kode', $kode)->value('id');
        if (! $indikatorId) {
            return;
        }

        IndikatorRule::updateOrCreate(
            [
                'indikator_id' => $indikatorId,
                'rule_type' => 'comparison',
                'variable_a_id' => $variableAId,
                'operator' => 'equals',
                'variable_b_id' => $variableBId,
            ],
            ['koefisien' => 1.0],
        );
    }
}
