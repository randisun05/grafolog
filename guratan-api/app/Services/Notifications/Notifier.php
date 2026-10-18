<?php

namespace App\Services\Notifications;

use App\Mail\NotificationMail;
use App\Models\Broadcast;
use App\Models\InboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Satu pintu notifikasi (2026-10-18): pesan kotak masuk + email (antrean) ke pengguna.
 * Kegagalan email tidak boleh menggagalkan proses bisnis - dicatat lalu dilewati.
 */
class Notifier
{
    public function send(User $user, string $type, string $title, string $body, ?string $url = null, bool $mail = true, ?int $broadcastId = null): InboxMessage
    {
        $message = InboxMessage::create([
            'user_id' => $user->id, 'broadcast_id' => $broadcastId, 'type' => $type,
            'title' => $title, 'body' => $body, 'url' => $url,
        ]);
        if ($mail) {
            $this->mail($user, $title, $body, $url);
        }

        return $message;
    }

    /** Beri tahu semua administrator (kotak masuk saja - tidak membanjiri email). */
    public function toAdmins(string $type, string $title, string $body, ?string $url = null): void
    {
        User::where('role', 'administrator')->get(['id', 'name', 'email', 'role'])
            ->each(fn (User $a) => $this->send($a, $type, $title, $body, $url, mail: false));
    }

    /** Kirim ke banyak pengguna sekaligus (target_roles null = semua). */
    public function broadcast(Broadcast $broadcast): int
    {
        $query = User::query();
        if ($broadcast->target_roles) {
            $query->whereIn('role', $broadcast->target_roles);
        }
        $count = 0;
        $query->select(['id', 'name', 'email', 'role'])->chunkById(200, function ($users) use ($broadcast, &$count) {
            foreach ($users as $user) {
                $this->send($user, 'broadcast', $broadcast->title, $broadcast->body, null, $broadcast->send_email, $broadcast->id);
                $count++;
            }
        });
        $broadcast->update(['recipients_count' => $count]);

        return $count;
    }

    private function mail(User $user, string $title, string $body, ?string $url): void
    {
        try {
            Mail::to($user->email)->queue(new NotificationMail($title, $body, $url));
        } catch (\Throwable $e) {
            Log::warning('Notifier: email gagal diantrekan', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
