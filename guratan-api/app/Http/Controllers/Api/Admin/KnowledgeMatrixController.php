<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Knowledge\KnowledgeMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator'. Tab "Matriks" di Admin Knowledge Base -
 * murni baca, edit tetap lewat tab Indikator / Aturan Operator.
 */
class KnowledgeMatrixController extends Controller
{
    public function __construct(private KnowledgeMatrixService $matrix) {}

    public function struktur(): JsonResponse
    {
        return response()->json($this->matrix->struktur());
    }

    public function empiris(Request $request): JsonResponse
    {
        $data = $request->validate(['min_n' => ['sometimes', 'integer', 'min:3', 'max:10000']]);

        return response()->json($this->matrix->empiris((int) ($data['min_n'] ?? 30)));
    }
}
