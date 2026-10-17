<?php

namespace Tests\Feature\Api;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportLanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $grafolog;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->grafolog = User::factory()->create(['role' => 'grafolog']);
        $this->client = User::factory()->create();
    }

    private function submittedReport(int $skor = 7): PersonalityReport
    {
        $sample = HandwritingSample::create([
            'user_id' => $this->client->id, 'created_by' => $this->grafolog->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        $skorPayload = Aspek::pluck('kode')->map(fn ($kode) => ['kode' => $kode, 'skor' => $skor])->all();
        $this->actingAs($this->grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/scores", ['skor' => $skorPayload])->assertCreated();

        $report = PersonalityReport::where('sample_id', $sample->id)->first();
        $report->update(['review_status' => PersonalityReport::REVIEW_APPROVED]);

        return $report;
    }

    public function test_translation_seeder_fills_all_aspek_and_sindrom(): void
    {
        $this->assertSame(0, Aspek::whereNull('nama_id')->count());
        $this->assertSame('Otoriter', Aspek::where('kode', '01')->value('nama_id'));
        $this->assertSame(
            Aspek::whereNotNull('narasi_high')->where('narasi_high', '!=', '')->count(),
            Aspek::whereNotNull('narasi_high_id')->count(),
        );
    }

    public function test_new_report_stores_both_languages_and_serves_each(): void
    {
        $report = $this->submittedReport();
        $stored = $report->data['sindrom'][0]['aspek'][0];
        $this->assertStringStartsWith('The writer', $stored['narasi_en']);
        $this->assertStringStartsWith('Penulis', $stored['narasi_id']);

        $id = $this->actingAs($this->client, 'sanctum')->getJson("/api/reports/{$report->id}?lang=id")->assertOk()->json('data');
        $this->assertSame('id', $id['bahasa']);
        $this->assertSame('DORONGAN DASAR', $id['sindrom'][0]['nama']);
        $this->assertSame('Otoriter', $id['sindrom'][0]['aspek'][0]['nama']);
        $this->assertStringStartsWith('Penulis', $id['sindrom'][0]['aspek'][0]['narasi']);
        $this->assertSame('Nilai Tinggi', $id['sindrom'][0]['aspek'][0]['band_label']);

        $en = $this->actingAs($this->client, 'sanctum')->getJson("/api/reports/{$report->id}?lang=en")->assertOk()->json('data');
        $this->assertSame('DRIVING FORCES', $en['sindrom'][0]['nama']);
        $this->assertSame('Authoritarian', $en['sindrom'][0]['aspek'][0]['nama']);
        $this->assertStringStartsWith('The writer', $en['sindrom'][0]['aspek'][0]['narasi']);
        $this->assertSame('High', $en['sindrom'][0]['aspek'][0]['band_label']);
        $this->assertSame('GREEN', $en['sindrom'][0]['polaritas']);
    }

    public function test_unsupported_language_rejected(): void
    {
        $report = $this->submittedReport();

        $this->actingAs($this->client, 'sanctum')->getJson("/api/reports/{$report->id}?lang=fr")->assertUnprocessable();
    }

    public function test_pdf_available_in_both_languages(): void
    {
        Storage::fake('local');
        $report = $this->submittedReport();

        $id = $this->actingAs($this->client, 'sanctum')->get("/api/reports/{$report->id}/pdf?lang=id")->assertOk();
        $this->assertStringContainsString("laporan-grafologi-{$report->id}-id.pdf", $id->headers->get('content-disposition'));

        $en = $this->actingAs($this->client, 'sanctum')->get("/api/reports/{$report->id}/pdf?lang=en")->assertOk();
        $this->assertStringContainsString("graphology-report-{$report->id}-en.pdf", $en->headers->get('content-disposition'));

        $files = Storage::disk('local')->files('reports');
        $this->assertCount(2, $files, 'File PDF ID dan EN harus sama-sama tersimpan, tidak saling menghapus');
    }

    public function test_manual_edit_only_changes_the_edited_language(): void
    {
        $report = $this->submittedReport();

        $this->actingAs($this->grafolog, 'sanctum')
            ->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'Custom English text.', 'bahasa' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.bahasa', 'en')
            ->assertJsonPath('data.sindrom.0.aspek.0.narasi', 'Custom English text.');

        $id = $this->actingAs($this->grafolog, 'sanctum')->getJson("/api/reports/{$report->id}?lang=id")->json('data.sindrom.0.aspek.0');
        $this->assertStringStartsWith('Penulis', $id['narasi']);
        $this->assertFalse($id['narasi_diedit_manual']);

        $this->actingAs($this->grafolog, 'sanctum')
            ->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'Teks khusus.'])
            ->assertJsonPath('data.sindrom.0.aspek.0.narasi', 'Teks khusus.');
        $this->assertSame('Teks khusus.', $report->fresh()->data['sindrom'][0]['aspek'][0]['narasi']);
    }

    public function test_legacy_report_without_narasi_en_gets_indonesian_from_kb(): void
    {
        $report = $this->submittedReport();
        $data = $report->data;
        foreach ($data['sindrom'] as &$sindrom) {
            unset($sindrom['nama_id']);
            foreach ($sindrom['aspek'] as &$aspek) {
                $aspek['narasi'] = $aspek['narasi_en'];
                unset($aspek['narasi_en'], $aspek['narasi_id'], $aspek['nama_id']);
            }
            unset($aspek);
        }
        unset($sindrom);
        $report->update(['data' => $data]);

        $id = $this->actingAs($this->client, 'sanctum')->getJson("/api/reports/{$report->id}?lang=id")->json('data.sindrom.0');
        $this->assertSame('DORONGAN DASAR', $id['nama']);
        $this->assertStringStartsWith('Penulis', $id['aspek'][0]['narasi']);

        $en = $this->actingAs($this->client, 'sanctum')->getJson("/api/reports/{$report->id}?lang=en")->json('data.sindrom.0.aspek.0');
        $this->assertStringStartsWith('The writer', $en['narasi']);
    }
}
