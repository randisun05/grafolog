<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Cadangan basis data harian (2026-10-19): mysqldump terkompresi (MySQL/MariaDB) atau salinan berkas
 * (SQLite) ke storage/app/private/backups, simpan N terakhir. BUKAN pengganti backup di luar server:
 * salin folder itu + `storage/app/private` (scan, CV, sertifikat) ke penyimpanan terpisah (lihat DEPLOYMENT.md).
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=14 : Jumlah cadangan yang disimpan} {--connection= : Koneksi database (default koneksi aktif)}';

    protected $description = 'Cadangkan basis data ke storage/app/private/backups';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $stamp = now()->format('Ymd-His');
        $conn = $this->option('connection') ?: config('database.default');
        $cfg = config("database.connections.{$conn}");

        if ($cfg['driver'] === 'sqlite') {
            $path = $cfg['database'];
            if ($path === ':memory:' || ! is_file($path)) {
                $this->error('Database SQLite tidak berupa berkas.');

                return self::FAILURE;
            }
            $disk->put("backups/db-{$stamp}.sqlite", file_get_contents($path));
        } elseif (in_array($cfg['driver'], ['mysql', 'mariadb'], true)) {
            $process = Process::fromShellCommandline(
                'mysqldump --single-transaction --quick --no-tablespaces -h "$H" -P "$P" -u "$U" "$D"',
                null, ['MYSQL_PWD' => $cfg['password'], 'H' => $cfg['host'], 'P' => $cfg['port'], 'U' => $cfg['username'], 'D' => $cfg['database']],
            );
            $process->setTimeout(600)->run();
            if (! $process->isSuccessful()) {
                $this->error('mysqldump gagal: '.trim($process->getErrorOutput()));

                return self::FAILURE;
            }
            $disk->put("backups/db-{$stamp}.sql.gz", gzencode($process->getOutput(), 6));
        } else {
            $this->error("Driver {$cfg['driver']} belum didukung.");

            return self::FAILURE;
        }

        $files = collect($disk->files('backups'))->sort()->values();
        $files->slice(0, max(0, $files->count() - (int) $this->option('keep')))->each(fn ($f) => $disk->delete($f));
        $this->info("Cadangan dibuat: backups/db-{$stamp}; tersimpan ".min($files->count(), (int) $this->option('keep')).' berkas.');

        return self::SUCCESS;
    }
}
