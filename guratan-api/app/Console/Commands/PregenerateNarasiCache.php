<?php

namespace App\Console\Commands;

use App\Models\Aspek;
use App\Services\Llm\NarasiCacheService;
use Illuminate\Console\Command;

class PregenerateNarasiCache extends Command
{
    protected $signature = 'narasi:pregenerate';

    protected $description = 'Siapkan narasi Bahasa Indonesia 40 aspek x 4 level: pakai terjemahan KB, sisanya lewat LLM (bila aktif) lalu cache permanen';

    public function handle(NarasiCacheService $service): int
    {
        $levels = ['very_high', 'high', 'medium', 'low'];
        $aspekList = Aspek::all();
        $missing = [];
        $bar = $this->output->createProgressBar($aspekList->count() * count($levels));

        $bar->start();
        foreach ($aspekList as $aspek) {
            foreach ($levels as $level) {
                if (NarasiCacheService::teksSumber($aspek, $level, 'en') !== '' && $service->teksIndonesia($aspek, $level) === null) {
                    $missing[] = "{$aspek->kode}/{$level}";
                }
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine(2);

        if ($missing === []) {
            $this->info('Semua narasi yang ada sumbernya sudah punya versi Bahasa Indonesia.');
        } else {
            $this->warn('Belum ada terjemahan untuk '.count($missing).' kombinasi (LLM nonaktif/limit): '.implode(', ', $missing));
        }

        return self::SUCCESS;
    }
}
