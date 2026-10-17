<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $client, public User $grafolog, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Undangan Akun Guratan dari Grafolog Anda');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client-invitation');
    }
}
