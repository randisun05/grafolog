<?php

namespace Tests\Unit;

use App\Models\HandwritingSample;
use App\Models\Indikator;
use App\Models\KombinasiTemuan;
use App\Models\SampleIndikatorCheck;
use App\Models\User;
use App\Services\Scoring\KombinasiTemuanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class KombinasiTemuanServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function sample(): HandwritingSample
    {
        $owner = User::factory()->create();

        return HandwritingSample::create([
            'user_id' => $owner->id, 'created_by' => $owner->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);
    }

    public function test_matches_when_two_aspek_levels_satisfy_and_logic(): void
    {
        $this->seedMinimalAspek(2); // aspek kode '01' dan '02', 1 sindrom sama
        $temuan = KombinasiTemuan::create(['nama' => 'Pola A', 'teks_interpretasi' => 'Sifat gabungan A.', 'logika_gabung' => 'AND']);
        $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => \App\Models\Aspek::where('kode', '01')->first()->id, 'kondisi' => 'high']);
        $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => \App\Models\Aspek::where('kode', '02')->first()->id, 'kondisi' => 'low']);

        $service = new KombinasiTemuanService;
        // 01 skor 8 -> high, 02 skor 2 -> low: kedua syarat AND terpenuhi.
        $matched = $service->evaluate(['01' => 8, '02' => 2], $this->sample());

        $this->assertCount(1, $matched);
        $this->assertSame('Pola A', $matched[0]['nama']);
        $this->assertSame('Sifat gabungan A.', $matched[0]['teks_interpretasi']);
    }

    public function test_and_logic_requires_all_syarat(): void
    {
        $this->seedMinimalAspek(2);
        $temuan = KombinasiTemuan::create(['nama' => 'Pola A', 'teks_interpretasi' => 'x', 'logika_gabung' => 'AND']);
        $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => \App\Models\Aspek::where('kode', '01')->first()->id, 'kondisi' => 'high']);
        $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => \App\Models\Aspek::where('kode', '02')->first()->id, 'kondisi' => 'low']);

        $service = new KombinasiTemuanService;
        // 02 tidak 'low' (skor 8 -> high) - AND gagal, tidak boleh matched.
        $matched = $service->evaluate(['01' => 8, '02' => 8], $this->sample());

        $this->assertCount(0, $matched);
    }

    public function test_or_logic_matches_with_only_one_syarat_satisfied(): void
    {
        $this->seedMinimalAspek(2);
        $temuan = KombinasiTemuan::create(['nama' => 'Pola OR', 'teks_interpretasi' => 'y', 'logika_gabung' => 'OR']);
        $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => \App\Models\Aspek::where('kode', '01')->first()->id, 'kondisi' => 'very_high']);
        $temuan->syarat()->create(['level' => 'aspek', 'aspek_id' => \App\Models\Aspek::where('kode', '02')->first()->id, 'kondisi' => 'low']);

        $service = new KombinasiTemuanService;
        $matched = $service->evaluate(['01' => 5, '02' => 1], $this->sample());

        $this->assertCount(1, $matched);
    }

    public function test_sindrom_level_condition_uses_average_of_its_aspek(): void
    {
        $this->seedMinimalAspek(2); // keduanya 1 sindrom yang sama
        $sindrom = \App\Models\Sindrom::first();
        $temuan = KombinasiTemuan::create(['nama' => 'Pola Sindrom', 'teks_interpretasi' => 'z', 'logika_gabung' => 'AND']);
        $temuan->syarat()->create(['level' => 'sindrom', 'sindrom_id' => $sindrom->id, 'kondisi' => 'high']);

        $service = new KombinasiTemuanService;
        // rata-rata (8+7)/2 = 7.5 -> round 8 -> high
        $matched = $service->evaluate(['01' => 8, '02' => 7], $this->sample());

        $this->assertCount(1, $matched);
    }

    public function test_indikator_level_condition_checks_sample_indikator_checks(): void
    {
        $this->seedMinimalAspek(1);
        $aspek = \App\Models\Aspek::where('kode', '01')->first();
        $indikator = Indikator::create(['kode' => '01-1a', 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Indikator X']);
        $sample = $this->sample();
        SampleIndikatorCheck::create(['sample_id' => $sample->id, 'indikator_id' => $indikator->id, 'checked' => true, 'sumber' => 'manual']);

        $temuan = KombinasiTemuan::create(['nama' => 'Pola Indikator', 'teks_interpretasi' => 'w', 'logika_gabung' => 'AND']);
        $temuan->syarat()->create(['level' => 'indikator', 'indikator_id' => $indikator->id, 'kondisi' => 'tercentang']);

        $service = new KombinasiTemuanService;
        $matched = $service->evaluate(['01' => 5], $sample);

        $this->assertCount(1, $matched);
    }

    public function test_indikator_level_tidak_tercentang_condition(): void
    {
        $this->seedMinimalAspek(1);
        $aspek = \App\Models\Aspek::where('kode', '01')->first();
        $indikator = Indikator::create(['kode' => '01-1a', 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Indikator X']);
        $sample = $this->sample(); // tidak ada baris sample_indikator_checks sama sekali

        $temuan = KombinasiTemuan::create(['nama' => 'Pola Tidak Tercentang', 'teks_interpretasi' => 'v', 'logika_gabung' => 'AND']);
        $temuan->syarat()->create(['level' => 'indikator', 'indikator_id' => $indikator->id, 'kondisi' => 'tidak_tercentang']);

        $service = new KombinasiTemuanService;
        $matched = $service->evaluate(['01' => 5], $sample);

        $this->assertCount(1, $matched);
    }

    public function test_temuan_without_syarat_never_matches(): void
    {
        $this->seedMinimalAspek(1);
        KombinasiTemuan::create(['nama' => 'Kosong', 'teks_interpretasi' => 'u', 'logika_gabung' => 'OR']);

        $service = new KombinasiTemuanService;
        $matched = $service->evaluate(['01' => 5], $this->sample());

        $this->assertCount(0, $matched);
    }
}
