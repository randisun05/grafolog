<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTokenCostRequest;
use App\Models\AuditLog;
use App\Models\TokenCost;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Http\JsonResponse;

/**
 * Gated 'role:administrator'. Sama pola dengan Admin\PricingController -
 * setiap perubahan biaya token per tier dicatat di audit_logs. tokens_required
 * boleh 0 (tier itu sengaja tidak menggunakan token sama sekali).
 */
class TokenCostController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            TokenCost::with('updater:id,name')->latest()->paginate(20)
        );
    }

    public function update(UpdateTokenCostRequest $request, string $tier): JsonResponse
    {
        // Comprehensive tidak dijual lagi; `validasi_supervisor` = biaya validasi laporan (Fase D).
        abort_unless(in_array($tier, ['master', ReportValidationService::COST_KEY], true), 404);

        $cost = TokenCost::setTokensFor($tier, $request->validated('tokens_required'), $request->user());

        AuditLog::record('ubah_biaya_token', TokenCost::class, $cost->id, $request->user()->id, $request->ip());

        return response()->json($cost, 201);
    }
}
