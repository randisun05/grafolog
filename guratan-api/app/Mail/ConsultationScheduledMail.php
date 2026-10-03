<?php

namespace App\Mail;

use App\Models\SampleConsultation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsultationScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SampleConsultation $consultation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Jadwal Konsultasi Grafologi Anda - Guratan');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consultation-scheduled',
            with: ['consultation' => $this->consultation->loadMissing('sample.user', 'scheduler')],
        );
    }
}
