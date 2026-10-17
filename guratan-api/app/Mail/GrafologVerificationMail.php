<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GrafologVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $grafolog) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->grafolog->grafolog_status === 'verified'
            ? 'Akun Grafolog Anda Sudah Diverifikasi - Guratan'
            : 'Hasil Verifikasi Akun Grafolog - Guratan');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.grafolog-verification');
    }
}
