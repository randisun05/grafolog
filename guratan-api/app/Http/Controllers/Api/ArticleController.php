<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

/**
 * Publik (tanpa login), sama pola PricingController/ContentController -
 * cuma artikel status=published, draft tidak pernah terlihat siapa pun
 * lewat jalur ini (termasuk lewat show by slug).
 */
class ArticleController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = Article::query()
            ->where('status', 'published')
            ->latest('published_at')
            ->paginate(9, ['id', 'title', 'slug', 'excerpt', 'cover_image_path', 'published_at']);

        return response()->json($articles);
    }

    public function show(string $slug): JsonResponse
    {
        $article = Article::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($article);
    }
}
