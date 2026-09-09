<?php

namespace Tests\Feature\Api\Games;

use App\Models\GameScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_score_without_login(): void
    {
        $response = $this->postJson('/api/games/scores', [
            'game_type' => 'trivia', 'nama' => 'Budi', 'skor' => 8,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('game_scores', ['game_type' => 'trivia', 'nama' => 'Budi', 'skor' => 8]);
    }

    public function test_rejects_invalid_game_type(): void
    {
        $this->postJson('/api/games/scores', ['game_type' => 'not-a-real-game', 'nama' => 'Budi', 'skor' => 8])
            ->assertUnprocessable();
    }

    public function test_leaderboard_orders_by_skor_desc_then_duration_asc(): void
    {
        GameScore::create(['game_type' => 'memory_match', 'nama' => 'Rendah', 'skor' => 100]);
        GameScore::create(['game_type' => 'memory_match', 'nama' => 'Lambat', 'skor' => 500, 'durasi_detik' => 90]);
        GameScore::create(['game_type' => 'memory_match', 'nama' => 'Cepat', 'skor' => 500, 'durasi_detik' => 30]);

        $response = $this->getJson('/api/games/memory_match/leaderboard');

        $response->assertOk();
        $names = collect($response->json())->pluck('nama')->all();
        $this->assertSame(['Cepat', 'Lambat', 'Rendah'], $names);
    }

    public function test_leaderboard_only_shows_matching_game_type(): void
    {
        GameScore::create(['game_type' => 'trivia', 'nama' => 'A', 'skor' => 10]);
        GameScore::create(['game_type' => 'memory_match', 'nama' => 'B', 'skor' => 10]);

        $response = $this->getJson('/api/games/trivia/leaderboard');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.nama', 'A');
    }

    /**
     * Throttle KHUSUS /scores (10,1) tumpuk di atas throttle:60,1 grup
     * games - pola sama narasi-terpadu/generate, lihat CLAUDE.md "Guard
     * biaya AI" untuk precedent throttle tumpuk yang sama.
     */
    public function test_score_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/games/scores', ['game_type' => 'trivia', 'nama' => 'Budi', 'skor' => 1]);
        }

        $response = $this->postJson('/api/games/scores', ['game_type' => 'trivia', 'nama' => 'Budi', 'skor' => 1]);

        $response->assertStatus(429);
    }
}
