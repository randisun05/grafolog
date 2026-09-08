<?php

namespace Tests\Unit\Services\Supervisor;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Sindrom;
use App\Models\Topik;
use App\Models\User;
use App\Services\Reporting\TopikFilterService;
use App\Services\Supervisor\PotensiAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class PotensiAggregationServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private Sindrom $sindrom;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed sekali, dipakai bersama semua fixture report di test ini -
        // supaya beberapa laporan genuinely merujuk aspek '01'/'02' yang
        // SAMA (bukan aspek berbeda per report, yang akan bikin agregasi
        // rata-rata jadi tidak berarti).
        $this->sindrom = $this->seedMinimalAspek(2);
    }

    private function reportWithAspekSkor(int $skor, string $narasiLevel, ?User $candidate = null): PersonalityReport
    {
        $sample = HandwritingSample::create([
            'user_id' => ($candidate ?? User::factory()->create())->id,
            'created_by' => User::factory()->create()->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
        ]);

        return PersonalityReport::create([
            'sample_id' => $sample->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
            'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $this->sindrom->id, 'kode_romawi' => $this->sindrom->kode_romawi, 'nama' => $this->sindrom->nama,
                    'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => $skor, 'band_label_rata_rata' => 'x',
                    'aspek' => [
                        ['kode' => '01', 'nama' => 'Aspek Karier', 'skor' => $skor, 'band_label' => 'x', 'narasi_level' => $narasiLevel, 'narasi' => 'x'],
                        ['kode' => '02', 'nama' => 'Aspek Lain', 'skor' => $skor, 'band_label' => 'x', 'narasi_level' => $narasiLevel, 'narasi' => 'x'],
                    ],
                ]],
            ],
        ]);
    }

    private function service(): PotensiAggregationService
    {
        return new PotensiAggregationService(new TopikFilterService);
    }

    public function test_aggregates_average_skor_and_narasi_level_distribution_across_reports(): void
    {
        $reportA = $this->reportWithAspekSkor(6, 'medium')->load('sample');
        $reportB = $this->reportWithAspekSkor(8, 'high')->load('sample');

        $result = $this->service()->aggregate(collect([$reportA, $reportB]));

        $this->assertSame(2, $result['report_count']);
        $this->assertSame(2, $result['candidate_count']);

        $aspek01 = collect($result['sindrom'][0]['aspek'])->firstWhere('kode', '01');
        $this->assertSame(7.0, $aspek01['avg_skor']);
        $this->assertSame(['low' => 0, 'medium' => 1, 'high' => 1, 'very_high' => 0], $aspek01['narasi_level_distribution']);
    }

    public function test_topik_filter_narrows_included_aspek(): void
    {
        $report = $this->reportWithAspekSkor(7, 'high')->load('sample');
        $karier = Topik::create(['nama' => 'Karier']);
        Aspek::where('kode', '01')->first()->topik()->attach($karier->id);

        $result = $this->service()->aggregate(collect([$report]), [$karier->id]);

        $aspekKodes = collect($result['sindrom'][0]['aspek'])->pluck('kode')->all();
        $this->assertSame(['01'], $aspekKodes);
    }

    public function test_empty_topik_ids_means_unfiltered(): void
    {
        $report = $this->reportWithAspekSkor(7, 'high')->load('sample');

        $result = $this->service()->aggregate(collect([$report]), []);

        $aspekKodes = collect($result['sindrom'][0]['aspek'])->pluck('kode')->all();
        $this->assertSame(['01', '02'], $aspekKodes);
    }

    public function test_candidate_count_counts_distinct_candidates_not_reports(): void
    {
        $sameCandidate = User::factory()->create();
        $reportA = $this->reportWithAspekSkor(6, 'medium', $sameCandidate)->load('sample');
        $reportB = $this->reportWithAspekSkor(8, 'high', $sameCandidate)->load('sample');

        $result = $this->service()->aggregate(collect([$reportA, $reportB]));

        $this->assertSame(2, $result['report_count']);
        $this->assertSame(1, $result['candidate_count']);
    }

    public function test_empty_reports_collection_returns_zeroed_result(): void
    {
        $result = $this->service()->aggregate(collect());

        $this->assertSame(0, $result['report_count']);
        $this->assertSame(0, $result['candidate_count']);
        $this->assertSame([], $result['sindrom']);
    }
}
