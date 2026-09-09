<?php

namespace Tests\Feature\Api\Games;

use App\Models\GlossaryTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_only_returns_active_ones(): void
    {
        GlossaryTerm::create(['istilah' => 'Aktif', 'definisi' => 'x', 'is_active' => true]);
        GlossaryTerm::create(['istilah' => 'Nonaktif', 'definisi' => 'y', 'is_active' => false]);

        $response = $this->getJson('/api/games/memory-match/terms');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.istilah', 'Aktif');
    }

    public function test_terms_caps_at_8_pairs(): void
    {
        foreach (range(1, 12) as $i) {
            GlossaryTerm::create(['istilah' => "Istilah $i", 'definisi' => 'x']);
        }

        $response = $this->getJson('/api/games/memory-match/terms');

        $response->assertOk()->assertJsonCount(8);
    }
}
