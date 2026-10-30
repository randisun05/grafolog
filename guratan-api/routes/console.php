<?php

use App\Services\Commerce\GrafologMatchingService;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => app(ReportValidationService::class)->sweep())
    ->hourly()->name('supervisi-sweep');
Schedule::command('activity:prune')->daily();
Schedule::call(fn () => app(GrafologMatchingService::class)->sweep())
    ->everyFifteenMinutes()->name('konfirmasi-grafolog-sweep');
Schedule::command('db:backup')->dailyAt('02:30');
Schedule::call(fn () => Cache::put('scheduler_heartbeat', now(), 600))->everyMinute()->name('scheduler-heartbeat');
