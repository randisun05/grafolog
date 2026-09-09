<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Models\Article;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes (routes/api.php). Beda dari
 * Api\ArticleController (publik, cuma status=published) - controller ini
 * menampilkan SEMUA status termasuk draft, untuk keperluan admin.
 */
class ArticleController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = Article::query()
            ->latest()
            ->paginate(20);

        return response()->json($articles);
    }

    /**
     * Slug digenerate SEKALI dari title saat dibuat (Article::
     * generateUniqueSlug()) - tidak pernah berubah lagi di update, supaya
     * URL publik /artikel/{slug} stabil (tidak mematahkan tautan yang
     * sudah dibagikan). `published_at` diisi cuma pada transisi PERTAMA
     * ke status published.
     */
    public function store(StoreArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = Article::generateUniqueSlug($data['title']);
        $data['created_by'] = $request->user()->id;

        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = now();
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('article-covers', 'public');
        }

        $article = Article::create($data);

        AuditLog::record('buat_artikel', Article::class, $article->id, $request->user()->id, $request->ip());

        return response()->json($article, 201);
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $data = $request->validated();

        if (($data['status'] ?? null) === 'published' && $article->published_at === null) {
            $data['published_at'] = now();
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('article-covers', 'public');
        }

        $article->update($data);

        AuditLog::record('ubah_artikel', Article::class, $article->id, $request->user()->id, $request->ip());

        return response()->json($article);
    }

    public function destroy(Request $request, Article $article): JsonResponse
    {
        $articleId = $article->id;
        $article->delete();

        AuditLog::record('hapus_artikel', Article::class, $articleId, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Artikel dihapus.']);
    }
}
