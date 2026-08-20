<?php

namespace Database\Seeders;

use App\Support\IndikatorKode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GrafologiKnowledgeSeeder extends Seeder
{
    /**
     * Semua tabel di sini pakai id auto-increment standar. Kode asli dari
     * Excel (mis. '01', "01-1'", '15 & 16') disimpan di kolom `kode` sebagai
     * REFERENSI saja, bukan primary key. Karena itu, saat insert tabel anak
     * (yang di JSON masih merujuk via kode lama), kita WAJIB bangun peta
     * kode -> id baru dulu sebelum insert - itulah kenapa urutan & struktur
     * method di bawah agak berbeda dari versi sebelumnya.
     *
     * Idempoten sejak 2026-08-08 (KM-A, diperluas KM-F): setiap method pakai
     * `updateOrInsert`/`updateOrCreate` keyed ke kolom unik alaminya (kode /
     * kode_romawi / pasangan indikator_id+depends_on_indikator_id untuk
     * referensi silang), bukan `insert` buta - menjalankan ulang seeder ini
     * di atas data yang sudah ada memperbarui baris yang cocok, bukan dobel
     * atau gagal kena constraint unik. Hanya `scoring_rule_band` yang masih
     * ditulis ulang penuh (delete-lalu-insert) - 6 baris kecil tanpa kunci
     * alami. **Catatan 2026-08-19**: referensi silang sekarang jadi baris
     * `indikator_rules` biasa (rule_type='indikator_checked'), bukan lagi
     * tabel `indikator_cross_reference` terpisah dengan flag `aktif` -
     * reseed akan MENULIS ULANG relasi ini dari sumber JSON setiap kali,
     * termasuk relasi yang admin sudah hapus manual lewat Aturan Operator
     * (beda dari perilaku `aktif` lama yang sengaja dijaga tidak tertimpa) -
     * sama seperti seeder konten lain (Irregularity/CategoryMatch/dst),
     * bukan kelas risiko baru. Semuanya dibungkus 1 transaksi supaya tidak
     * pernah dalam keadaan kosong-sebagian jika seeding gagal di tengah
     * jalan.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/grafologi_knowledge_base.json');
        if (! file_exists($path)) {
            $this->command->error("File data tidak ditemukan: $path");

            return;
        }

        $data = json_decode(file_get_contents($path), true);
        $now = now();

        DB::transaction(function () use ($data, $now) {
            DB::table('metodologi_penilaian')->updateOrInsert(
                ['kode' => 'master'],
                [
                    'nama' => 'Master — Penilaian Manual Grafolog Bersertifikat',
                    'keterangan' => 'Measurement worksheet fisik diukur manual oleh grafolog bersertifikat, dicocokkan ke Indikator lewat aturan operator.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $metodologiMasterId = DB::table('metodologi_penilaian')->where('kode', 'master')->value('id');

            $sindromMap = $this->seedSindrom($data['sindrom'], $now);
            $aspekMap = $this->seedAspek($data['aspek'], $sindromMap, $now);
            $indikatorMap = $this->seedIndikator($data['indikator'], $aspekMap, $now);
            $this->seedMeasurementVariable($data['measurement_variables'], $metodologiMasterId, $now);
            $this->seedScoringRuleBand($data['scoring_rules'], $now);
            $this->seedDeskriptifLookup($data['deskriptif_lookup'], $now);
            $this->seedCrossReference($data['indikator_cross_reference'], $indikatorMap, $now);
        });

        $this->command->info('Knowledge base grafologi berhasil di-seed (idempoten, aman dijalankan ulang).');
    }

    /** @return array<int,int>  kode_lama(1-8) => id_baru */
    private function seedSindrom(array $rows, $now): array
    {
        $map = [];
        foreach ($rows as $r) {
            DB::table('sindrom')->updateOrInsert(
                ['kode_romawi' => $r['kode_romawi']],
                [
                    'nama' => $r['nama'],
                    'polaritas_inferred' => $r['polaritas_inferred'],
                    'catatan_polaritas' => $r['catatan_polaritas'] ?? null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $map[$r['id']] = DB::table('sindrom')->where('kode_romawi', $r['kode_romawi'])->value('id');
        }
        $this->command->info(count($rows).' sindrom di-seed.');

        return $map;
    }

    /** @return array<string,int>  kode_lama('01') => id_baru */
    private function seedAspek(array $rows, array $sindromMap, $now): array
    {
        $map = [];
        foreach ($rows as $r) {
            DB::table('aspek')->updateOrInsert(
                ['kode' => $r['id']],
                [
                    'sindrom_id' => $sindromMap[$r['sindrom_id']],
                    'nama' => $r['nama'],
                    'keterangan_umum' => $r['keterangan_umum'] ?? null,
                    'narasi_very_high' => $r['narasi']['very_high'] ?? null,
                    'narasi_high' => $r['narasi']['high'] ?? null,
                    'narasi_medium' => $r['narasi']['medium'] ?? null,
                    'narasi_low' => $r['narasi']['low'] ?? null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $map[$r['id']] = DB::table('aspek')->where('kode', $r['id'])->value('id');
        }
        $this->command->info(count($rows).' aspek di-seed.');

        return $map;
    }

    /** @return array<string,int>  kode_lama("01-1'") => id_baru */
    private function seedIndikator(array $rows, array $aspekMap, $now): array
    {
        $map = [];
        $skipped = 0;
        foreach ($rows as $r) {
            if (! isset($aspekMap[$r['aspek_id']])) {
                $skipped++;

                continue; // aspek_id tidak ditemukan - seharusnya tidak terjadi, tapi jangan sampai fatal
            }
            $parsed = IndikatorKode::parse($r['id']);
            DB::table('indikator')->updateOrInsert(
                ['kode' => $r['id']],
                [
                    'aspek_id' => $aspekMap[$r['aspek_id']],
                    'posisi' => $parsed['posisi'],
                    'varian' => $parsed['varian'],
                    'nama' => Str::limit($r['nama'] ?? '', 250, ''),
                    'keterangan' => $r['keterangan'] ?? null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $map[$r['id']] = DB::table('indikator')->where('kode', $r['id'])->value('id');
        }
        $this->command->info(count($map).' indikator di-seed'.($skipped ? " ($skipped dilewati - aspek tidak ditemukan)." : '.'));

        return $map;
    }

    private function seedMeasurementVariable(array $rows, int $metodologiMasterId, $now): void
    {
        foreach ($rows as $r) {
            DB::table('measurement_variable')->updateOrInsert(
                ['kode' => $r['id']],
                [
                    'metodologi_id' => $metodologiMasterId,
                    'axis' => $r['axis'],
                    'nama' => $r['nama'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $variableId = DB::table('measurement_variable')->where('kode', $r['id'])->value('id');

            // Kategori selalu milik penuh 1 variable_id, tidak punya kunci
            // alami sendiri - ganti seluruh set kategorinya per re-run,
            // bukan updateOrCreate per baris.
            DB::table('measurement_category')->where('variable_id', $variableId)->delete();
            foreach ($r['kategori_range'] as $i => $c) {
                DB::table('measurement_category')->insert([
                    'variable_id' => $variableId,
                    'kategori' => $c['kategori'],
                    'rentang' => $c['rentang'],
                    'unit' => $c['unit'],
                    'urutan' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        $this->command->info(count($rows).' measurement_variable + kategori di-seed.');
    }

    private function seedScoringRuleBand(array $scoringRules, $now): void
    {
        // Tanpa kunci alami (polaritas+label bukan unik dijamin di sumber) -
        // 6 baris kecil, aman ditulis ulang penuh setiap re-run.
        DB::table('scoring_rule_band')->delete();
        $map = ['HIJAU' => 'HIJAU_sindrom_positif', 'MERAH' => 'MERAH_sindrom_negatif'];
        $count = 0;
        foreach ($map as $polaritas => $key) {
            foreach ($scoringRules[$key]['band'] as $b) {
                DB::table('scoring_rule_band')->insert([
                    'polaritas' => $polaritas,
                    'label' => $b['label'],
                    'rentang_skor' => $b['rentang_skor'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $count++;
            }
        }
        $this->command->info("$count scoring_rule_band di-seed.");
    }

    private function seedDeskriptifLookup(array $rows, $now): void
    {
        foreach ($rows as $r) {
            DB::table('deskriptif_lookup')->updateOrInsert(
                ['kode' => (string) $r['id']],
                [
                    'keterangan' => $r['keterangan'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
        $this->command->info(count($rows).' deskriptif_lookup di-seed.');
    }

    /**
     * Cross-reference: sama seperti versi sebelumnya, cocokkan via "loose key"
     * (buang karakter non-alfanumerik + lowercase), tapi sekarang resolusinya
     * ke id integer (bukan kode string) untuk indikator_sumber_id.
     *
     * Idempoten sejak KM-F (2026-08-08): `updateOrInsert` keyed ke
     * (indikator_sumber_raw, mereferensikan_ke_kode) - pasangan ini sudah
     * dipastikan unik di 280 baris data sumber. Kolom `aktif` SENGAJA tidak
     * ikut ditulis di sini - itu status yang dikelola administrator lewat
     * panel KM-F, re-seed tidak boleh menimpanya balik ke default true.
     */
    /**
     * Sejak 2026-08-19: langsung jadi baris `indikator_rules`
     * (rule_type='indikator_checked', indikator_id=target,
     * depends_on_indikator_id=sumber) - bukan lagi tabel
     * `indikator_cross_reference` terpisah (dihapus, lihat migrasi
     * `migrate_cross_reference_into_indikator_rules_and_drop_table`).
     * Cuma pasangan yang KEDUA sisinya (sumber & target) berhasil di-resolve
     * ke Indikator sungguhan yang bisa jadi rule - baris `unmatched` (source
     * atau target tidak ketemu) tidak pernah berfungsi di sistem lama juga,
     * sengaja tidak dibawa. Idempoten lewat `updateOrInsert` keyed
     * (indikator_id, rule_type, depends_on_indikator_id).
     */
    private function seedCrossReference(array $rows, array $indikatorMap, $now): void
    {
        $looseMap = [];
        foreach ($indikatorMap as $kode => $id) {
            $looseMap[$this->looseKey($kode)] = $id;
        }

        $total = 0;
        $matchedCount = 0;
        foreach ($rows as $r) {
            $sumberKey = $r['indikator_sumber_kode_estimasi'] ?? null;
            $sumberId = $sumberKey ? ($looseMap[$this->looseKey($sumberKey)] ?? null) : null;

            foreach ($r['mereferensikan_ke'] as $target) {
                $targetId = $looseMap[$this->looseKey($target)] ?? null;
                $total++;
                if (! $sumberId || ! $targetId) {
                    continue;
                }
                $matchedCount++;

                DB::table('indikator_rules')->updateOrInsert(
                    [
                        'indikator_id' => $targetId,
                        'rule_type' => 'indikator_checked',
                        'depends_on_indikator_id' => $sumberId,
                    ],
                    [
                        'variable_a_id' => null,
                        'variable_a_value_mode' => 'nilai',
                        'category_label' => null,
                        'operator' => null,
                        'koefisien' => 1.0,
                        'variable_b_id' => null,
                        'compare_value' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
        $this->command->info("$matchedCount referensi silang di-seed sebagai indikator_rules (dari $total baris sumber, ".($total - $matchedCount).' tidak ketemu source/target - wajar, lihat catatan konversi).');
    }

    private function looseKey(string $code): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $code));
    }
}
