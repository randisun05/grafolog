<?php

namespace App\Console\Commands;

use App\Models\HandwritingSample;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Temuan keamanan 2026-07-27: gambar tier Rapid lama (kolom `handwriting_samples.image_path`) tersimpan di
 * disk PUBLIK. Perintah ini memindahkannya ke disk privat (path sama). Tidak ada kode yang menyajikan
 * `image_path`, jadi aman; berkas hilang dari URL publik. Idempoten.
 */
class PrivatizeLegacyImages extends Command
{
    protected $signature = 'samples:privatize-legacy-images {--dry-run}';

    protected $description = 'Pindahkan gambar Rapid lama dari disk publik ke disk privat';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;
        $missing = 0;

        HandwritingSample::whereNotNull('image_path')->chunkById(200, function ($samples) use ($public, $private, &$moved, &$missing) {
            foreach ($samples as $s) {
                if (! $public->exists($s->image_path)) {
                    $missing++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    $private->put($s->image_path, $public->get($s->image_path));
                    $public->delete($s->image_path);
                }
                $moved++;
            }
        });
        $this->info(($this->option('dry-run') ? '[dry-run] ' : '')."Dipindahkan {$moved}, tidak ditemukan di disk publik {$missing}.");

        return self::SUCCESS;
    }
}
