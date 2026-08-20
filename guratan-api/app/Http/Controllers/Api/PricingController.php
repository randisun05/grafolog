<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewPricingRequest;
use App\Models\DiscountCode;
use App\Models\PricingPlan;
use Illuminate\Http\JsonResponse;

/**
 * index() is public read - harga ditampilkan sebelum login (halaman
 * marketing/checkout nanti butuh ini tanpa auth). preview() butuh login
 * (di-daftarkan di grup auth:sanctum routes/api.php), murni baca/hitung,
 * tidak menyimpan/memakai kuota kode apa pun - DiscountCode::incrementUsage()
 * baru dipanggil saat checkout sungguhan ada (Commerce Fase D).
 */
class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        $prices = PricingPlan::where('is_active', true)->get(['tier', 'price', 'updated_at']);

        return response()->json($prices);
    }

    public function preview(PreviewPricingRequest $request): JsonResponse
    {
        $tier = $request->validated('tier');
        $basePrice = PricingPlan::activePriceFor($tier);
        abort_if($basePrice === null, 422, "Harga untuk tier {$tier} belum dikonfigurasi.");

        $codeInput = $request->validated('code');
        $discount = null;
        $codeValid = null;
        $codeMessage = null;

        if ($codeInput) {
            $discount = DiscountCode::where('code', strtoupper($codeInput))->first();
            $codeValid = (bool) $discount?->isValidFor($tier);
            $codeMessage = $codeValid ? null : 'Kode diskon tidak berlaku.';
        }

        $discountAmount = $codeValid ? $discount->amountOff($basePrice) : 0;

        return response()->json([
            'tier' => $tier,
            'base_price' => $basePrice,
            'code' => $codeInput,
            'code_valid' => $codeValid,
            'code_message' => $codeMessage,
            'discount_amount' => $discountAmount,
            'final_price' => $basePrice - $discountAmount,
        ]);
    }
}
