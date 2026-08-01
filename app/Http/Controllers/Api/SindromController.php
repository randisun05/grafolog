<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sindrom;
use Illuminate\Http\JsonResponse;

class SindromController extends Controller
{
    /**
     * Struktur sindrom + aspek (tanpa narasi) untuk merender form skoring
     * 40 aspek di portal grafolog.
     */
    public function index(): JsonResponse
    {
        $sindrom = Sindrom::with(['aspek' => function ($q) {
            $q->select('id', 'kode', 'nama', 'sindrom_id')->orderBy('kode');
        }])
            ->orderBy('id')
            ->get(['id', 'kode_romawi', 'nama', 'polaritas_inferred']);

        return response()->json($sindrom);
    }
}
