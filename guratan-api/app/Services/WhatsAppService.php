<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integrasi Fonnte (https://fonnte.com) - WA gateway lokal Indonesia,
 * dipilih 2026-09-07 karena punya kuota gratis buat mulai tanpa verifikasi
 * bisnis (beda dari Meta WhatsApp Cloud API resmi yang butuh Facebook
 * Business Manager). Satu endpoint REST sederhana: POST token+target+message,
 * tidak ada OAuth/template approval seperti Cloud API.
 *
 * Env-gated sama pola DokuService - TAPI beda filosofi: DOKU melempar
 * RuntimeException kalau kredensial kosong (pembayaran gagal harus
 * kelihatan), WhatsAppService diam-diam SKIP (return false) kalau token
 * kosong - notifikasi WA itu pelengkap di samping email yang sudah
 * terkirim duluan di titik pemanggilan manapun (lihat
 * SendReportCompletedNotification, AuthController::forgotPassword), jadi
 * kegagalan/absennya WA TIDAK BOLEH menggagalkan alur utama (laporan tetap
 * selesai, reset password tetap jalan lewat email) - sama semangatnya
 * dengan MAIL_MAILER=log yang tidak menggagalkan request cuma karena SMTP
 * belum tersambung.
 */
class WhatsAppService
{
    private const ENDPOINT = 'https://api.fonnte.com/send';

    private ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? config('services.fonnte.token');
    }

    /**
     * Dipakai NotificationDispatcher untuk membedakan status 'skipped'
     * (kredensial memang belum diisi, belum pernah dicoba dikirim) dari
     * 'failed' (dicoba, Fonnte menolak/exception) di NotificationLog -
     * tanpa ini pemanggil harus menebak dari nilai balik `send()` yang
     * sama-sama `false` untuk kedua kasus.
     */
    public function isConfigured(): bool
    {
        return (bool) $this->token;
    }

    /**
     * Kirim satu pesan WA. Return true kalau Fonnte menerima permintaan
     * (bukan jaminan pesan sampai di HP penerima - itu di luar kendali
     * kita), false kalau token belum diisi atau pengiriman gagal (dicatat
     * ke log, tidak melempar exception - lihat catatan class di atas soal
     * kenapa ini harus diam-diam gagal, bukan mengganggu alur utama).
     */
    public function send(?string $phone, string $message): bool
    {
        if (! $phone) {
            return false;
        }

        if (! $this->token) {
            Log::info('WhatsAppService: FONNTE_TOKEN belum diisi, pengiriman WA dilewati.', [
                'phone' => $this->normalizePhone($phone),
            ]);

            return false;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $this->token])
                ->asForm()
                ->post(self::ENDPOINT, [
                    'target' => $this->normalizePhone($phone),
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsAppService: Fonnte menolak permintaan.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('WhatsAppService: gagal kirim pesan.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Fonnte minta format internasional tanpa '+' (mis. 628123456789).
     * Nomor lokal yang diketik user biasanya diawali '0' (mis.
     * 08123456789) - dikonversi ke '62' di sini supaya pemanggil
     * (Job/Controller) tidak perlu mikirin format, cukup kirim apa adanya
     * dari input form.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
