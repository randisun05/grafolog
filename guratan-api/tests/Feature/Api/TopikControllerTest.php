<?php

namespace Tests\Feature\Api;

use App\Models\Topik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Api\TopikController - bacaan ringan {id, nama} untuk staf, dipakai
 * filter segmen di ReportView.vue (B2B Fase 2). Beda dari
 * Api\Admin\TopikController (CRUD, role:administrator) - ini cuma daftar
 * untuk dropdown, staff-only (bukan admin-only, HR bukan administrator).
 */
class TopikControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_topik(): void
    {
        $this->getJson('/api/topik')->assertUnauthorized();
    }

    public function test_client_cannot_list_topik(): void
    {
        $client = User::factory()->create(['role' => 'user']);

        $this->actingAs($client, 'sanctum')->getJson('/api/topik')->assertForbidden();
    }

    public function test_hr_can_list_topik(): void
    {
        Topik::create(['nama' => 'Karier']);
        Topik::create(['nama' => 'Percintaan']);
        $hr = User::factory()->create(['role' => 'hr']);

        $response = $this->actingAs($hr, 'sanctum')->getJson('/api/topik');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_grafolog_can_list_topik(): void
    {
        Topik::create(['nama' => 'Karier']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/topik')->assertOk()->assertJsonCount(1);
    }
}
