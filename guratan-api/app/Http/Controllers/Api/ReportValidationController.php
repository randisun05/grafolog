<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalityReport;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grafolog penanganan memutuskan nasib laporan draf (Fase D, 2026-10-17): terbit mandiri atau
 * minta validasi supervisor berbayar token. Lihat ReportValidationService.
 */
class ReportValidationController extends Controller
{
    public function __construct(private ReportValidationService $validation) {}

    public function fee(Request $request): JsonResponse
    {
        return response()->json([
            'tokens' => $this->validation->fee(),
            'balance' => $request->user()->token_balance,
        ]);
    }

    public function publish(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeScorer($request, $report);

        return response()->json($this->validation->publish($report, $request->user(), $request->ip())->fresh());
    }

    public function request(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeScorer($request, $report);

        return response()->json($this->validation->requestValidation($report, $request->user(), $request->ip())->fresh());
    }

    private function authorizeScorer(Request $request, PersonalityReport $report): void
    {
        $user = $request->user();
        abort_unless($user->isGrafolog() && $report->sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani laporan ini.');
    }
}
