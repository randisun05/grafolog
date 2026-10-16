<?php

namespace Tests\Feature\Api\Admin;

use App\Models\HandwritingSample;
use App\Models\MeasurementVariable;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeMatrixControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrator']);
    }

    public function test_only_administrator_can_read_matrix(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'grafolog']));
        $this->getJson('/api/admin/knowledge/matrix')->assertForbidden();
        $this->getJson('/api/admin/knowledge/matrix/empiris')->assertForbidden();
    }

    public function test_struktur_matches_real_knowledge_base(): void
    {
        $this->seed(DatabaseSeeder::class);
        Sanctum::actingAs($this->admin());

        $res = $this->getJson('/api/admin/knowledge/matrix')->assertOk()->json();

        $this->assertSame(106, $res['ringkasan']['aturan_ukur']);
        $this->assertSame(257, $res['ringkasan']['tautan_indikator']);
        $this->assertSame(16, $res['ringkasan']['variabel_dipakai']);
        $this->assertSame(308, $res['ringkasan']['indikator_beraturan']);
        $this->assertCount(18, $res['celah']['variabel_tanpa_aturan']);
        $this->assertCount(13, $res['celah']['aspek_tanpa_indikator_terukur']);
        $this->assertCount(2, $res['celah']['aspek_tanpa_aturan']);
        $this->assertSame(['maju' => 257, 'mundur' => 0, 'dalam_aspek' => 0, 'dua_arah' => 0], $res['celah']['arah_tautan']);
        $this->assertFalse($res['celah']['ada_siklus']);
        $this->assertSame(4, $res['celah']['rantai_terpanjang']);
        $this->assertCount(160, $res['ciri_bersama']['grup']);
        $this->assertSame(224, $res['celah']['pasangan_ciri_belum_tertaut']);

        // Middle zone height menyentuh 17 aspek.
        $mzh = MeasurementVariable::where('nama', 'Middle zone height')->value('id');
        $this->assertCount(17, collect($res['variabel_aspek'])->where('variable_id', $mzh));

        // Sel aspek->aspek membawa daftar tautan indikator yang bisa ditelusuri.
        $cell = $res['aspek_aspek'][0];
        $this->assertNotEmpty($cell['tautan']);
        $this->assertArrayHasKey('kode', $cell['tautan'][0]['dari']);
    }

    public function test_empiris_computes_pearson_only_above_min_n(): void
    {
        Sanctum::actingAs($this->admin());
        $a = MeasurementVariable::create(['kode' => 'a', 'axis' => 'vertical', 'nama' => 'A']);
        $b = MeasurementVariable::create(['kode' => 'b', 'axis' => 'vertical', 'nama' => 'B']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        foreach ([1, 2, 3, 4, 5] as $v) {
            $s = HandwritingSample::create([
                'user_id' => User::factory()->create()->id, 'created_by' => $grafolog->id,
                'tier' => 'comprehensive', 'status' => 'pending',
            ]);
            $s->measurementReadings()->create(['variable_id' => $a->id, 'nilai' => $v]);
            $s->measurementReadings()->create(['variable_id' => $b->id, 'nilai' => 10 - 2 * $v]);
        }

        $res = $this->getJson('/api/admin/knowledge/matrix/empiris?min_n=5')->assertOk()->json();
        $this->assertSame(5, $res['variabel']['n_sampel']);
        $this->assertSame([['a' => $a->id, 'b' => $b->id, 'r' => -1, 'n' => 5]], $res['variabel']['sel']);

        $res = $this->getJson('/api/admin/knowledge/matrix/empiris')->assertOk()->json();
        $this->assertSame(30, $res['min_n']);
        $this->assertSame([], $res['variabel']['sel']);

        $this->getJson('/api/admin/knowledge/matrix/empiris?min_n=1')->assertStatus(422);
    }
}
