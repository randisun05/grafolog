<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** WhatsApp (Fonnte) berjalan paralel dengan email di Notifier::send(). */
class WhatsAppNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.fonnte.token' => 'fonnte-test']);
    }

    public function test_send_queues_whatsapp_alongside_email_when_user_has_phone(): void
    {
        Bus::fake();
        $user = User::factory()->create(['phone' => '08123456789']);

        app(Notifier::class)->send($user, 'pembayaran', 'Pembayaran diterima', 'Pesanan #1 sudah dibayar.');

        Bus::assertDispatched(SendWhatsAppNotification::class, fn ($j) => $j->userId === $user->id && $j->phone === '08123456789'
            && str_contains($j->message, 'Pembayaran diterima'));
    }

    public function test_no_whatsapp_without_phone_or_token_or_when_mail_disabled(): void
    {
        Bus::fake();
        $noPhone = User::factory()->create(['phone' => null]);
        $withPhone = User::factory()->create(['phone' => '08123456789']);
        $notifier = app(Notifier::class);

        $notifier->send($noPhone, 'pembayaran', 'T', 'B');
        $notifier->send($withPhone, 'pembayaran', 'T', 'B', mail: false);
        config(['services.fonnte.token' => null]);
        $notifier->send($withPhone, 'pembayaran', 'T', 'B');

        Bus::assertNotDispatched(SendWhatsAppNotification::class);
    }

    public function test_job_logs_sent_failed_and_skipped(): void
    {
        $user = User::factory()->create();
        Http::fake(['api.fonnte.com/*' => Http::sequence()->push(['status' => true], 200)->push(['status' => false], 500)]);

        SendWhatsAppNotification::dispatchSync($user->id, 'pembayaran', '08123456789', 'halo');
        SendWhatsAppNotification::dispatchSync($user->id, 'pembayaran', '08123456789', 'halo');
        SendWhatsAppNotification::dispatchSync($user->id, 'pembayaran', null, 'halo');
        config(['services.fonnte.token' => null]);
        SendWhatsAppNotification::dispatchSync($user->id, 'pembayaran', '08123456789', 'halo');

        $this->assertSame(
            ['sent', 'failed', 'skipped', 'skipped'],
            NotificationLog::orderBy('id')->pluck('status')->all(),
        );
    }
}
