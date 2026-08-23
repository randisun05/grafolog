<?php

namespace App\Console\Commands;

use App\Models\Aspek;
use App\Services\Llm\NarasiCacheService;
use Illuminate\Console\Command;

class PregenerateNarasiCache extends Command
{
    protected $signature = 'narasi:pregenerate {--bahasa=id}';

    protected $description = 'Panggil LLM sekali untuk semua 40 aspek x 4 level (160 kombinasi), simpan ke narasi_cache permanen';

    public function handle(NarasiCacheService $service): int
    {
        $bahasa = $this->option('bahasa');
        $levels = ['very_high', 'high', 'medium', 'low'];
        $aspekList = Aspek::all();
        $total = $aspekList->count() * count($levels);
        $bar = $this->output->createProgressBar($total);

        $bar->start();
        foreach ($aspekList as $aspek) {
            foreach ($levels as $level) {
                $service->ambil($aspek, $level, $bahasa); // otomatis skip kalau sudah ada di cache
                $bar->advance();
            }
        }
        $bar->finish();

        $this->newLine(2);
        $this->info("Selesai. Total kombinasi: {$total} (yang sudah ada di cache otomatis dilewati, tidak panggil LLM ulang).");

        return self::SUCCESS;
    }
}
