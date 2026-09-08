<?php

namespace App\Jobs;

use App\Mail\ReportCompletedMail;
use App\Models\PersonalityReport;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReportCompletedNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PersonalityReport $report) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $owner = $this->report->sample?->user;

        if (! $owner) {
            return;
        }

        Mail::to($owner->email)->send(new ReportCompletedMail($this->report));

        // WA cuma pelengkap - kegagalan/absennya nomor tidak boleh
        // menggagalkan job ini (email di atas sudah jadi jalur utama).
        $whatsApp->send(
            $owner->phone,
            "Halo {$owner->name}, laporan kepribadian Anda dari Guratan sudah siap! ".
            'Login ke akun Anda untuk melihat & mengunduh laporan lengkapnya: '.
            rtrim((string) config('app.frontend_url'), '/').'/riwayat'
        );
    }
}
