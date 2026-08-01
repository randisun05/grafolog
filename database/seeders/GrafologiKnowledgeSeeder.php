<?php

namespace Database\Seeders;

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

        $sindromMap = $this->seedSindrom($data['sindrom'], $now);
        $aspekMap = $this->seedAspek($data['aspek'], $sindromMap, $now);
        $indikatorMap = $this->seedIndikator($data['indikator'], $aspekMap, $now);
        $variableMap = $this->seedMeasurementVariable($data['measurement_variables'], $now);
        $this->seedScoringRuleBand($data['scoring_rules'], $now);
        $this->seedDeskriptifLookup($data['deskriptif_lookup'], $now);
        $this->seedCrossReference($data['indikator_cross_reference'], $indikatorMap, $now);

        $this->command->info('Knowledge base grafologi berhasil di-seed (skema id auto-increment).');
    }

    /** @return array<int,int>  kode_lama(1-8) => id_baru */
    private function seedSindrom(array $rows, $now): array
    {
        $map = [];
        foreach ($rows as $r) {
            $id = DB::table('sindrom')->insertGetId([
                'kode_romawi' => $r['kode_romawi'],
                'nama' => $r['nama'],
                'polaritas_inferred' => $r['polaritas_inferred'],
                'catatan_polaritas' => $r['catatan_polaritas'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$r['id']] = $id; // $r['id'] = kode lama (1-8) dari JSON
        }
        $this->command->info(count($rows) . ' sindrom di-seed.');
        return $map;
    }

    /** @return array<string,int>  kode_lama('01') => id_baru */
    private function seedAspek(array $rows, array $sindromMap, $now): array
    {
        $map = [];
        foreach ($rows as $r) {
            $id = DB::table('aspek')->insertGetId([
                'kode' => $r['id'],
                'sindrom_id' => $sindromMap[$r['sindrom_id']],
                'nama' => $r['nama'],
                'keterangan_umum' => $r['keterangan_umum'] ?? null,
                'narasi_very_high' => $r['narasi']['very_high'] ?? null,
                'narasi_high' => $r['narasi']['high'] ?? null,
                'narasi_medium' => $r['narasi']['medium'] ?? null,
                'narasi_low' => $r['narasi']['low'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$r['id']] = $id;
        }
        $this->command->info(count($rows) . ' aspek di-seed.');
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
            $id = DB::table('indikator')->insertGetId([
                'kode' => $r['id'],
                'aspek_id' => $aspekMap[$r['aspek_id']],
                'nama' => Str::limit($r['nama'] ?? '', 250, ''),
                'keterangan' => $r['keterangan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$r['id']] = $id;
        }
        $this->command->info(count($map) . ' indikator di-seed' . ($skipped ? " ($skipped dilewati - aspek tidak ditemukan)." : '.'));
        return $map;
    }

    /** @return array<string,int>  kode_lama('1','15 & 20 (d-stem)') => id_baru */
    private function seedMeasurementVariable(array $rows, $now): array
    {
        $map = [];
        foreach ($rows as $r) {
            $id = DB::table('measurement_variable')->insertGetId([
                'kode' => $r['id'],
                'axis' => $r['axis'],
                'nama' => $r['nama'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$r['id']] = $id;

            foreach ($r['kategori_range'] as $i => $c) {
                DB::table('measurement_category')->insert([
                    'variable_id' => $id,
                    'kategori' => $c['kategori'],
                    'rentang' => $c['rentang'],
                    'unit' => $c['unit'],
                    'urutan' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        $this->command->info(count($rows) . ' measurement_variable + kategori di-seed.');
        return $map;
    }

    private function seedScoringRuleBand(array $scoringRules, $now): void
    {
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
            DB::table('deskriptif_lookup')->insert([
                'kode' => (string) $r['id'],
                'keterangan' => $r['keterangan'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->command->info(count($rows) . ' deskriptif_lookup di-seed.');
    }

    /**
     * Cross-reference: sama seperti versi sebelumnya, cocokkan via "loose key"
     * (buang karakter non-alfanumerik + lowercase), tapi sekarang resolusinya
     * ke id integer (bukan kode string) untuk indikator_sumber_id.
     */
    private function seedCrossReference(array $rows, array $indikatorMap, $now): void
    {
        $looseMap = [];
        foreach ($indikatorMap as $kode => $id) {
            $looseMap[$this->looseKey($kode)] = ['id' => $id, 'kode' => $kode];
        }

        $insertRows = [];
        $matchedCount = 0;
        foreach ($rows as $r) {
            $sumberKey = $r['indikator_sumber_kode_estimasi'] ?? null;
            $sumberMatch = $sumberKey ? ($looseMap[$this->looseKey($sumberKey)] ?? null) : null;

            foreach ($r['mereferensikan_ke'] as $target) {
                $targetMatch = $looseMap[$this->looseKey($target)] ?? null;
                $status = ($sumberMatch && $targetMatch) ? 'matched' : 'unmatched';
                if ($status === 'matched') $matchedCount++;

                $insertRows[] = [
                    'indikator_sumber_raw' => Str::limit($r['indikator_sumber_raw'], 250, ''),
                    'indikator_sumber_id' => $sumberMatch['id'] ?? null,
                    'mereferensikan_ke_kode' => $targetMatch['kode'] ?? $target,
                    'match_status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('indikator_cross_reference')->insert($insertRows);
        $total = count($insertRows);
        $this->command->info("$total baris cross_reference di-seed ($matchedCount matched, " . ($total - $matchedCount) . ' unmatched - wajar, lihat catatan konversi).');
    }

    private function looseKey(string $code): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $code));
    }
}
