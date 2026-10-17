<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Aspek;
use App\Models\Assignment;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\JobPosition;
use App\Models\Order;
use App\Models\OrderBatch;
use App\Models\Payment;
use App\Models\PersonalityReport;
use App\Models\Product;
use App\Models\Project;
use App\Models\ReportAspekScore;
use App\Models\SampleConsultation;
use App\Models\SampleImage;
use App\Models\TokenCost;
use App\Models\User;
use App\Services\Reporting\ReportValidationService;
use App\Services\Scoring\ScoringEngineService;
use App\Services\TokenWalletService;
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
 * kandidat HR lewat pesanan B2B, dan perusahaan yang menunggu verifikasi.
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
        $this->createCommerceData($users['admin'], [$users['ani'], $users['bima']]);

        $ani = $users['ani'];
        $bima = $users['bima'];
        $spv = $users['supervisor'];

        // 1. Budi - pesan sendiri, lunas, dikerjakan Ani, laporan sudah disetujui.
        $budi = $this->clientOrder($users['budi'], 'master', paid: true, daysAgo: 12);
        $this->assign($budi, $ani, $users['admin']);
        $this->report($engine, $budi, $ani, 'pemimpin', PersonalityReport::REVIEW_APPROVED, $spv, daysAgo: 10);

        // 2. Citra - tier Master, disetujui + jadwal konsultasi.
        $citra = $this->clientOrder($users['citra'], 'master', paid: true, daysAgo: 8);
        $this->assign($citra, $ani, $users['admin']);
        // Citra: diterbitkan mandiri oleh grafolog (validasi supervisor opsional, Fase D).
        $this->report($engine, $citra, $ani, 'sosial', PersonalityReport::REVIEW_APPROVED, $ani, daysAgo: 6)
            ->update(['validation' => PersonalityReport::VALIDATION_SELF, 'validation_requested_at' => null]);
        SampleConsultation::create([
            'sample_id' => $citra->id,
            'scheduled_at' => now('Asia/Jakarta')->addDays(3)->setTime(10, 0)->utc(),
            'meeting_url' => 'https://meet.google.com/demo-guratan-01',
            'notes' => 'Siapkan 2-3 pertanyaan tentang hasil aspek Interpersonal Relations.',
            'scheduled_by' => $ani->id,
        ]);

        // 3. Dewi - lunas, belum ditugaskan (muncul di Pesanan Masuk admin).
        $this->clientOrder($users['dewi'], 'master', paid: true, daysAgo: 1);

        // 4. Eko - pesanan belum dibayar.
        $this->clientOrder($users['eko'], 'master', paid: false, daysAgo: 0);

        // 5. Fajar - klien walk-in Bima, laporan menunggu review supervisor.
        $fajar = $this->walkIn($users['fajar'], $bima, 'master', daysAgo: 3);
        $this->report($engine, $fajar, $bima, 'analitis', PersonalityReport::REVIEW_PENDING, null, daysAgo: 1);

        // 6. Gita - klien walk-in Ani, dikembalikan supervisor untuk revisi.
        $gita = $this->walkIn($users['gita'], $ani, 'master', daysAgo: 5);
        $report = $this->report($engine, $gita, $ani, 'tertekan', PersonalityReport::REVIEW_REVISION, $spv, daysAgo: 2);
        $report->update(['review_note' => 'Mohon cek ulang skor Stress Vulnerability - catatan pengukuran tekanan tidak konsisten dengan skor 8.']);

        // 7-8. Kandidat HR PT Maju Jaya - pesanan B2B "Paket Rekrutmen" (grafologi + wawancara),
        // HR memilih Bima. Hendra: laporan disetujui + wawancara selesai; Intan: sedang diproses.
        $project = Project::create(['name' => 'Rekrutmen Staf Keuangan Q4', 'source' => 'hr', 'created_by' => $users['hr']->id]);
        $hendra = $this->sample($project, $users['hendra'], $users['hr'], 'master', daysAgo: 9);
        $this->assign($hendra, $bima, $users['hr']);
        $this->report($engine, $hendra, $bima, 'analitis', PersonalityReport::REVIEW_APPROVED, $spv, daysAgo: 7);
        $intan = $this->sample($project, $users['intan'], $users['hr'], 'master', daysAgo: 2);
        $this->assign($intan, $bima, $users['hr']);
        $this->createB2bData($users, ['hendra' => $hendra, 'intan' => $intan]);

        $this->command?->info('Data demo dibuat. Semua akun berpassword: '.self::PASSWORD);
        $this->command?->table(['Role', 'Email'], collect($users)->map(fn (User $u) => [$u->role, $u->email])->values()->all());
    }

    /** @return array<string, User> */
    private function createUsers(): array
    {
        $company = Company::create([
            'name' => 'PT Maju Jaya (Demo)', 'industry' => 'Distribusi', 'employee_range' => '201-1000',
            'phone' => '021-5550101', 'address' => 'Jl. Sudirman 1, Jakarta', 'verified_at' => now(),
        ]);
        $consent = ['consented_at' => now(), 'consent_version' => User::CONSENT_VERSION];

        $make = fn (string $name, string $email, string $role, array $extra = []) => User::create([
            'name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'role' => $role, ...$extra,
        ]);

        $web = [...$consent, 'consent_source' => 'self', 'acquisition_source' => 'website'];
        $ani = $make('Ani Grafolog', 'ani.grafolog@guratan.test', 'grafolog', [
            'grafolog_status' => 'verified', 'city' => 'Jakarta', 'experience_years' => 8,
            'specializations' => ['Pengembangan diri', 'Konseling remaja'],
            'bio' => 'Grafolog bersertifikat, fokus pada pengembangan diri dan pendampingan klien individu.',
        ]);
        $bima = $make('Bima Grafolog', 'bima.grafolog@guratan.test', 'grafolog', [
            'grafolog_status' => 'verified', 'city' => 'Bandung', 'experience_years' => 12,
            'specializations' => ['Rekrutmen', 'Wawancara berbasis perilaku'],
            'bio' => 'Berpengalaman mendampingi tim HR untuk asesmen kandidat staf hingga manajerial.',
        ]);
        $bawaan = fn (User $g) => [...$consent, 'consent_source' => 'grafolog', 'acquisition_source' => 'grafolog', 'referred_by' => $g->id];
        $b2b = [...$consent, 'consent_source' => 'hr', 'acquisition_source' => 'b2b', 'company_id' => $company->id];

        return [
            'admin' => $make('Admin Demo', 'admin.demo@guratan.test', 'administrator'),
            'supervisor' => $make('Sari Supervisor', 'supervisor@guratan.test', 'supervisor'),
            'ani' => $ani,
            'bima' => $bima,
            'hr' => $make('Rina HR', 'hr@majujaya.test', 'hr', ['company_id' => $company->id]),
            'budi' => $make('Budi Santoso', 'budi@guratan.test', 'user', $web),
            'citra' => $make('Citra Lestari', 'citra@guratan.test', 'user', $web),
            'dewi' => $make('Dewi Anggraini', 'dewi@guratan.test', 'user', $web),
            'eko' => $make('Eko Prasetyo', 'eko@guratan.test', 'user', $web),
            'fajar' => $make('Fajar Nugroho', 'fajar@guratan.test', 'user', $bawaan($bima)),
            'gita' => $make('Gita Permata', 'gita@guratan.test', 'user', $bawaan($ani)),
            'hendra' => $make('Hendra Wijaya', 'hendra@majujaya.test', 'user', $b2b),
            // Perusahaan yang mendaftar mandiri dan menunggu verifikasi admin.
            'hr_pending' => $make('Yoga HR', 'hr@sinarabadi.test', 'hr', ['company_id' => Company::create([
                'name' => 'PT Sinar Abadi (Demo)', 'status' => Company::STATUS_PENDING, 'industry' => 'Teknologi',
                'employee_range' => '11-50', 'phone' => '022-777', 'address' => 'Jl. Dago 10, Bandung',
            ])->id]),
            'intan' => $make('Intan Sari', 'intan@majujaya.test', 'user', $b2b),
        ];
    }

    private function createCommerceData(User $admin, array $grafologs = []): void
    {
        // Biaya validasi supervisor 2 token + saldo awal grafolog demo.
        TokenCost::setTokensFor(ReportValidationService::COST_KEY, 2, $admin);
        foreach ($grafologs as $g) {
            app(TokenWalletService::class)->credit($g, 10);
        }
        DiscountCode::create([
            'code' => 'DEMO20', 'type' => 'percentage', 'value' => 20, 'applicable_tiers' => ['master'],
            'is_public' => true, 'description' => 'Hemat 20% Master Graphology (demo)',
            'max_uses' => 100, 'used_count' => 1, 'is_active' => true, 'created_by' => $admin->id,
        ]);
        Announcement::create([
            'title' => 'Selamat datang di Guratan (demo)',
            'body' => 'Ini pengumuman contoh untuk klien. Data di lingkungan ini adalah data dummy.',
            'target_roles' => ['user'], 'is_active' => true, 'created_by' => $admin->id,
        ]);
    }

    /** @param array<string, User> $users @param array<string, HandwritingSample> $samples */
    private function createB2bData(array $users, array $samples): void
    {
        $hr = $users['hr'];
        $product = Product::where('kode', 'paket-rekrutmen')->with('components')->firstOrFail();
        $price = $product->currentPrice() ?? 229000;
        $position = JobPosition::create([
            'company_id' => $hr->company_id, 'title' => 'Staf Keuangan', 'created_by' => $hr->id,
            'description' => 'Rekonsiliasi, pelaporan bulanan, koordinasi dengan auditor.',
            'key_aspek_ids' => Aspek::orderBy('id')->limit(5)->pluck('id')->all(),
        ]);
        $batch = OrderBatch::create([
            'company_id' => $hr->company_id, 'product_id' => $product->id, 'grafolog_id' => $users['bima']->id,
            'status' => Order::STATUS_PROCESSING, 'base_amount' => $price * 2, 'amount' => $price * 2,
            'created_by' => $hr->id, 'paid_at' => $this->ago(9),
        ]);
        Payment::create([
            'order_batch_id' => $batch->id, 'invoice_number' => 'INV-DEMO-B'.$batch->id,
            'base_amount' => $batch->amount, 'amount' => $batch->amount, 'currency' => 'IDR',
            'status' => 'paid', 'paid_at' => $this->ago(9),
        ]);
        $wawancara = $product->components->firstWhere('tipe', 'wawancara');

        foreach (['hendra' => 'selesai', 'intan' => 'asesmen'] as $key => $stage) {
            $user = $users[$key];
            CandidateProfile::create([
                'user_id' => $user->id, 'company_id' => $hr->company_id, 'job_position_id' => $position->id,
                'education' => 'S1', 'experience_years' => $key === 'hendra' ? 4 : 2, 'stage' => $stage, 'created_by' => $hr->id,
            ]);
            $order = Order::create([
                'batch_id' => $batch->id, 'user_id' => $user->id, 'product_id' => $product->id,
                'sample_id' => $samples[$key]->id, 'grafolog_id' => $users['bima']->id,
                'status' => $key === 'hendra' ? Order::STATUS_DONE : Order::STATUS_PROCESSING,
                'base_amount' => $price, 'amount' => $price, 'source' => 'b2b', 'created_by' => $hr->id,
                'paid_at' => $this->ago(9), 'completed_at' => $key === 'hendra' ? $this->ago(5) : null,
            ]);
            $order->deliveries()->create([
                'product_component_id' => $wawancara->id, 'user_id' => $user->id, 'assigned_to' => $users['bima']->id,
            ] + ($key === 'hendra' ? [
                'status' => 'selesai', 'scheduled_at' => $this->ago(6), 'report_submitted_at' => $this->ago(5),
                'report' => [
                    'komunikasi' => 4, 'stabilitas_emosi' => 4, 'motivasi' => 3, 'integritas' => 5, 'kerja_sama' => 4,
                    'catatan' => 'Teliti dan terstruktur saat menjelaskan proses rekonsiliasi; agak berhati-hati di situasi baru.',
                    'rekomendasi' => 'Direkomendasikan',
                ],
            ] : ['status' => 'ditugaskan']));
        }
    }

    private function clientOrder(User $client, string $tier, bool $paid, int $daysAgo): HandwritingSample
    {
        $project = Project::create(['source' => 'client', 'created_by' => $client->id]);
        $sample = $this->sample($project, $client, $client, $tier, $daysAgo);

        $base = 149000;
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
            'validation' => PersonalityReport::VALIDATION_SUPERVISOR,
            'validation_requested_at' => $generatedAt,
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
        // Minimal FHD, sama seperti aturan unggah (StoreSampleImagesRequest).
        $img = imagecreatetruecolor(1920, 1080);
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
            imagestring($img, 5, 120 + $i * 12, 180 + $i * 160, $line, $ink);
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
            'width' => 1920, 'height' => 1080,
        ]);
    }

    private function ago(int $days): Carbon
    {
        return now()->subDays($days)->setTime(9, 30);
    }
}
