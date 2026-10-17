<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Company $company, public User $hr) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->company->isVerified()
            ? 'Perusahaan Anda Sudah Diverifikasi - Guratan'
            : 'Hasil Verifikasi Perusahaan - Guratan');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.company-verification');
    }
}
