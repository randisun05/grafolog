<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Titik tunggal yang dipanggil setiap kali aplikasi mengirim notifikasi
 * ke user (email dan/atau WhatsApp) - satu-satunya tempat yang menulis
 * ke `NotificationLog` (lihat model itu + migrasi
 * `create_notification_logs_table` untuk arti status sent/failed/skipped).
 * Dipakai oleh `SendReportCompletedNotification` dan
 * `AuthController::forgotPassword()` - kalau ada titik notifikasi baru
 * di masa depan, pakai class ini juga supaya otomatis kelihatan di
 * `/admin/notification-logs`, jangan panggil `Mail::` atau
 * `WhatsAppService` langsung.
 */
class NotificationDispatcher
{
    public function __construct(private WhatsAppService $whatsApp) {}

    /**
     * Kirim email lalu catat hasilnya. Exception dari Mail::send() TETAP
     * dilempar ulang setelah dicatat - logging tidak boleh mengubah
     * perilaku keandalan yang sudah ada (job yang gagal kirim tetap retry
     * lewat mekanisme queue, request sinkron yang gagal tetap terlihat
     * gagal), cuma menambah jejak yang bisa dibaca admin.
     *
     * @throws Throwable kalau Mail::send() gagal - diteruskan apa adanya.
     */
    public function sendEmail(string $type, ?User $user, string $recipientEmail, Mailable $mailable): void
    {
        try {
            Mail::to($recipientEmail)->send($mailable);
            NotificationLog::record('email', $type, $user?->id, $recipientEmail, 'sent');
        } catch (Throwable $e) {
            NotificationLog::record('email', $type, $user?->id, $recipientEmail, 'failed', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Kirim WhatsApp lalu catat hasilnya. TIDAK PERNAH melempar exception -
     * WA selalu pelengkap di samping email (lihat catatan class
     * WhatsAppService), jadi kegagalan di sini cuma tercatat sebagai baris
     * 'failed'/'skipped', tidak pernah menggagalkan pemanggil.
     */
    public function sendWhatsApp(string $type, ?User $user, ?string $phone, string $message): void
    {
        if (! $phone) {
            NotificationLog::record('whatsapp', $type, $user?->id, '-', 'skipped', 'Nomor WhatsApp belum diisi.');

            return;
        }

        if (! $this->whatsApp->isConfigured()) {
            NotificationLog::record('whatsapp', $type, $user?->id, $phone, 'skipped', 'FONNTE_TOKEN belum diisi.');

            return;
        }

        if ($this->whatsApp->send($phone, $message)) {
            NotificationLog::record('whatsapp', $type, $user?->id, $phone, 'sent');
        } else {
            NotificationLog::record(
                'whatsapp',
                $type,
                $user?->id,
                $phone,
                'failed',
                'Fonnte menolak permintaan atau terjadi galat jaringan - lihat log server untuk detail.'
            );
        }
    }
}
