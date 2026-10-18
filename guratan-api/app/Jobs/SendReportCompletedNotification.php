<?php

namespace App\Jobs;

use App\Mail\ReportCompletedMail;
use App\Models\PersonalityReport;
use App\Services\Notifications\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReportCompletedNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PersonalityReport $report) {}

    public function handle(Notifier $notifier): void
    {
        $order = $this->report->sample?->order;

        // Laporan kandidat B2B milik perusahaan: HR yang diberi tahu, bukan kandidat.
        if ($order?->batch) {
            if ($hr = $order->batch->creator) {
                $notifier->send($hr, 'laporan_terbit', 'Laporan kandidat siap',
                    "Laporan grafologi {$order->user->name} sudah terbit. Lihat di tab Analisis.", '/hr/candidates?tab=analisis');
            }

            return;
        }

        $owner = $this->report->sample?->user;
        if (! $owner) {
            return;
        }

        Mail::to($owner->email)->send(new ReportCompletedMail($this->report));
        $notifier->send($owner, 'laporan_terbit', 'Laporan Anda siap', 'Laporan grafologi Anda sudah terbit dan bisa dibaca.', "/reports/{$this->report->id}", mail: false);
    }
}
