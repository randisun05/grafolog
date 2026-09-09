<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Cara Membaca Middle Zone pada Tulisan Tangan',
            'excerpt' => 'Pengantar singkat.',
            'body' => '<p>Isi artikel lengkap.</p>',
            'status' => 'draft',
        ], $overrides);
    }

    public function test_guest_cannot_manage_articles(): void
    {
        $this->postJson('/api/admin/articles', $this->validPayload())->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_articles(): void
    {
        $user = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/articles', $this->validPayload())
            ->assertForbidden();
    }

    public function test_admin_can_create_article_with_cover_and_slug_is_generated(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/articles', $this->validPayload([
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]));

        $response->assertCreated()
            ->assertJsonPath('slug', 'cara-membaca-middle-zone-pada-tulisan-tangan')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('published_at', null);

        $this->assertDatabaseHas('articles', ['title' => $this->validPayload()['title']]);
        Storage::disk('public')->assertExists(Article::first()->cover_image_path);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_artikel']);
    }

    public function test_duplicate_title_gets_suffixed_slug(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/articles', $this->validPayload())->assertCreated();
        $second = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/articles', $this->validPayload());

        $second->assertCreated()->assertJsonPath('slug', 'cara-membaca-middle-zone-pada-tulisan-tangan-2');
    }

    public function test_publishing_sets_published_at_once_and_does_not_move_on_later_update(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $article = Article::create([
            'title' => 'Draft Lama', 'slug' => 'draft-lama', 'body' => '<p>x</p>', 'status' => 'draft',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/articles/{$article->id}", ['status' => 'published'])
            ->assertOk();
        $firstPublishedAt = $article->fresh()->published_at;
        $this->assertNotNull($firstPublishedAt);

        $this->travel(1)->days();
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/articles/{$article->id}", ['title' => 'Judul Diperbarui'])
            ->assertOk();

        $this->assertTrue($firstPublishedAt->eq($article->fresh()->published_at));
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_artikel']);
    }

    public function test_admin_index_includes_draft_articles(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Article::create(['title' => 'Draft', 'slug' => 'draft', 'body' => '<p>x</p>', 'status' => 'draft']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_delete_article(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $article = Article::create(['title' => 'Hapus Saya', 'slug' => 'hapus-saya', 'body' => '<p>x</p>', 'status' => 'draft']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/articles/{$article->id}")
            ->assertOk();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_artikel']);
    }
}
