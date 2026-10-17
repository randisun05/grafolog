<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Gated 'role:administrator'. CRUD artikel; terbit = is_published + published_at. */
class ArticleAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Article::latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['judul']);
        $article = Article::create($this->publishing($data) + ['created_by' => $request->user()->id]);
        AuditLog::record('buat_artikel', Article::class, $article->id, $request->user()->id, $request->ip());

        return response()->json($article, 201);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $article->update($this->publishing($this->validated($request, $article), $article));
        AuditLog::record('ubah_artikel', Article::class, $article->id, $request->user()->id, $request->ip());

        return response()->json($article);
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        $req = $article ? 'sometimes' : 'required';

        return $request->validate([
            'judul' => [$req, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'alpha_dash', 'max:255', Rule::unique('articles', 'slug')->ignore($article?->id)],
            'ringkasan' => ['sometimes', 'nullable', 'string', 'max:500'],
            'isi' => [$req, 'string', 'max:50000'],
            'kategori' => ['sometimes', 'string', 'max:50'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
    }

    private function publishing(array $data, ?Article $article = null): array
    {
        if (($data['is_published'] ?? false) && ! $article?->published_at) {
            $data['published_at'] = now();
        }

        return array_filter($data, fn ($v, $k) => ! ($k === 'slug' && $v === null), ARRAY_FILTER_USE_BOTH);
    }

    private function uniqueSlug(string $judul): string
    {
        $base = Str::slug($judul) ?: 'artikel';
        $slug = $base;
        for ($i = 2; Article::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
