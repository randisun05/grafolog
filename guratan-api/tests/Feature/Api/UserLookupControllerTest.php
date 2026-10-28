<?php

namespace Tests\Feature\Api;

use App\Mail\ClientInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_grafologs(): void
    {
        $this->getJson('/api/grafologs')->assertUnauthorized();
    }

    public function test_client_cannot_list_grafologs(): void
    {
        $client = User::factory()->create(['role' => 'user']);

        $this->actingAs($client, 'sanctum')->getJson('/api/grafologs')->assertForbidden();
    }

    public function test_hr_can_list_grafologs(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        User::factory()->count(2)->create(['role' => 'grafolog']);
        User::factory()->create(['role' => 'user']); // tidak boleh ikut kehitung

        $response = $this->actingAs($hr, 'sanctum')->getJson('/api/grafologs');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_administrator_can_list_grafologs(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/grafologs')->assertOk()->assertJsonCount(1);
    }

    public function test_grafolog_lists_only_own_referred_clients(): void
    {
        $mine = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $other = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $ownClient = User::factory()->create(['role' => 'user', 'referred_by' => $mine->id]);
        User::factory()->create(['role' => 'user', 'referred_by' => $other->id]);
        User::factory()->create(['role' => 'user']);

        $this->actingAs($mine, 'sanctum')->getJson('/api/clients')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $ownClient->id)->assertJsonPath('0.orders_count', 0);

        $this->actingAs($ownClient, 'sanctum')->getJson('/api/clients')->assertForbidden();
    }

    public function test_guest_cannot_create_client(): void
    {
        $this->postJson('/api/clients', ['consent_confirmed' => true, 'name' => 'Klien Baru', 'email' => 'klien@example.com'])
            ->assertUnauthorized();
    }

    public function test_non_grafolog_cannot_create_client(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')
            ->postJson('/api/clients', ['consent_confirmed' => true, 'name' => 'Klien Baru', 'email' => 'klien@example.com'])
            ->assertForbidden();
    }

    public function test_grafolog_cannot_set_client_password_directly(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->postJson('/api/clients', [
            'consent_confirmed' => true, 'name' => 'Klien', 'email' => 'walkin@example.com', 'password' => 'Password123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_grafolog_registers_client_by_invitation_and_client_can_activate(): void
    {
        Mail::fake();
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson('/api/clients', [
            'consent_confirmed' => true, 'name' => 'Klien Walk-in', 'email' => 'walkin@example.com', 'phone' => '0812',
        ]);

        $response->assertCreated()->assertJsonPath('invitation_sent', true)->assertJsonMissingPath('generated_password');
        $this->assertDatabaseHas('users', [
            'email' => 'walkin@example.com', 'role' => 'user', 'acquisition_source' => 'grafolog', 'referred_by' => $grafolog->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'daftarkan_klien', 'actor_user_id' => $grafolog->id]);
        Mail::assertQueued(ClientInvitationMail::class, fn ($m) => $m->hasTo('walkin@example.com'));

        // Klien mengaktifkan akun lewat tautan undangan (broker 'invitations').
        parse_str(parse_url($response->json('invitation_url'), PHP_URL_QUERY), $q);
        $this->postJson('/api/auth/reset-password', [
            'token' => $q['token'], 'email' => 'walkin@example.com', 'undangan' => true,
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
        ])->assertOk();

        $client = User::where('email', 'walkin@example.com')->first();
        $this->assertTrue(Hash::check('Rahasia123', $client->password));
        $this->assertNotNull($client->email_verified_at);
    }

    public function test_invitation_token_is_not_accepted_as_regular_reset(): void
    {
        Mail::fake();
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $url = $this->actingAs($grafolog, 'sanctum')->postJson('/api/clients', [
            'consent_confirmed' => true, 'name' => 'Klien', 'email' => 'k@example.com',
        ])->json('invitation_url');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        $this->postJson('/api/auth/reset-password', [
            'token' => $q['token'], 'email' => 'k@example.com',
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
        ])->assertStatus(422);
    }

    public function test_only_referring_grafolog_can_resend_invitation(): void
    {
        Mail::fake();
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $other = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user', 'referred_by' => $grafolog->id, 'acquisition_source' => 'grafolog']);

        $this->actingAs($other, 'sanctum')->postJson("/api/clients/{$client->id}/invite")->assertForbidden();
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/clients/{$client->id}/invite")->assertOk();
        Mail::assertQueued(ClientInvitationMail::class, 1);
    }

    public function test_duplicate_email_rejected(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        User::factory()->create(['email' => 'sudahada@example.com']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/clients', ['consent_confirmed' => true, 'name' => 'Duplikat', 'email' => 'sudahada@example.com'])
            ->assertUnprocessable();
    }

    public function test_create_client_requires_consent_confirmation(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/clients', ['name' => 'Tanpa Consent', 'email' => 'noconsent@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent_confirmed');
    }
}
