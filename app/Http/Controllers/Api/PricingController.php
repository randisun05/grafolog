<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use Illuminate\Http\JsonResponse;

/**
 * Public read - harga ditampilkan sebelum login (halaman marketing/checkout
 * nanti butuh ini tanpa auth). Commerce inisiatif, Fase A - lihat
 * guratan-api/CLAUDE.md bagian "Pricing & commerce".
 */
class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        $prices = PricingPlan::where('is_active', true)->get(['tier', 'price', 'updated_at']);

        return response()->json($prices);
    }
}
