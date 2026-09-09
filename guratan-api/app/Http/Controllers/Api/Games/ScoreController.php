<?php

namespace App\Http\Controllers\Api\Games;

use App\Http\Controllers\Controller;
use App\Models\GameScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leaderboard publik bersama untuk ketiga mini game - lihat
 * guratan-api/CLAUDE.md "Konten publik — Fase 3". Publik, tanpa login -
 * `nama` ditulis pemain sendiri. store() dijaga throttle:10,1 TUMPUK di
 * atas throttle:60,1 grup (pola sama narasi-terpadu/generate).
 */
class ScoreController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_type' => ['required', 'string', 'in:tebak_kepribadian,trivia,memory_match'],
            'nama' => ['required', 'string', 'max:30'],
            'skor' => ['required', 'integer', 'min:0'],
            'durasi_detik' => ['nullable', 'integer', 'min:0'],
        ]);

        $score = GameScore::create($data);

        return response()->json($score, 201);
    }

    public function leaderboard(string $gameType): JsonResponse
    {
        $scores = GameScore::where('game_type', $gameType)
            ->orderByDesc('skor')
            ->orderByRaw('durasi_detik IS NULL, durasi_detik ASC')
            ->orderBy('created_at')
            ->limit(20)
            ->get(['nama', 'skor', 'durasi_detik', 'created_at']);

        return response()->json($scores);
    }
}
