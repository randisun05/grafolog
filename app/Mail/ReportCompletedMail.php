<?php

namespace App\Mail;

use App\Models\PersonalityReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PersonalityReport $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Laporan Kepribadian Anda Sudah Siap - Guratan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report-completed',
            with: ['report' => $this->report],
        );
    }
}
