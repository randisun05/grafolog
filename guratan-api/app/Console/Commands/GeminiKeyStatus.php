<?php

namespace App\Console\Commands;

use App\Services\Llm\GeminiKeyPool;
use Illuminate\Console\Command;

class GeminiKeyStatus extends Command
{
    protected $signature = 'llm:gemini-status';

    protected $description = 'Tampilkan status slot API key Gemini (aktif/siap/istirahat) tanpa menampilkan key-nya';

    public function handle(): int
    {
        $pool = GeminiKeyPool::fromConfig();

        if ($pool->isEmpty()) {
            $this->warn('Belum ada GEMINI_API_KEY_1..10 yang diisi di .env.');

            return self::FAILURE;
        }

        $this->table(['Slot', 'Status', 'Istirahat sampai', 'Alasan'], array_map(
            fn ($row) => [$row['slot'], $row['status'], $row['until'] ?? '-', $row['reason'] ?? '-'],
            $pool->status(),
        ));
        $this->line('Provider aktif: '.config('services.llm.provider').' · model: '.config('services.gemini.model'));

        return self::SUCCESS;
    }
}
