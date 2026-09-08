<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keputusan sadar 2026-09-08 (lihat "Open security findings" di
 * guratan-api/CLAUDE.md) - sebelumnya token Sanctum tidak pernah
 * kedaluwarsa sama sekali, token yang bocor tetap valid selamanya sampai
 * dicabut manual. `config/sanctum.php`'s `expiration` sekarang 1440 menit
 * (24 jam), dihitung dari `created_at` token (waktu login), BUKAN sliding
 * window last-used.
 */
class SanctumTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_older_than_24_hours_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        $token->accessToken->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_token_within_24_hours_is_still_valid(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        $token->accessToken->forceFill(['created_at' => now()->subHours(23)])->save();

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_freshly_issued_token_is_valid(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/auth/me')
            ->assertOk();
    }
}
