<?php

namespace Database\Seeders;

use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 66 aturan tipe 'category', dianalisis 2026-08-08: Indikator yang namanya
 * PERSIS SAMA dengan "[nama Variabel Ukur] [nama kategori]" milik variabel
 * itu (mis. Indikator "Middle zone height large" == Variabel "Middle zone
 * height" @ kategori "large"). Dicari lewat pencocokan string persis
 * (case-insensitive) terhadap 34 Variabel Ukur x kategorinya masing-masing,
 * BUKAN tebakan - daftar di bawah adalah hasil pencocokan itu, dibekukan
 * di sini (bukan dihitung ulang tiap seed) supaya perubahan KB nanti tidak
 * diam-diam mengubah aturan yang sudah direview.
 *
 * Beberapa Indikator dengan nama sama muncul di banyak Aspek berbeda (mis.
 * "Middle zone height large" ada di 5 Aspek) - itu memang benar, 1 ciri
 * fisik yang sama jadi bukti untuk beberapa trait kepribadian tergantung
 * konteks Aspek-nya, bukan duplikat keliru.
 *
 * Idempoten (updateOrCreate). Terpisah dari `IrregularityRuleSeeder` -
 * batch analisis berbeda, didokumentasikan sendiri-sendiri.
 */
class CategoryMatchRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // [kode indikator, nama variabel, label kategori]
            $matches = [
                ['37-6b', 'Middle zone height', 'extremely small'],
                ['31-7', 'Middle zone height', 'very small'],
                ['37-6a', 'Middle zone height', 'very small'],
                ['02-8a', 'Middle zone height', 'large'],
                ['04-5a', 'Middle zone height', 'large'],
                ['11-2b', 'Middle zone height', 'large'],
                ['23-4a', 'Middle zone height', 'large'],
                ['35-8-dup3', 'Middle zone height', 'large'],
                ['02-8b', 'Middle zone height', 'very large'],
                ['12-4a', 'Middle zone height', 'very large'],
                ['35-8-dup4', 'Middle zone height', 'very large'],
                ['12-4b', 'Middle zone height', 'extremely large'],
                ['35-8-dup5', 'Middle zone height', 'extremely large'],
                ['40-7', 'Middle zone height', 'extremely large'],
                ['35-6-dup7', 't-bar height', 'very low'],
                ['37-1b', 't-bar height', 'very low'],
                ['35-6-dup6', 't-bar height', 'low'],
                ['11-10a', 't-bar height', 'balanced'],
                ['04-9a', 't-bar height', 'high'],
                ['11-10b', 't-bar height', 'high'],
                ['11-10c', 't-bar height', 'very high'],
                ['37-1a', 'Diacriticals height', 'very low'],
                ['04-2a', 'Diacriticals height', 'high'],
                ['04-2b', 'Diacriticals height', 'very high'],
                ['35-6-dup2', 'Initial height', 'very low'],
                ['29-4a', 'Extension Spacing', 'very narrow'],
                ['36-3', 'Extension Spacing', 'very narrow'],
                ['40-4a', 'Extension Spacing', 'very narrow'],
                ['18-2a', 'Extension Spacing', 'narrow'],
                ['06-10c', 'Extension Spacing', 'balanced'],
                ['24-3', 'Extension Spacing', 'balanced'],
                ['25-2', 'Extension Spacing', 'balanced'],
                ['06-10d', 'Extension Spacing', 'broad'],
                ['25-2-dup2', 'Extension Spacing', 'broad'],
                ['32-4a', 'Extension Spacing', 'very broad'],
                ['37-2a', 'Extension Spacing', 'very broad'],
                ['32-4b', 'Extension Spacing', 'extremely broad'],
                ['29-9', 't-bar length', 'extremely long'],
                ['30-7b', 'Letter Spacing', 'very narrow'],
                ['35-8', 'Letter Spacing', 'very narrow'],
                ['35-10', 'Letter Spacing', 'very narrow'],
                ['37-3c', 'Letter Spacing', 'very narrow'],
                ['15-6', 'Letter Spacing', 'narrow'],
                ['17-4a', 'Letter Spacing', 'balanced'],
                ['18-5a', 'Letter Spacing', 'balanced'],
                ['19-5a', 'Letter Spacing', 'balanced'],
                ['17-4b', 'Letter Spacing', 'broad'],
                ['18-5b', 'Letter Spacing', 'broad'],
                ['19-5b', 'Letter Spacing', 'broad'],
                ['20-5b', 'Letter Spacing', 'broad'],
                ['03-5', 'Letter Spacing', 'very broad'],
                ['40-5', 'Letter Spacing', 'very broad'],
                ['03-10b', 'Word Spacing', 'very narrow'],
                ['03-10a', 'Word Spacing', 'narrow'],
                ['18-10b', 'Word Spacing', 'balanced'],
                ['19-10b', 'Word Spacing', 'balanced'],
                ['25-10', 'Word Spacing', 'balanced'],
                ['06-10b', 'Word Spacing', 'broad'],
                ['25-10-dup2', 'Word Spacing', 'broad'],
                ['32-10a', 'Word Spacing', 'very broad'],
                ['37-2b', 'Word Spacing', 'very broad'],
                ['32-10b', 'Word Spacing', 'extremely broad'],
                ['37-3b', 'Horizontal Spacing', 'very narrow'],
                ['37-3a', 'Horizontal Spacing', 'narrow'],
                ["02-02'", 'Horizontal Spacing', 'broad'],
                ['15-4a', 'Ductus width', 'sharp'],
            ];

            $variableIdByName = MeasurementVariable::pluck('id', 'nama');

            foreach ($matches as [$kode, $variableNama, $kategoriLabel]) {
                $indikatorId = Indikator::where('kode', $kode)->value('id');
                $variableId = $variableIdByName[$variableNama] ?? null;

                if (! $indikatorId || ! $variableId) {
                    continue; // KB berubah sejak analisis - lewati, jangan pecah seluruh seed
                }

                IndikatorRule::updateOrCreate(
                    [
                        'indikator_id' => $indikatorId,
                        'rule_type' => 'category',
                        'variable_a_id' => $variableId,
                        'category_label' => $kategoriLabel,
                    ],
                    [],
                );
            }
        });
    }
}
