<?php

namespace Tests\Feature\Api\Admin;

use App\Models\TriviaQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriviaQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'pertanyaan' => 'Berapa jumlah Sindrom dalam sistem Guratan?',
            'pilihan' => ['8', '5', '12', '3'],
            'jawaban_benar_index' => 0,
            'penjelasan' => 'Ada 8 Sindrom.',
        ], $overrides);
    }

    public function test_guest_cannot_manage_trivia(): void
    {
        $this->postJson('/api/admin/trivia-questions', $this->validPayload())->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_trivia(): void
    {
        $user = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/trivia-questions', $this->validPayload())
            ->assertForbidden();
    }

    public function test_admin_can_create_trivia_question(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/trivia-questions', $this->validPayload());

        $response->assertCreated()->assertJsonPath('jawaban_benar_index', 0);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_trivia']);
    }

    public function test_pilihan_must_have_exactly_4_items(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/trivia-questions', $this->validPayload(['pilihan' => ['a', 'b', 'c']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pilihan');
    }

    public function test_admin_can_update_and_delete(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $question = TriviaQuestion::create($this->validPayload());

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/trivia-questions/{$question->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_trivia']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/trivia-questions/{$question->id}")
            ->assertOk();
        $this->assertDatabaseMissing('trivia_questions', ['id' => $question->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_trivia']);
    }

    public function test_index_includes_inactive_questions(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        TriviaQuestion::create($this->validPayload(['is_active' => false]));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/trivia-questions')
            ->assertOk()
            ->assertJsonCount(1);
    }
}
