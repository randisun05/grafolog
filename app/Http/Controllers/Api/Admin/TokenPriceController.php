<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTokenPriceRequest;
use App\Models\AuditLog;
use App\Models\TokenPrice;
use Illuminate\Http\JsonResponse;

/**
 * Gated 'role:administrator'. Sama pola dengan Admin\PricingController -
 * setiap perubahan harga token dicatat di audit_logs.
 */
class TokenPriceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            TokenPrice::with('updater:id,name')->latest()->paginate(20)
        );
    }

    public function update(UpdateTokenPriceRequest $request): JsonResponse
    {
        $price = TokenPrice::setPrice($request->validated('price_per_token'), $request->user());

        AuditLog::record('ubah_harga_token', TokenPrice::class, $price->id, $request->user()->id, $request->ip());

        return response()->json($price, 201);
    }
}
