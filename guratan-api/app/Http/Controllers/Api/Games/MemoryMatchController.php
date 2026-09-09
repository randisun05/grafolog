<?php

namespace App\Http\Controllers\Api\Games;

use App\Http\Controllers\Controller;
use App\Models\GlossaryTerm;
use Illuminate\Http\JsonResponse;

/**
 * Game "Memory Match Istilah" - lihat guratan-api/CLAUDE.md "Konten
 * publik — Fase 3". Publik, tanpa login. Balas daftar flat
 * istilah+definisi - frontend yang membangun 16 kartu diacak dari 8
 * pasang ini.
 */
class MemoryMatchController extends Controller
{
    public function terms(): JsonResponse
    {
        $terms = GlossaryTerm::where('is_active', true)
            ->inRandomOrder()
            ->limit(8)
            ->get(['id', 'istilah', 'definisi']);

        return response()->json($terms);
    }
}
