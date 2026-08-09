<?php

namespace Database\Seeders;

use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Aturan operator "irregular/regular" - dianalisis dari nama 45 Indikator
 * yang mengandung kata "regular" terhadap 20 variabel ukur yang diberikan
 * user (2026-08-08), dicocokkan lewat pembahasan langsung (bukan tebakan
 * otomatis) - lihat CLAUDE.md "Aturan Irregularity" untuk daftar penuh &
 * kenapa sebagian Indikator sengaja TIDAK diberi aturan di sini.
 *
 * Idempoten (updateOrCreate) - aman dijalankan ulang. Bukan bagian dari
 * `GrafologiKnowledgeSeeder` karena itu khusus data dari JSON sumber Excel;
 * ini analisis baru dari sesi ini, bukan konten Excel asli.
 */
class IrregularityRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $var = fn (string $nama) => MeasurementVariable::where('nama', $nama)->firstOrFail()->id;
            $ind = fn (string $kode) => Indikator::where('kode', $kode)->firstOrFail()->id;

            $mzh = $var('Middle zone height');
            $ovalsHeight = $var('Ovals height');
            $upperZoneHeight = $var('Upper zone height (ratio)');
            $lowerZoneLength = $var('Lower zone length (ratio)');
            $mzOvalsWidth = $var('M, Z, Ovals width');
            $letterSpacing = $var('Letter Spacing');
            $wordSpacing = $var('Word Spacing');
            $extensionSpacing = $var('Extension Spacing');
            $mzUpslant = $var('Middle zone – upslant');
            $mzDownslant = $var('Middle zone – downslant');

            // Setiap baris: [kode indikator, variable_a, operator, koefisien|null, variable_b|null, compare_value|null]
            $simpleRules = [
                // Ovals height irregular - >1x MZH
                ['27-5b', $ovalsHeight, 'greater_than', 1.0, $mzh, null],
                // Upper zone height irregular - >1.5x MZH
                ['27-9', $upperZoneHeight, 'greater_than', 1.5, $mzh, null],
                ['38-3c', $upperZoneHeight, 'greater_than', 1.5, $mzh, null],
                // Lower zone length irregular - >1.5x MZH
                ['27-4', $lowerZoneLength, 'greater_than', 1.5, $mzh, null],
                ['38-3b', $lowerZoneLength, 'greater_than', 1.5, $mzh, null],
                // Middle zone width irregular / Ovals width irregular - keduanya berbagi
                // variabel gabungan "M, Z, Ovals width" (kode 15 & 16 di sumber) - >1x MZH
                ['27-6a', $mzOvalsWidth, 'greater_than', 1.0, $mzh, null],
                ['38-4a', $mzOvalsWidth, 'greater_than', 1.0, $mzh, null],
                ['27-6b', $mzOvalsWidth, 'greater_than', 1.0, $mzh, null],
                ['38-4b', $mzOvalsWidth, 'greater_than', 1.0, $mzh, null],
                // Letter spacing irregular - >0.5x MZH
                ['27-3a', $letterSpacing, 'greater_than', 0.5, $mzh, null],
                ['38-4d', $letterSpacing, 'greater_than', 0.5, $mzh, null],
                // Word spacing irregular - >1x MZH
                ['27-10', $wordSpacing, 'greater_than', 1.0, $mzh, null],
                ['38-4c', $wordSpacing, 'greater_than', 1.0, $mzh, null],
                // Middle zone upslant irregular - >30 derajat (compare_value tetap)
                ['27-8a', $mzUpslant, 'greater_than', null, null, 30],
                ['36-8b-dup2', $mzUpslant, 'greater_than', null, null, 30],
                ['38-3d', $mzUpslant, 'greater_than', null, null, 30],
                ['40-8', $mzUpslant, 'greater_than', null, null, 30],
                // Middle zone downslant irregular - >15 derajat
                ['26-6a', $mzDownslant, 'greater_than', null, null, 15],
                ['27-8b', $mzDownslant, 'greater_than', null, null, 15],

                // --- "regular" (kebalikan irregular, <= ambang yang sama) ---
                ['13-3a', $letterSpacing, 'less_or_equal', 0.5, $mzh, null],
                ['13-3b', $wordSpacing, 'less_or_equal', 1.0, $mzh, null],
                ['14-1e', $wordSpacing, 'less_or_equal', 1.0, $mzh, null],
                ['14-1b', $mzUpslant, 'less_or_equal', null, null, 30],
                ['14-1c', $mzDownslant, 'less_or_equal', null, null, 15],
            ];

            foreach ($simpleRules as [$kode, $varA, $operator, $koefisien, $varB, $compareValue]) {
                IndikatorRule::updateOrCreate(
                    [
                        'indikator_id' => $ind($kode),
                        'rule_type' => 'comparison',
                        'variable_a_id' => $varA,
                        'operator' => $operator,
                        'variable_b_id' => $varB,
                        'compare_value' => $compareValue,
                    ],
                    ['koefisien' => $koefisien ?? 1.0],
                );
            }

            // Extension spacing irregular (27-2): >4mm ATAU >1x MZH (OR, default logic).
            $extIrregular = Indikator::find($ind('27-2'));
            $extIrregular->update(['rule_group_logic' => 'OR']);
            IndikatorRule::updateOrCreate(
                ['indikator_id' => $extIrregular->id, 'rule_type' => 'comparison', 'variable_a_id' => $extensionSpacing, 'operator' => 'greater_than', 'variable_b_id' => null, 'compare_value' => 4],
                ['koefisien' => 1.0],
            );
            IndikatorRule::updateOrCreate(
                ['indikator_id' => $extIrregular->id, 'rule_type' => 'comparison', 'variable_a_id' => $extensionSpacing, 'operator' => 'greater_than', 'variable_b_id' => $mzh, 'compare_value' => null],
                ['koefisien' => 1.0],
            );

            // Extension spacing regular (14-1d): <=4mm DAN <=1x MZH (AND - kebalikan De Morgan dari OR di atas).
            $extRegular = Indikator::find($ind('14-1d'));
            $extRegular->update(['rule_group_logic' => 'AND']);
            IndikatorRule::updateOrCreate(
                ['indikator_id' => $extRegular->id, 'rule_type' => 'comparison', 'variable_a_id' => $extensionSpacing, 'operator' => 'less_or_equal', 'variable_b_id' => null, 'compare_value' => 4],
                ['koefisien' => 1.0],
            );
            IndikatorRule::updateOrCreate(
                ['indikator_id' => $extRegular->id, 'rule_type' => 'comparison', 'variable_a_id' => $extensionSpacing, 'operator' => 'less_or_equal', 'variable_b_id' => $mzh, 'compare_value' => null],
                ['koefisien' => 1.0],
            );
        });
    }
}
