<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class PruneActivityLogs extends Command
{
    protected $signature = 'activity:prune {--days= : Hari simpan (default config activity.retention_days)}';

    protected $description = 'Hapus log aktivitas yang lebih tua dari masa simpan';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('activity.retention_days'));
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Dihapus {$deleted} baris log aktivitas (> {$days} hari).");

        return self::SUCCESS;
    }
}
