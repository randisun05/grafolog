<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email notifikasi generik (kembaran pesan di kotak masuk). */
class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $judul, public string $isi, public ?string $tautan = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->judul.' - Guratan');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification');
    }
}
