<?php

namespace Database\Seeders;

use App\Models\CvField;
use App\Models\CvGroup;
use App\Models\CvValue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Variabel CV contoh (dummy) + isian demo. BUKAN bagian DatabaseSeeder - admin mengatur variabel
 * sungguhan lewat /admin/cv. Dipanggil dari DemoDataSeeder; aman dijalankan ulang (idempotent per key).
 *
 * applies_to: all | client (klien & kandidat) | grafolog. is_hidden: hanya pemilik + administrator.
 */
class CvDemoSeeder extends Seeder
{
    private const PENDIDIKAN = ['SMA/SMK', 'D3', 'S1', 'S2', 'S3'];

    /** @return array<int, array{name:string, description:string, fields: array<int, array>}> */
    public static function definition(): array
    {
        $f = fn (string $label, string $type = 'text', string $applies = 'client', array $x = []) => ['label' => $label, 'type' => $type, 'applies_to' => $applies] + $x;

        return [
            ['name' => 'Profil', 'description' => 'Identitas dasar yang tampil ke pihak yang memeriksa/diperiksa.', 'fields' => [
                $f('Foto profil', 'photo', 'all', ['help' => 'JPG/PNG/WEBP, maks. 3 MB.']),
                $f('Nama panggilan', 'text', 'all'),
                $f('Jenis kelamin', 'select', 'client', ['options' => ['Laki-laki', 'Perempuan']]),
                $f('Tempat lahir', 'text', 'client'),
                $f('Tanggal lahir', 'date', 'client'),
                $f('Tangan dominan menulis', 'select', 'client', ['options' => ['Kanan', 'Kiri', 'Keduanya'], 'help' => 'Relevan untuk pengukuran tulisan tangan.']),
                $f('Hobi & minat', 'textarea', 'client'),
                $f('Kota domisili', 'text', 'all'),
            ]],
            ['name' => 'Pendidikan & Pekerjaan', 'description' => 'Latar belakang formal.', 'fields' => [
                $f('Pendidikan terakhir', 'select', 'client', ['options' => self::PENDIDIKAN, 'required' => true]),
                $f('Institusi pendidikan', 'text', 'client'),
                $f('Jurusan', 'text', 'client'),
                $f('Pekerjaan saat ini', 'text', 'client'),
                $f('Lama pengalaman kerja (tahun)', 'number', 'client'),
                $f('Dokumen CV', 'file', 'client', ['help' => 'PDF atau DOCX, maks. 5 MB.']),
            ]],
            ['name' => 'Orang Tua', 'description' => 'Latar belakang keluarga (opsional).', 'fields' => [
                $f('Nama ayah', 'text', 'client'),
                $f('Pekerjaan ayah', 'text', 'client'),
                $f('Nama ibu', 'text', 'client'),
                $f('Pekerjaan ibu', 'text', 'client'),
                $f('Anak ke-', 'number', 'client'),
                $f('Jumlah saudara kandung', 'number', 'client'),
            ]],
            ['name' => 'Pasangan', 'description' => 'Diisi bila sudah menikah.', 'fields' => [
                $f('Status pernikahan', 'select', 'client', ['options' => ['Belum menikah', 'Menikah', 'Cerai']]),
                $f('Nama pasangan', 'text', 'client'),
                $f('Pekerjaan pasangan', 'text', 'client'),
                $f('Jumlah anak', 'number', 'client'),
            ]],
            ['name' => 'Kontak Pribadi', 'description' => 'Hanya Anda dan administrator yang dapat melihat.', 'fields' => [
                $f('Alamat lengkap', 'textarea', 'all', ['is_hidden' => true]),
                $f('Nomor darurat', 'phone', 'all', ['is_hidden' => true]),
                $f('Kontak darurat (nama & hubungan)', 'text', 'all', ['is_hidden' => true]),
            ]],
            ['name' => 'Profil Grafolog', 'description' => 'Ditampilkan ke klien dan perusahaan yang Anda periksa.', 'fields' => [
                $f('Gelar & sertifikasi', 'text', 'grafolog'),
                $f('Lembaga sertifikasi', 'text', 'grafolog'),
                $f('Tahun mulai praktik', 'number', 'grafolog'),
                $f('Bidang keahlian', 'text', 'grafolog'),
                $f('Metode kerja', 'textarea', 'grafolog', ['help' => 'Cara Anda mengukur dan menyusun laporan.']),
                $f('Bahasa yang dikuasai', 'text', 'grafolog'),
                $f('Biografi singkat', 'textarea', 'grafolog'),
                $f('Tautan profesional (LinkedIn dsb.)', 'text', 'grafolog'),
                $f('Nomor ponsel pribadi', 'phone', 'grafolog', ['is_hidden' => true]),
            ]],
        ];
    }

    /** Buat grup + variabel contoh (idempotent). */
    public function run(): void
    {
        foreach (self::definition() as $gi => $g) {
            $group = CvGroup::firstOrCreate(['name' => $g['name']], ['description' => $g['description'], 'sort_order' => $gi]);
            foreach ($g['fields'] as $fi => $field) {
                $key = Str::slug($field['label'], '_');
                CvField::firstOrCreate(['key' => $key], $field + ['cv_group_id' => $group->id, 'sort_order' => $fi]);
            }
        }
    }

    /** Isi nilai demo untuk satu pengguna (menimpa nilai yang ada). */
    public function fill(User $user, array $values): void
    {
        $fields = CvField::all()->keyBy('key');
        foreach ($values as $key => $value) {
            $field = $fields[$key] ?? throw new RuntimeException("Variabel CV '{$key}' tidak ada.");
            CvValue::updateOrCreate(
                ['user_id' => $user->id, 'cv_field_id' => $field->id],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            );
        }
        $this->photo($user);
    }

    /** Foto profil dummy (inisial di lingkaran warna) - hanya bila variabel foto berlaku untuk peran ini. */
    private function photo(User $user): void
    {
        $field = CvField::where('key', 'foto_profil')->first();
        if (! $field || ! function_exists('imagecreatetruecolor')) {
            return;
        }
        $img = imagecreatetruecolor(240, 240);
        $hue = crc32($user->email) % 360;
        [$r, $g, $b] = [(int) (120 + 90 * sin(deg2rad($hue))), (int) (120 + 90 * sin(deg2rad($hue + 120))), (int) (120 + 90 * sin(deg2rad($hue + 240)))];
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $initials = collect(explode(' ', $user->name))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        imagestring($img, 5, 120 - 5 * strlen($initials) - 2, 112, $initials, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        $path = "cv/{$user->id}/foto-profil.png";
        Storage::disk('local')->put($path, $png);
        CvValue::updateOrCreate(['user_id' => $user->id, 'cv_field_id' => $field->id], ['value' => json_encode([
            'path' => $path, 'name' => 'foto-profil.png', 'mime' => 'image/png',
        ])]);
    }
}
