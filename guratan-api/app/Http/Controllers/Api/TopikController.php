<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bacaan ringan {id, nama} untuk staf (grafolog/admin/hr) - dipakai
 * ReportView.vue's filter segmen Topik (B2B Fase 2, lihat ROADMAP.md
 * "Kesiapan Publikasi"). Beda dari Api\Admin\TopikController (CRUD penuh,
 * role:administrator) - HR bukan administrator, tidak bisa memukul
 * endpoint admin itu, tapi tetap perlu tahu daftar Topik yang ada untuk
 * memilih filter. Staff-only sama seperti ReportController::segmen() -
 * klien tidak pernah melihat breakdown internal apa pun, termasuk daftar
 * kategorinya.
 */
class TopikController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'user', 403);

        return response()->json(Topik::orderBy('nama')->get(['id', 'nama']));
    }
}
