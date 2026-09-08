<?php

namespace App\Jobs;

use App\Mail\ReportCompletedMail;
use App\Models\PersonalityReport;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReportCompletedNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PersonalityReport $report) {}

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $owner = $this->report->sample?->user;

        if (! $owner) {
            return;
        }

        $dispatcher->sendEmail('laporan_selesai', $owner, $owner->email, new ReportCompletedMail($this->report));

        // WA cuma pelengkap - kegagalan/absennya nomor tidak boleh
        // menggagalkan job ini (email di atas sudah jadi jalur utama).
        $dispatcher->sendWhatsApp(
            'laporan_selesai',
            $owner,
            $owner->phone,
            "Halo {$owner->name}, laporan kepribadian Anda dari Guratan sudah siap! ".
            'Login ke akun Anda untuk melihat & mengunduh laporan lengkapnya: '.
            rtrim((string) config('app.frontend_url'), '/').'/riwayat'
        );
    }
}
