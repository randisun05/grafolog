<?php

namespace Tests\Feature\Api\Admin;

use App\Models\GameScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameScoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_scores(): void
    {
        $this->getJson('/api/admin/game-scores')->assertUnauthorized();
    }

    public function test_non_admin_cannot_view_scores(): void
    {
        $user = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/game-scores')->assertForbidden();
    }

    public function test_admin_can_list_and_filter_by_game_type(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        GameScore::create(['game_type' => 'trivia', 'nama' => 'Budi', 'skor' => 8]);
        GameScore::create(['game_type' => 'memory_match', 'nama' => 'Siti', 'skor' => 500]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/game-scores?game_type=trivia');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama', 'Budi');
    }

    public function test_admin_can_delete_inappropriate_score(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $score = GameScore::create(['game_type' => 'trivia', 'nama' => 'Nama Tidak Pantas', 'skor' => 10]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/game-scores/{$score->id}")
            ->assertOk();

        $this->assertDatabaseMissing('game_scores', ['id' => $score->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_skor_game']);
    }
}
