<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Payment\PaymentMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * `php artisan app:preflight` - pemeriksaan kesiapan produksi (2026-10-19). Exit 1 bila ada GAGAL.
 * Peringatan tidak menggagalkan tetapi harus dibaca sebelum go-live. Lihat DEPLOYMENT.md.
 */
class Preflight extends Command
{
    protected $signature = 'app:preflight {--strict : Peringatan juga dianggap gagal}';

    protected $description = 'Periksa konfigurasi sebelum go-live (env, DB, email, DOKU, antrean, scheduler)';

    private int $fails = 0;

    private int $warns = 0;

    public function handle(): int
    {
        $this->fails = $this->warns = 0;
        $isProd = app()->environment('production');
        $this->check('APP_ENV=production', $isProd, 'warn', 'APP_ENV bukan production');
        $this->check('APP_DEBUG=false', ! config('app.debug'), 'fail', 'APP_DEBUG aktif: stack trace bocor ke pengguna');
        $this->check('APP_KEY terisi', filled(config('app.key')), 'fail', 'APP_KEY kosong (php artisan key:generate)');
        $this->check('APP_URL https', str_starts_with((string) config('app.url'), 'https://'), $isProd ? 'fail' : 'warn', 'APP_URL bukan https');
        $this->check('FRONTEND_URL https', str_starts_with((string) config('app.frontend_url'), 'https://'), $isProd ? 'fail' : 'warn', 'FRONTEND_URL bukan https');
        $origins = (string) config('cors.allowed_origins.0', '');
        $this->check('CORS bukan localhost', ! str_contains(implode(',', (array) config('cors.allowed_origins')), 'localhost'), $isProd ? 'fail' : 'warn', "CORS_ALLOWED_ORIGINS masih localhost ({$origins})");

        $this->check('Database terhubung', $this->dbOk(), 'fail', 'Tidak bisa query database');
        $this->check('Storage privat bisa ditulis', $this->storageOk(), 'fail', 'storage/app tidak bisa ditulis');

        $mailer = config('mail.default');
        $this->check('MAIL_MAILER nyata (bukan log/array)', ! in_array($mailer, ['log', 'array'], true), $isProd ? 'fail' : 'warn', "MAIL_MAILER={$mailer}: email tidak terkirim ke pengguna");
        $this->check('MAIL_FROM terisi', filled(config('mail.from.address')) && ! str_contains((string) config('mail.from.address'), 'example'), 'warn', 'MAIL_FROM_ADDRESS masih contoh');

        $bypass = PaymentMode::bypassed();
        $this->check('Mode uji pembayaran mati', ! $bypass, $isProd ? 'fail' : 'warn', 'PAYMENT_BYPASS/kredensial DOKU kosong: pembayaran langsung dianggap lunas');
        $this->check('Kredensial DOKU terisi', filled(config('services.doku.client_id')) && filled(config('services.doku.secret_key')), $isProd ? 'fail' : 'warn', 'DOKU_CLIENT_ID/DOKU_SECRET_KEY kosong');
        $this->check('DOKU_IS_PRODUCTION=true', (bool) config('services.doku.is_production'), $isProd ? 'fail' : 'warn', 'DOKU masih sandbox');

        $this->check('Antrean bukan sync', config('queue.default') !== 'sync', 'fail', 'QUEUE_CONNECTION=sync: email memperlambat request');
        $this->check('Worker antrean hidup', $this->queueHealthy(), 'warn', 'Ada job menunggu > 10 menit: jalankan `php artisan queue:work` via supervisor/systemd');
        $beat = Cache::get('scheduler_heartbeat');
        $this->check('Scheduler berjalan (cron schedule:run)', $beat && now()->diffInMinutes($beat) < 5, 'warn', 'Tidak ada detak scheduler 5 menit terakhir: pasang cron `* * * * * php artisan schedule:run`');

        $this->check('Token sesi kedaluwarsa', (int) config('sanctum.expiration') > 0, 'fail', 'SANCTUM_EXPIRATION kosong');
        $this->check('Akun administrator ada', User::where('role', 'administrator')->exists(), 'fail', 'Belum ada administrator (isi ADMIN_* lalu db:seed --class=AdministratorSeeder)');
        $this->check('Tidak ada akun demo', ! User::where('email', 'like', '%@guratan.test')->exists(), $isProd ? 'fail' : 'warn', 'Akun demo @guratan.test masih ada');
        $this->check('LLM terkonfigurasi', config('services.llm.provider', env('LLM_PROVIDER', 'none')) !== 'none', 'warn', 'LLM_PROVIDER=none: analisis AI memakai ringkasan aturan, terjemahan tidak otomatis');
        $this->check('Link storage publik tidak dipakai', ! is_link(public_path('storage')) || ! $isProd, 'warn', 'public/storage ada - pastikan tidak ada berkas sensitif di disk public');

        $this->newLine();
        $this->line("Ringkasan: {$this->fails} gagal, {$this->warns} peringatan.");

        return $this->fails > 0 || ($this->option('strict') && $this->warns > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $ok, string $severity, string $hint): void
    {
        if ($ok) {
            $this->line("  <info>OK</info>      {$label}");

            return;
        }
        if ($severity === 'fail') {
            $this->fails++;
            $this->line("  <error>GAGAL</error>   {$label} - {$hint}");
        } else {
            $this->warns++;
            $this->line("  <comment>PERINGATAN</comment> {$label} - {$hint}");
        }
    }

    private function dbOk(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function storageOk(): bool
    {
        try {
            $f = 'preflight-'.uniqid().'.tmp';
            Storage::disk('local')->put($f, 'x');
            Storage::disk('local')->delete($f);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function queueHealthy(): bool
    {
        if (config('queue.default') !== 'database') {
            return true;
        }
        try {
            return ! DB::table('jobs')->where('created_at', '<', now()->subMinutes(10)->timestamp)->exists();
        } catch (Throwable) {
            return true;
        }
    }
}
