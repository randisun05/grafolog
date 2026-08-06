<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
