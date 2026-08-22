<?php

namespace Tests\Feature\Api;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\KombinasiTemuan;
use App\Models\PersonalityReport;
use App\Models\Topik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/**
 * ReportController::segmen() - filter breakdown internal per-Topik, contoh
 * konkret "produk turunan" dari kategorisasi (lihat CLAUDE.md "Topik").
 * TopikFilterService murni baca `data` yang sudah tersimpan - tidak
 * memanggil ulang ScoringEngineService/KombinasiTemuanService sama sekali.
 */
class ReportSegmenTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function reportWithData(User $grafolog): PersonalityReport
    {
        $sindrom = $this->seedMinimalAspek(2); // aspek '01' dan '02'
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id, 'created_by' => $grafolog->id,
            'tier' => 'comprehensive', 'status' => 'completed',
        ]);

        return PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $sindrom->id, 'kode_romawi' => $sindrom->kode_romawi, 'nama' => $sindrom->nama,
                    'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => 7.0, 'band_label_rata_rata' => 'Nilai Tinggi',
                    'aspek' => [
                        ['kode' => '01', 'nama' => 'Aspek Karier', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'narasi 01'],
                        ['kode' => '02', 'nama' => 'Aspek Lain', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'narasi 02'],
                    ],
                ]],
                'kombinasi_ditemukan' => [
                    ['id' => 1, 'nama' => 'Pola Karier', 'teks_interpretasi' => 'x'],
                    ['id' => 2, 'nama' => 'Pola Lain', 'teks_interpretasi' => 'y'],
                ],
            ],
        ]);
    }

    public function test_grafolog_can_filter_report_by_topik(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->reportWithData($grafolog);
        $karier = Topik::create(['nama' => 'Karier']);
        Aspek::where('kode', '01')->first()->topik()->attach($karier->id);
        $temuan1 = KombinasiTemuan::create(['id' => 1, 'nama' => 'Pola Karier', 'teks_interpretasi' => 'x', 'logika_gabung' => 'OR']);
        $temuan1->topik()->attach($karier->id);
        KombinasiTemuan::create(['id' => 2, 'nama' => 'Pola Lain', 'teks_interpretasi' => 'y', 'logika_gabung' => 'OR']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->getJson("/api/reports/{$report->id}/segmen?topik_ids[]={$karier->id}");

        $response->assertOk();
        $aspekKodes = collect($response->json('sindrom.0.aspek'))->pluck('kode')->all();
        $this->assertSame(['01'], $aspekKodes);
        $this->assertCount(1, $response->json('kombinasi_ditemukan'));
        $this->assertSame('Pola Karier', $response->json('kombinasi_ditemukan.0.nama'));
    }

    public function test_empty_topik_ids_returns_data_unfiltered(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->reportWithData($grafolog);

        $response = $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}/segmen");

        $response->assertOk();
        $this->assertCount(2, $response->json('sindrom.0.aspek'));
        $this->assertCount(2, $response->json('kombinasi_ditemukan'));
    }

    public function test_sindrom_with_no_matching_aspek_is_dropped_entirely(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $report = $this->reportWithData($grafolog);
        $lain = Topik::create(['nama' => 'Tidak Terpakai']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->getJson("/api/reports/{$report->id}/segmen?topik_ids[]={$lain->id}");

        $response->assertOk()->assertJsonCount(0, 'sindrom');
    }

    public function test_client_cannot_access_segmen_endpoint(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);
        $sindrom = $this->seedMinimalAspek(1);
        $sample = HandwritingSample::create([
            'user_id' => $client->id, 'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        $report = PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(),
            'data' => ['sindrom' => [['id' => $sindrom->id, 'kode_romawi' => $sindrom->kode_romawi, 'nama' => $sindrom->nama, 'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => 7.0, 'band_label_rata_rata' => 'Nilai Tinggi', 'aspek' => [['kode' => '01', 'nama' => 'x', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'x']]]]],
        ]);

        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}/segmen")->assertForbidden();
    }

    public function test_stranger_cannot_access_segmen_endpoint(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $stranger = User::factory()->create(['role' => 'grafolog']);
        $report = $this->reportWithData($grafolog);

        $this->actingAs($stranger, 'sanctum')->getJson("/api/reports/{$report->id}/segmen")->assertForbidden();
    }
}
