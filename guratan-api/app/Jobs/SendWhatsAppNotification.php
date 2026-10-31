<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kirim satu pesan WhatsApp (Fonnte) ke pengguna, dipicu Notifier::send() PARALEL dengan email.
 * WA hanya pelengkap: tidak diulang (tries 1), hasilnya selalu dicatat ke notification_logs
 * (sent / failed / skipped) supaya terlihat di /admin/notification-logs.
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public ?int $userId,
        public string $type,
        public ?string $phone,
        public string $message,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        if (! $this->phone) {
            NotificationLog::record('whatsapp', $this->type, $this->userId, '-', 'skipped', 'Nomor WhatsApp belum diisi.');

            return;
        }
        if (! $whatsApp->isConfigured()) {
            NotificationLog::record('whatsapp', $this->type, $this->userId, $this->phone, 'skipped', 'FONNTE_TOKEN belum diisi.');

            return;
        }

        if ($whatsApp->send($this->phone, $this->message)) {
            NotificationLog::record('whatsapp', $this->type, $this->userId, $this->phone, 'sent');
        } else {
            NotificationLog::record('whatsapp', $this->type, $this->userId, $this->phone, 'failed',
                'Fonnte menolak permintaan atau terjadi galat jaringan - lihat log server untuk detail.');
        }
    }
}
