<?php

namespace Tests\Unit;

use App\Services\Scoring\ScoringEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ScoringEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();

        $hijauId = DB::table('sindrom')->insertGetId([
            'kode_romawi' => 'I', 'nama' => 'Driving Forces',
            'polaritas_inferred' => 'HIJAU', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $merahId = DB::table('sindrom')->insertGetId([
            'kode_romawi' => 'VI', 'nama' => 'Stress Vulnerability',
            'polaritas_inferred' => 'MERAH', 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('aspek')->insert([
            [
                'kode' => '01', 'sindrom_id' => $hijauId, 'nama' => 'Authoritarian',
                'keterangan_umum' => 'umum',
                'narasi_very_high' => 'sangat tinggi teks',
                'narasi_high' => 'tinggi teks',
                'narasi_medium' => 'sedang teks',
                'narasi_low' => 'rendah teks',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'kode' => '02', 'sindrom_id' => $hijauId, 'nama' => 'Assertive',
                'keterangan_umum' => 'umum',
                'narasi_very_high' => 'assertive sangat tinggi',
                'narasi_high' => 'assertive tinggi',
                'narasi_medium' => 'assertive sedang',
                'narasi_low' => 'assertive rendah',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'kode' => '30', 'sindrom_id' => $merahId, 'nama' => 'Anxiety',
                'keterangan_umum' => 'umum',
                'narasi_very_high' => 'anxiety sangat tinggi',
                'narasi_high' => 'anxiety tinggi',
                'narasi_medium' => 'anxiety sedang',
                'narasi_low' => 'anxiety rendah',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $bands = [
            ['polaritas' => 'HIJAU', 'label' => 'Nilai Rendah', 'rentang_skor' => '1-3'],
            ['polaritas' => 'HIJAU', 'label' => 'Nilai Sedang', 'rentang_skor' => '4-6'],
            ['polaritas' => 'HIJAU', 'label' => 'Nilai Tinggi', 'rentang_skor' => '7-10'],
            ['polaritas' => 'MERAH', 'label' => 'Nilai Rendah', 'rentang_skor' => '1-2'],
            ['polaritas' => 'MERAH', 'label' => 'Nilai Sedang', 'rentang_skor' => '3'],
            ['polaritas' => 'MERAH', 'label' => 'Nilai Tinggi', 'rentang_skor' => '4-10'],
        ];
        foreach ($bands as $b) {
            DB::table('scoring_rule_band')->insert($b + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function test_generate_groups_aspek_by_sindrom_with_band_and_narasi(): void
    {
        /** @var ScoringEngineService $service */
        $service = app(ScoringEngineService::class);

        $result = $service->generate(['01' => 9, '02' => 5, '30' => 8]);

        $this->assertCount(2, $result['sindrom']);

        $driving = collect($result['sindrom'])->firstWhere('kode_romawi', 'I');
        $this->assertEquals('HIJAU', $driving['polaritas']);
        $this->assertCount(2, $driving['aspek']);
        $this->assertEquals(7.0, $driving['rata_rata_skor']);
        $this->assertEquals('Nilai Tinggi', $driving['band_label_rata_rata']);

        $authoritarian = collect($driving['aspek'])->firstWhere('kode', '01');
        $this->assertEquals('very_high', $authoritarian['narasi_level']);
        $this->assertEquals('sangat tinggi teks', $authoritarian['narasi']);
        $this->assertEquals('Nilai Tinggi', $authoritarian['band_label']);

        $stress = collect($result['sindrom'])->firstWhere('kode_romawi', 'VI');
        $this->assertEquals('MERAH', $stress['polaritas']);
        $anxiety = collect($stress['aspek'])->firstWhere('kode', '30');
        $this->assertEquals('Nilai Tinggi', $anxiety['band_label']);
        $this->assertEquals('high', $anxiety['narasi_level']);
    }

    public function test_narasi_level_mapping_covers_all_buckets(): void
    {
        $this->assertEquals('low', ScoringEngineService::narasiLevelUntukSkor(1));
        $this->assertEquals('low', ScoringEngineService::narasiLevelUntukSkor(3));
        $this->assertEquals('medium', ScoringEngineService::narasiLevelUntukSkor(4));
        $this->assertEquals('medium', ScoringEngineService::narasiLevelUntukSkor(6));
        $this->assertEquals('high', ScoringEngineService::narasiLevelUntukSkor(7));
        $this->assertEquals('high', ScoringEngineService::narasiLevelUntukSkor(8));
        $this->assertEquals('very_high', ScoringEngineService::narasiLevelUntukSkor(9));
        $this->assertEquals('very_high', ScoringEngineService::narasiLevelUntukSkor(10));
    }

    public function test_throws_for_unknown_kode(): void
    {
        /** @var ScoringEngineService $service */
        $service = app(ScoringEngineService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->generate(['99' => 5]);
    }

    public function test_throws_for_out_of_range_skor(): void
    {
        /** @var ScoringEngineService $service */
        $service = app(ScoringEngineService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->generate(['01' => 11]);
    }
}
