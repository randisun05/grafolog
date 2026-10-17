<?php

namespace Tests\Feature\Api;

use App\Mail\ConsultationScheduledMail;
use App\Models\HandwritingSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConsultationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sample(string $tier = 'master'): array
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $client->id, 'created_by' => $grafolog->id, 'tier' => $tier, 'status' => 'pending',
        ]);

        return [$sample, $grafolog, $client];
    }

    private function payload(array $overrides = []): array
    {
        return [
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'notes' => 'Siapkan pertanyaan.',
            ...$overrides,
        ];
    }

    public function test_grafolog_schedules_master_consultation_and_client_is_emailed(): void
    {
        Mail::fake();
        [$sample, $grafolog, $client] = $this->sample();

        $this->actingAs($grafolog, 'sanctum')
            ->putJson("/api/samples/{$sample->id}/consultation", $this->payload())
            ->assertCreated()->assertJsonPath('status', 'dijadwalkan');

        Mail::assertQueued(ConsultationScheduledMail::class, fn ($m) => $m->hasTo($client->email));
        $this->actingAs($client, 'sanctum')->getJson("/api/samples/{$sample->id}")
            ->assertJsonPath('consultation.meeting_url', 'https://meet.google.com/abc-defg-hij');
    }

    public function test_marking_done_does_not_email_again(): void
    {
        Mail::fake();
        [$sample, $grafolog] = $this->sample();
        $payload = $this->payload();
        $this->actingAs($grafolog, 'sanctum')->putJson("/api/samples/{$sample->id}/consultation", $payload);

        $this->actingAs($grafolog, 'sanctum')
            ->putJson("/api/samples/{$sample->id}/consultation", [...$payload, 'status' => 'selesai'])->assertOk();

        Mail::assertQueued(ConsultationScheduledMail::class, 1);
    }

    public function test_comprehensive_tier_rejected(): void
    {
        [$sample, $grafolog] = $this->sample('comprehensive');

        $this->actingAs($grafolog, 'sanctum')
            ->putJson("/api/samples/{$sample->id}/consultation", $this->payload())->assertUnprocessable();
    }

    public function test_other_grafolog_and_client_cannot_schedule(): void
    {
        [$sample, , $client] = $this->sample();
        $other = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($other, 'sanctum')->putJson("/api/samples/{$sample->id}/consultation", $this->payload())->assertForbidden();
        $this->actingAs($client, 'sanctum')->putJson("/api/samples/{$sample->id}/consultation", $this->payload())->assertForbidden();
    }

    public function test_non_https_link_and_past_date_rejected(): void
    {
        [$sample, $grafolog] = $this->sample();

        $this->actingAs($grafolog, 'sanctum')
            ->putJson("/api/samples/{$sample->id}/consultation", $this->payload([
                'meeting_url' => 'http://insecure.example.com', 'scheduled_at' => now()->subDay()->toIso8601String(),
            ]))
            ->assertUnprocessable()->assertJsonValidationErrors(['meeting_url', 'scheduled_at']);
    }
}
