<?php

namespace Tests\Unit;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    public function test_send_skips_and_returns_false_when_token_not_configured(): void
    {
        config(['services.fonnte.token' => null]);
        Http::fake();

        $result = (new WhatsAppService)->send('08123456789', 'Halo');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_send_skips_and_returns_false_when_phone_is_null(): void
    {
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake();

        $result = (new WhatsAppService)->send(null, 'Halo');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_send_posts_to_fonnte_with_normalized_phone_and_returns_true_on_success(): void
    {
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'], 200),
        ]);

        $result = (new WhatsAppService)->send('08123456789', 'Halo, laporan Anda sudah siap.');

        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->header('Authorization') === ['fake-token']
                && $request['target'] === '628123456789'
                && $request['message'] === 'Halo, laporan Anda sudah siap.';
        });
    }

    public function test_send_does_not_double_prefix_a_number_already_in_62_format(): void
    {
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);

        (new WhatsAppService)->send('628123456789', 'Halo');

        Http::assertSent(fn ($request) => $request['target'] === '628123456789');
    }

    public function test_send_returns_false_when_fonnte_rejects_the_request(): void
    {
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'invalid token'], 401)]);

        $result = (new WhatsAppService)->send('08123456789', 'Halo');

        $this->assertFalse($result);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.fonnte.com/send');
    }

    public function test_is_configured_reflects_whether_token_is_set(): void
    {
        config(['services.fonnte.token' => null]);
        $this->assertFalse((new WhatsAppService)->isConfigured());

        config(['services.fonnte.token' => 'fake-token']);
        $this->assertTrue((new WhatsAppService)->isConfigured());
    }
}
