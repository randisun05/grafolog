<?php

namespace Tests\Unit;

use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementCategory;
use App\Models\MeasurementVariable;
use App\Models\MetodologiPenilaian;
use App\Models\Sindrom;
use Database\Seeders\GrafologiKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GrafologiKnowledgeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_knowledge_base_and_tags_master_methodology(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);

        $master = MetodologiPenilaian::findByKode('master');
        $this->assertNotNull($master);

        $variable = MeasurementVariable::first();
        $this->assertNotNull($variable);
        $this->assertSame($master->id, $variable->metodologi_id);

        $indikatorWithPosisi = Indikator::whereNotNull('posisi')->count();
        $this->assertSame(Indikator::count(), $indikatorWithPosisi);
        $this->assertGreaterThan(0, Indikator::whereNotNull('varian')->count());
    }

    public function test_running_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);

        $counts = [
            'sindrom' => DB::table('sindrom')->count(),
            'aspek' => DB::table('aspek')->count(),
            'indikator' => DB::table('indikator')->count(),
            'measurement_variable' => DB::table('measurement_variable')->count(),
            'measurement_category' => DB::table('measurement_category')->count(),
            'scoring_rule_band' => DB::table('scoring_rule_band')->count(),
            'deskriptif_lookup' => DB::table('deskriptif_lookup')->count(),
            'metodologi_penilaian' => DB::table('metodologi_penilaian')->count(),
        ];
        $indikatorCheckedCount = IndikatorRule::where('rule_type', 'indikator_checked')->count();
        $this->assertGreaterThan(0, $counts['indikator']);
        $this->assertGreaterThan(0, $indikatorCheckedCount);

        $this->seed(GrafologiKnowledgeSeeder::class);

        foreach ($counts as $table => $expected) {
            $this->assertSame($expected, DB::table($table)->count(), "$table row count changed after re-seeding");
        }
        $this->assertSame(
            $indikatorCheckedCount,
            IndikatorRule::where('rule_type', 'indikator_checked')->count(),
            'indikator_checked rule count changed after re-seeding',
        );
    }

    public function test_running_seeder_twice_preserves_ids_referenced_by_other_tables(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $sindrom = Sindrom::first();
        $originalId = $sindrom->id;

        $this->seed(GrafologiKnowledgeSeeder::class);

        $this->assertSame($originalId, Sindrom::where('kode_romawi', $sindrom->kode_romawi)->value('id'));
    }

    public function test_measurement_category_matches_its_variable_after_reseed(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(GrafologiKnowledgeSeeder::class);

        $orphaned = MeasurementCategory::whereDoesntHave('variable')->count();
        $this->assertSame(0, $orphaned);
    }

    /**
     * 2026-08-19: referensi silang sekarang cuma baris `indikator_rules`
     * biasa (rule_type='indikator_checked'), tidak ada lagi flag `aktif`
     * terpisah yang dijaga tidak tertimpa (beda dari KM-F lama) -
     * "menonaktifkan" sekarang berarti HAPUS baris lewat Aturan Operator,
     * sama seperti rule measurement lainnya. Reseed akan MEMBUAT ULANG
     * relasi itu dari sumber JSON kalau masih ada di sana - keputusan
     * desain yang didokumentasikan di GrafologiKnowledgeSeeder, bukan bug.
     */
    public function test_reseeding_recreates_a_cross_reference_rule_deleted_via_admin_ui(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $row = IndikatorRule::where('rule_type', 'indikator_checked')->firstOrFail();
        $key = ['indikator_id' => $row->indikator_id, 'depends_on_indikator_id' => $row->depends_on_indikator_id];
        $row->delete();
        $this->assertDatabaseMissing('indikator_rules', $key + ['rule_type' => 'indikator_checked']);

        $this->seed(GrafologiKnowledgeSeeder::class);

        $this->assertDatabaseHas('indikator_rules', $key + ['rule_type' => 'indikator_checked']);
    }
}
