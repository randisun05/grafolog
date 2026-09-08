<?php

namespace Tests\Unit;

use App\Mail\ResetPasswordMail;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function dispatcher(): NotificationDispatcher
    {
        return new NotificationDispatcher(new WhatsAppService);
    }

    public function test_send_email_records_sent_log_on_success(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->dispatcher()->sendEmail('reset_password', $user, $user->email, new ResetPasswordMail('http://x'));

        Mail::assertSent(ResetPasswordMail::class);
        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'email', 'type' => 'reset_password', 'user_id' => $user->id,
            'recipient' => $user->email, 'status' => 'sent', 'error_message' => null,
        ]);
    }

    public function test_send_email_records_failed_log_and_rethrows_on_exception(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);

        try {
            $this->dispatcher()->sendEmail('reset_password', $user, $user->email, new ResetPasswordMail('http://x'));
        } finally {
            $this->assertDatabaseHas('notification_logs', [
                'channel' => 'email', 'type' => 'reset_password', 'user_id' => $user->id,
                'recipient' => $user->email, 'status' => 'failed', 'error_message' => 'SMTP down',
            ]);
        }
    }

    public function test_send_whatsapp_records_skipped_when_phone_missing(): void
    {
        $user = User::factory()->create(['phone' => null]);

        $this->dispatcher()->sendWhatsApp('reset_password', $user, null, 'Halo');

        $log = NotificationLog::where('channel', 'whatsapp')->first();
        $this->assertSame('skipped', $log->status);
        $this->assertSame('-', $log->recipient);
    }

    public function test_send_whatsapp_records_skipped_when_fonnte_not_configured(): void
    {
        config(['services.fonnte.token' => null]);
        $user = User::factory()->create(['phone' => '08123456789']);

        $this->dispatcher()->sendWhatsApp('reset_password', $user, '08123456789', 'Halo');

        $log = NotificationLog::where('channel', 'whatsapp')->first();
        $this->assertSame('skipped', $log->status);
        $this->assertSame('08123456789', $log->recipient);
    }

    public function test_send_whatsapp_records_sent_when_fonnte_accepts(): void
    {
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
        $user = User::factory()->create(['phone' => '08123456789']);

        $this->dispatcher()->sendWhatsApp('reset_password', $user, '08123456789', 'Halo');

        $this->assertSame('sent', NotificationLog::where('channel', 'whatsapp')->first()->status);
    }

    public function test_send_whatsapp_records_failed_when_fonnte_rejects_and_does_not_throw(): void
    {
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false], 401)]);
        $user = User::factory()->create(['phone' => '08123456789']);

        $this->dispatcher()->sendWhatsApp('reset_password', $user, '08123456789', 'Halo');

        $this->assertSame('failed', NotificationLog::where('channel', 'whatsapp')->first()->status);
    }
}
