<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Leaderboard publik untuk 3 mini game - lihat guratan-api/CLAUDE.md
 * "Konten publik — Fase 3". `nama` bebas diisi pemain, tidak terikat
 * akun (bisa dimain tanpa login).
 */
class GameScore extends Model
{
    protected $fillable = ['game_type', 'nama', 'skor', 'durasi_detik'];
}
