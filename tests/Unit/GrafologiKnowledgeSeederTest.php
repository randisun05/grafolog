<?php

namespace Tests\Unit;

use App\Models\Indikator;
use App\Models\IndikatorCrossReference;
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
            'indikator_cross_reference' => DB::table('indikator_cross_reference')->count(),
            'metodologi_penilaian' => DB::table('metodologi_penilaian')->count(),
        ];
        $this->assertGreaterThan(0, $counts['indikator']);

        $this->seed(GrafologiKnowledgeSeeder::class);

        foreach ($counts as $table => $expected) {
            $this->assertSame($expected, DB::table($table)->count(), "$table row count changed after re-seeding");
        }
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
     * KM-F (2026-08-08): `aktif` adalah status yang dikelola administrator,
     * bukan data sumber JSON - re-seed WAJIB tidak menimpanya balik ke
     * default true (lihat catatan di GrafologiKnowledgeSeeder::seedCrossReference()).
     */
    public function test_reseeding_preserves_admin_deactivated_cross_reference(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $row = IndikatorCrossReference::where('match_status', 'matched')->firstOrFail();
        $row->update(['aktif' => false]);

        $this->seed(GrafologiKnowledgeSeeder::class);

        $this->assertFalse($row->fresh()->aktif);
    }
}
