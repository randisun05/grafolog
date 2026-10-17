<?php

namespace App\Services;

use App\Mail\ClientInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Undangan untuk klien yang didaftarkan grafolog: klien mengatur kata sandinya sendiri lewat
 * tautan (bukan password yang disampaikan grafolog), jadi ia benar-benar memakai aplikasi -
 * menerima promo, melihat laporan, memesan produk lain.
 */
class ClientInvitationService
{
    public function send(User $client, User $grafolog): string
    {
        $token = Password::broker('invitations')->createToken($client);
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $token, 'email' => $client->email, 'undangan' => 1,
        ]);

        Mail::to($client->email)->queue(new ClientInvitationMail($client, $grafolog, $url));

        return $url;
    }
}
