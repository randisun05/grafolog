<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konversi satu-kali baris `indikator_cross_reference` yang AKTIF+MATCHED
 * (di database ini, saat migrasi ini benar-benar dijalankan) jadi baris
 * `indikator_rules` (rule_type='indikator_checked'), lalu drop tabel lama.
 * Baris `unmatched` (tidak pernah ikut cascade) dan yang admin nonaktifkan
 * SENGAJA tidak dibawa - itu memang tidak pernah berfungsi/tidak diinginkan
 * di sistem lama. Untuk fresh install (tabel masih kosong saat migrate,
 * belum di-seed), konversi ini no-op - konten 257 relasi yang sama datang
 * dari `GrafologiKnowledgeSeeder::seedCrossReference()` yang sudah diretrofit
 * untuk menulis ke indikator_rules langsung (lihat file itu). Tidak
 * reversible secara berarti (baris cross_reference lama sudah hilang
 * begitu dijalankan) - down() cuma membuat ulang skema tabel, kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('indikator_cross_reference')) {
            $rows = DB::table('indikator_cross_reference')
                ->where('aktif', true)
                ->where('match_status', 'matched')
                ->whereNotNull('indikator_sumber_id')
                ->get();

            $indikatorByKode = DB::table('indikator')->pluck('id', 'kode');
            $now = now();
            $migrated = 0;

            foreach ($rows as $row) {
                $targetId = $indikatorByKode[$row->mereferensikan_ke_kode] ?? null;
                if (! $targetId) {
                    continue;
                }

                $exists = DB::table('indikator_rules')
                    ->where('indikator_id', $targetId)
                    ->where('rule_type', 'indikator_checked')
                    ->where('depends_on_indikator_id', $row->indikator_sumber_id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('indikator_rules')->insert([
                    'indikator_id' => $targetId,
                    'rule_type' => 'indikator_checked',
                    'depends_on_indikator_id' => $row->indikator_sumber_id,
                    'variable_a_id' => null,
                    'variable_a_value_mode' => 'nilai',
                    'category_label' => null,
                    'operator' => null,
                    'koefisien' => 1.0,
                    'variable_b_id' => null,
                    'compare_value' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $migrated++;
            }

            if (app()->runningInConsole() && ! app()->environment('testing')) {
                fwrite(STDOUT, "  {$migrated} baris indikator_cross_reference dikonversi ke indikator_rules.\n");
            }

            // sample_indikator_checks.cross_reference_id jadi redundan - baris
            // cascade sekarang ditandai lewat rule_id (menunjuk IndikatorRule
            // rule_type='indikator_checked' yang sama persis), tidak perlu FK
            // kedua ke tabel yang mau dihapus.
            if (Schema::hasColumn('sample_indikator_checks', 'cross_reference_id')) {
                Schema::table('sample_indikator_checks', function ($table) {
                    $table->dropForeign(['cross_reference_id']);
                    $table->dropColumn('cross_reference_id');
                });
            }

            Schema::dropIfExists('indikator_cross_reference');
        }
    }

    public function down(): void
    {
        Schema::table('sample_indikator_checks', function ($table) {
            $table->foreignId('cross_reference_id')->nullable()->after('rule_id');
        });

        Schema::create('indikator_cross_reference', function ($table) {
            $table->id();
            $table->string('indikator_sumber_raw', 255);
            $table->foreignId('indikator_sumber_id')->nullable()->constrained('indikator')->nullOnDelete();
            $table->string('mereferensikan_ke_kode', 20);
            $table->enum('match_status', ['matched', 'unmatched'])->default('unmatched');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index('mereferensikan_ke_kode');
        });
    }
};
