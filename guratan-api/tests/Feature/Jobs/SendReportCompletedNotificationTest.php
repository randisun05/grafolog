<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendReportCompletedNotification;
use App\Mail\ReportCompletedMail;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendReportCompletedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function completedReport(User $owner): PersonalityReport
    {
        $sample = HandwritingSample::create([
            'user_id' => $owner->id,
            'created_by' => $owner->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
        ]);

        return PersonalityReport::create([
            'sample_id' => $sample->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
            'generated_at' => now(),
            'data' => [],
        ]);
    }

    public function test_sends_email_regardless_of_whatsapp_configuration(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => 'user']);
        $report = $this->completedReport($owner);

        (new SendReportCompletedNotification($report))->handle(app(WhatsAppService::class));

        Mail::assertSent(ReportCompletedMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_also_sends_whatsapp_when_phone_present_and_fonnte_configured(): void
    {
        Mail::fake();
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);

        $owner = User::factory()->create(['role' => 'user', 'phone' => '08123456789']);
        $report = $this->completedReport($owner);

        (new SendReportCompletedNotification($report))->handle(app(WhatsAppService::class));

        Http::assertSent(fn ($request) => $request['target'] === '628123456789'
            && str_contains($request['message'], $owner->name));
    }

    public function test_does_not_attempt_whatsapp_when_owner_has_no_phone(): void
    {
        Mail::fake();
        config(['services.fonnte.token' => 'fake-token']);
        Http::fake();

        $owner = User::factory()->create(['role' => 'user', 'phone' => null]);
        $report = $this->completedReport($owner);

        (new SendReportCompletedNotification($report))->handle(app(WhatsAppService::class));

        Http::assertNothingSent();
    }
}
