<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup database (spatie/laravel-backup) - lihat DEPLOYMENT.md "Backup
// database". Ini cuma MENDAFTARKAN jadwal - jadwal ini TIDAK jalan sendiri
// tanpa `php artisan schedule:work` (dev) atau cron 1x/menit memanggil
// `schedule:run` (production) yang benar-benar berjalan di server, lihat
// catatan di DEPLOYMENT.md.
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->daily()->at('10:00');
