<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Aspek;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\ReportAspekScore;
use App\Models\SampleConsultation;
use App\Models\SampleImage;
use App\Models\User;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Data dummy untuk uji manual semua role (BUKAN bagian DatabaseSeeder).
 * Jalankan setelah migrate --seed:  php artisan db:seed --class=DemoDataSeeder
 * Semua akun berpassword DemoGuratan123. Ditolak di APP_ENV=production, dan
 * hanya jalan sekali (cek akun admin.demo@guratan.test).
 *
 * Skenario mencakup setiap tahap alur: pesanan belum bayar, lunas belum
 * ditugaskan, menunggu review, perlu revisi, disetujui (+ konsultasi Master),
 * dan kandidat HR.
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'DemoGuratan123';

    /** Skor dasar per sindrom (kode romawi) untuk tiap persona, +-1 variasi per aspek. */
    private const PERSONAS = [
        'pemimpin' => ['I' => 8, 'II' => 7, 'III' => 8, 'IV' => 7, 'V' => 8, 'VI' => 3, 'VII' => 3, 'VIII' => 2],
        'analitis' => ['I' => 5, 'II' => 9, 'III' => 7, 'IV' => 4, 'V' => 7, 'VI' => 4, 'VII' => 5, 'VIII' => 3],
        'sosial' => ['I' => 6, 'II' => 5, 'III' => 6, 'IV' => 9, 'V' => 5, 'VI' => 5, 'VII' => 3, 'VIII' => 4],
        'tertekan' => ['I' => 4, 'II' => 6, 'III' => 4, 'IV' => 4, 'V' => 4, 'VI' => 8, 'VII' => 7, 'VIII' => 7],
    ];

    public function run(ScoringEngineService $engine): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoDataSeeder tidak boleh dijalankan di production.');
        }

        if (User::where('email', 'admin.demo@guratan.test')->exists()) {
            $this->command?->warn('Data demo sudah ada - dilewati. Jalankan migrate:fresh --seed dulu untuk mengulang.');

            return;
        }

        if (Aspek::count() === 0) {
            throw new RuntimeException('Knowledge base kosong - jalankan php artisan db:seed (DatabaseSeeder) dulu.');
        }

        $users = $this->createUsers();
        $this->createCommerceData($users['admin']);

        $ani = $users['ani'];
        $bima = $users['bima'];
        $spv = $users['supervisor'];

        // 1. Budi - pesan sendiri, lunas, dikerjakan Ani, laporan sudah disetujui.
        $budi = $this->clientOrder($users['budi'], 'comprehensive', paid: true, daysAgo: 12);
        $this->assign($budi, $ani, $users['admin']);
        $this->report($engine, $budi, $ani, 'pemimpin', PersonalityReport::REVIEW_APPROVED, $spv, daysAgo: 10);

        // 2. Citra - tier Master, disetujui + jadwal konsultasi.
        $citra = $this->clientOrder($users['citra'], 'master', paid: true, daysAgo: 8);
        $this->assign($citra, $ani, $users['admin']);
        $this->report($engine, $citra, $ani, 'sosial', PersonalityReport::REVIEW_APPROVED, $spv, daysAgo: 6);
        SampleConsultation::create([
            'sample_id' => $citra->id,
            'scheduled_at' => now('Asia/Jakarta')->addDays(3)->setTime(10, 0)->utc(),
            'meeting_url' => 'https://meet.google.com/demo-guratan-01',
            'notes' => 'Siapkan 2-3 pertanyaan tentang hasil aspek Interpersonal Relations.',
            'scheduled_by' => $ani->id,
        ]);

        // 3. Dewi - lunas, belum ditugaskan (muncul di Pesanan Masuk admin).
        $this->clientOrder($users['dewi'], 'comprehensive', paid: true, daysAgo: 1);

        // 4. Eko - pesanan belum dibayar.
        $this->clientOrder($users['eko'], 'master', paid: false, daysAgo: 0);

        // 5. Fajar - klien walk-in Bima, laporan menunggu review supervisor.
        $fajar = $this->walkIn($users['fajar'], $bima, 'comprehensive', daysAgo: 3);
        $this->report($engine, $fajar, $bima, 'analitis', PersonalityReport::REVIEW_PENDING, null, daysAgo: 1);

        // 6. Gita - klien walk-in Ani, dikembalikan supervisor untuk revisi.
        $gita = $this->walkIn($users['gita'], $ani, 'comprehensive', daysAgo: 5);
        $report = $this->report($engine, $gita, $ani, 'tertekan', PersonalityReport::REVIEW_REVISION, $spv, daysAgo: 2);
        $report->update(['review_note' => 'Mohon cek ulang skor Stress Vulnerability - catatan pengukuran tekanan tidak konsisten dengan skor 8.']);

        // 7-8. Kandidat HR PT Maju Jaya.
        $project = Project::create(['name' => 'Rekrutmen Staf Keuangan Q4', 'source' => 'hr', 'created_by' => $users['hr']->id]);
        $hendra = $this->sample($project, $users['hendra'], $users['hr'], 'comprehensive', daysAgo: 9);
        $this->assign($hendra, $bima, $users['hr']);
        $this->report($engine, $hendra, $bima, 'analitis', PersonalityReport::REVIEW_APPROVED, $spv, daysAgo: 7);
        $this->sample($project, $users['intan'], $users['hr'], 'comprehensive', daysAgo: 2);

        $this->command?->info('Data demo dibuat. Semua akun berpassword: '.self::PASSWORD);
        $this->command?->table(['Role', 'Email'], collect($users)->map(fn (User $u) => [$u->role, $u->email])->values()->all());
    }

    /** @return array<string, User> */
    private function createUsers(): array
    {
        $company = Company::create(['name' => 'PT Maju Jaya (Demo)']);
        $consent = ['consented_at' => now(), 'consent_version' => User::CONSENT_VERSION];

        $make = fn (string $name, string $email, string $role, array $extra = []) => User::create([
            'name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'role' => $role, ...$extra,
        ]);

        return [
            'admin' => $make('Admin Demo', 'admin.demo@guratan.test', 'administrator'),
            'supervisor' => $make('Sari Supervisor', 'supervisor@guratan.test', 'supervisor'),
            'ani' => $make('Ani Grafolog', 'ani.grafolog@guratan.test', 'grafolog'),
            'bima' => $make('Bima Grafolog', 'bima.grafolog@guratan.test', 'grafolog'),
            'hr' => $make('Rina HR', 'hr@majujaya.test', 'hr', ['company_id' => $company->id]),
            'budi' => $make('Budi Santoso', 'budi@guratan.test', 'user', [...$consent, 'consent_source' => 'self']),
            'citra' => $make('Citra Lestari', 'citra@guratan.test', 'user', [...$consent, 'consent_source' => 'self']),
            'dewi' => $make('Dewi Anggraini', 'dewi@guratan.test', 'user', [...$consent, 'consent_source' => 'self']),
            'eko' => $make('Eko Prasetyo', 'eko@guratan.test', 'user', [...$consent, 'consent_source' => 'self']),
            'fajar' => $make('Fajar Nugroho', 'fajar@guratan.test', 'user', [...$consent, 'consent_source' => 'grafolog']),
            'gita' => $make('Gita Permata', 'gita@guratan.test', 'user', [...$consent, 'consent_source' => 'grafolog']),
            'hendra' => $make('Hendra Wijaya', 'hendra@majujaya.test', 'user', [...$consent, 'consent_source' => 'hr', 'company_id' => $company->id]),
            'intan' => $make('Intan Sari', 'intan@majujaya.test', 'user', [...$consent, 'consent_source' => 'hr', 'company_id' => $company->id]),
        ];
    }

    private function createCommerceData(User $admin): void
    {
        DiscountCode::create([
            'code' => 'DEMO20', 'type' => 'percentage', 'value' => 20, 'applicable_tiers' => ['comprehensive', 'master'],
            'max_uses' => 100, 'used_count' => 1, 'is_active' => true, 'created_by' => $admin->id,
        ]);
        Announcement::create([
            'title' => 'Selamat datang di Guratan (demo)',
            'body' => 'Ini pengumuman contoh untuk klien. Data di lingkungan ini adalah data dummy.',
            'target_roles' => ['user'], 'is_active' => true, 'created_by' => $admin->id,
        ]);
    }

    private function clientOrder(User $client, string $tier, bool $paid, int $daysAgo): HandwritingSample
    {
        $project = Project::create(['source' => 'client', 'created_by' => $client->id]);
        $sample = $this->sample($project, $client, $client, $tier, $daysAgo);

        $base = $tier === 'master' ? 149000 : 49000;
        Payment::create([
            'sample_id' => $sample->id,
            'invoice_number' => 'INV-DEMO-'.$sample->id,
            'base_amount' => $base,
            'amount' => $base,
            'currency' => 'IDR',
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? $this->ago($daysAgo) : null,
        ]);

        return $sample;
    }

    private function walkIn(User $client, User $grafolog, string $tier, int $daysAgo): HandwritingSample
    {
        $project = Project::create(['source' => 'grafolog', 'created_by' => $grafolog->id]);

        return $this->sample($project, $client, $grafolog, $tier, $daysAgo);
    }

    private function sample(Project $project, User $subject, User $creator, string $tier, int $daysAgo): HandwritingSample
    {
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => $subject->id, 'created_by' => $creator->id,
            'tier' => $tier, 'status' => 'pending',
        ]);
        $sample->forceFill(['created_at' => $this->ago($daysAgo)])->save();
        $this->attachPhoto($sample, $subject, $creator);

        return $sample;
    }

    private function assign(HandwritingSample $sample, User $grafolog, User $by): void
    {
        Assignment::create(['sample_id' => $sample->id, 'grafolog_id' => $grafolog->id, 'assigned_by' => $by->id, 'status' => 'assigned']);
    }

    private function report(
        ScoringEngineService $engine,
        HandwritingSample $sample,
        User $grafolog,
        string $persona,
        string $reviewStatus,
        ?User $reviewer,
        int $daysAgo,
    ): PersonalityReport {
        mt_srand(crc32($sample->id.$persona));
        $skor = Aspek::with('sindrom')->get()
            ->mapWithKeys(fn (Aspek $a) => [
                $a->kode => max(1, min(10, self::PERSONAS[$persona][$a->sindrom->kode_romawi] + mt_rand(-1, 1))),
            ])->all();

        $generatedAt = $this->ago($daysAgo);
        $report = PersonalityReport::create([
            'sample_id' => $sample->id,
            'tier' => $sample->tier,
            'status' => 'completed',
            'data' => $engine->generate($skor),
            'generated_at' => $generatedAt,
            'review_status' => $reviewStatus,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => $reviewer ? $generatedAt->copy()->addHours(3) : null,
        ]);

        $aspekIds = Aspek::pluck('id', 'kode');
        foreach ($skor as $kode => $nilai) {
            ReportAspekScore::create(['report_id' => $report->id, 'aspek_id' => $aspekIds[$kode], 'skor' => $nilai]);
        }

        $sample->update(['status' => 'completed']);
        $sample->assignment?->update(['status' => 'completed']);

        return $report;
    }

    private function attachPhoto(HandwritingSample $sample, User $subject, User $uploader): void
    {
        $img = imagecreatetruecolor(900, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 250, 248, 240));
        $ink = imagecolorallocate($img, 30, 40, 110);
        $lines = [
            'Contoh tulisan tangan (DATA DUMMY)',
            "Nama: {$subject->name}",
            'Saya menulis kalimat ini di atas kertas polos tanpa garis.',
            'Huruf, spasi, kemiringan, dan tekanan pena akan diukur grafolog.',
            'Tanggal: '.now()->format('d-m-Y'),
        ];
        foreach ($lines as $i => $line) {
            imagestring($img, 5, 60 + $i * 6, 80 + $i * 90, $line, $ink);
        }
        ob_start();
        imagejpeg($img, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($img);

        $path = "samples/{$sample->id}/demo-tulisan.jpg";
        Storage::disk(SampleImage::DISK)->put($path, $bytes);
        $sample->images()->create([
            'path' => $path, 'original_name' => 'tulisan-tangan.jpg', 'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($bytes), 'uploaded_by' => $uploader->id,
        ]);
    }

    private function ago(int $days): Carbon
    {
        return now()->subDays($days)->setTime(9, 30);
    }
}
