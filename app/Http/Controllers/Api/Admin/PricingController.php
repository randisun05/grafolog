<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePricingRequest;
use App\Models\AuditLog;
use App\Models\PricingPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. Harga adalah data bisnis
 * sensitif - setiap perubahan tercatat di audit_logs, sama seperti akses
 * laporan psikologis dicatat lewat LogReportAccess.
 */
class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            PricingPlan::with('updater:id,name')->latest()->paginate(20)
        );
    }

    public function update(UpdatePricingRequest $request, string $tier): JsonResponse
    {
        abort_unless(in_array($tier, ['comprehensive', 'master'], true), 404);

        $plan = PricingPlan::setPriceFor($tier, $request->validated('price'), $request->user());

        AuditLog::record('ubah_harga', PricingPlan::class, $plan->id, $request->user()->id, $request->ip());

        return response()->json($plan, 201);
    }
}
