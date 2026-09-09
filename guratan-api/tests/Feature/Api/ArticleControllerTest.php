<?php

namespace Tests\Feature\Api;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_only_published_articles(): void
    {
        Article::create(['title' => 'Terbit', 'slug' => 'terbit', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()]);
        Article::create(['title' => 'Draft', 'slug' => 'draft', 'body' => '<p>x</p>', 'status' => 'draft']);

        $response = $this->getJson('/api/articles');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'terbit');
    }

    public function test_guest_can_view_published_article_by_slug(): void
    {
        Article::create(['title' => 'Terbit', 'slug' => 'terbit', 'body' => '<p>Isi</p>', 'status' => 'published', 'published_at' => now()]);

        $this->getJson('/api/articles/terbit')->assertOk()->assertJsonPath('title', 'Terbit');
    }

    public function test_draft_article_returns_404_for_anyone(): void
    {
        Article::create(['title' => 'Draft', 'slug' => 'draft', 'body' => '<p>x</p>', 'status' => 'draft']);

        $this->getJson('/api/articles/draft')->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/articles/tidak-ada')->assertNotFound();
    }
}
