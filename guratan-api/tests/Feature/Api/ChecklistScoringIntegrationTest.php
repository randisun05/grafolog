<?php

namespace Tests\Feature\Api;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\Indikator;
use App\Models\MeasurementVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/**
 * KM-G: bukti bahwa alur measurement worksheet -> checklist -> tally
 * benar-benar bisa dipakai lewat ScoringController::submit YANG SAMA SEKALI
 * TIDAK DIUBAH - frontend cukup ambil tally dari GET checklist lalu susun
 * payload `skor` yang identik dengan yang dipakai form manual 1-10. Tidak
 * ada "mode" baru di SubmitScoresRequest/ScoringController - lihat CLAUDE.md.
 */
class ChecklistScoringIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    public function test_checklist_tally_can_be_submitted_through_the_unchanged_scoring_endpoint(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();

        // 3 posisi di aspek ini - centang 2 dari 3 lewat checklist.
        $posisi1 = Indikator::create(['kode' => "{$aspek->kode}-1", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Posisi 1']);
        $posisi2 = Indikator::create(['kode' => "{$aspek->kode}-2", 'posisi' => 2, 'aspek_id' => $aspek->id, 'nama' => 'Posisi 2']);
        Indikator::create(['kode' => "{$aspek->kode}-3", 'posisi' => 3, 'aspek_id' => $aspek->id, 'nama' => 'Posisi 3']);

        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $variable->kategori()->create(['kategori' => 'large', 'rentang' => '5-10', 'urutan' => 1]);
        $posisi1->rules()->create(['rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large']);

        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id, 'created_by' => $grafolog->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        // 1. isi measurement worksheet -> posisi 1 auto-tercentang.
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => $variable->id, 'nilai' => 7]],
        ])->assertOk();

        // 2. grafolog manual-centang posisi 2 dari layar checklist (posisi 3
        // dibiarkan kosong - murni observasi manual tanpa aturan, sengaja
        // tidak dicentang di test ini).
        $checklist = $this->actingAs($grafolog, 'sanctum')->getJson("/api/samples/{$sample->id}/checklist");
        $checklist->assertOk()->assertJsonPath('sindrom.0.aspek.0.indikator.0.checked', true);

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/checklist/toggle", [
            'indikator_id' => $posisi2->id, 'checked' => true,
        ])->assertOk();

        // 3. frontend membaca tally akhir dari checklist ...
        $final = $this->actingAs($grafolog, 'sanctum')->getJson("/api/samples/{$sample->id}/checklist");
        $skorAspek = $final->json('sindrom.0.aspek.0.skor');
        $this->assertSame(2, $skorAspek); // 2 dari 3 posisi tercentang

        // 4. ... dan submit lewat endpoint scoring yang sudah ada, tidak
        // berubah sama sekali - payload persis sama seperti form manual.
        $submit = $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/scores", [
            'skor' => [['kode' => $aspek->kode, 'skor' => $skorAspek]],
        ]);

        $submit->assertCreated();
        $this->assertDatabaseHas('report_aspek_scores', ['aspek_id' => $aspek->id, 'skor' => 2]);
        $this->assertDatabaseHas('handwriting_samples', ['id' => $sample->id, 'status' => 'completed']);
    }
}
