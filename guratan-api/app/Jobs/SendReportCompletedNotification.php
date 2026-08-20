<?php

namespace App\Jobs;

use App\Mail\ReportCompletedMail;
use App\Models\PersonalityReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReportCompletedNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PersonalityReport $report) {}

    public function handle(): void
    {
        $owner = $this->report->sample?->user;

        if (! $owner) {
            return;
        }

        Mail::to($owner->email)->send(new ReportCompletedMail($this->report));
    }
}
