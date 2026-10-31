<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GameScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator'. Murni moderasi - lihat entri leaderboard
 * publik (bisa filter per game_type) dan hapus baris nama tidak pantas.
 * Tidak ada store/update - skor cuma pernah ditulis lewat
 * Api\Games\ScoreController::store() (publik, tanpa login).
 */
class GameScoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $scores = GameScore::query()
            ->when($request->filled('game_type'), fn ($q) => $q->where('game_type', $request->input('game_type')))
            ->latest()
            ->paginate(25);

        return response()->json($scores);
    }

    public function destroy(Request $request, GameScore $gameScore): JsonResponse
    {
        $id = $gameScore->id;
        $gameScore->delete();

        AuditLog::record('hapus_skor_game', GameScore::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Skor dihapus.']);
    }
}
