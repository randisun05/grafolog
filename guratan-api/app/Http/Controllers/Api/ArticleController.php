<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

/** Artikel informasi publik (Fase E). */
class ArticleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Article::published()->latest('published_at')
                ->get(['id', 'judul', 'slug', 'ringkasan', 'kategori', 'published_at'])
        );
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json(Article::published()->where('slug', $slug)->firstOrFail());
    }
}
