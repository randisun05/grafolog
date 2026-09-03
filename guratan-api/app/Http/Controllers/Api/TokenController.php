<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewTokenPurchaseRequest;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\TokenCost;
use App\Models\TokenLedgerEntry;
use App\Models\TokenPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bacaan seputar token grafolog: harga saat ini, saldo + riwayat wallet,
 * dan pratinjau harga pembelian. Aksi yang benar-benar mengubah saldo
 * (beli token, potong saat submit laporan) hidup di TokenPurchaseController
 * dan ScoringController - controller ini murni baca/hitung, sama pembagian
 * peran dengan PricingController (publik) vs PaymentController (aksi).
 */
class TokenController extends Controller
{
    public function price(): JsonResponse
    {
        return response()->json(['price_per_token' => TokenPrice::current()]);
    }

    public function wallet(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance' => $user->token_balance,
            'price_per_token' => TokenPrice::current(),
            // Ditambahkan supaya UI Portal Grafolog bisa peringatkan "token
            // tidak cukup" SEBELUM grafolog mulai isi form 40 aspek, bukan
            // baru ketahuan setelah submit gagal 402 - null berarti gate
            // belum dikonfigurasi admin untuk tier itu (tidak ada batasan).
            // Loop atas produk aktif (bukan 2 kunci hardcoded lagi) - lihat
            // guratan-api/CLAUDE.md "Sistem Products data-driven".
            'costs' => collect(Product::activeCodes())
                ->mapWithKeys(fn (string $code) => [$code => TokenCost::activeTokensFor($code)]),
            'transactions' => TokenLedgerEntry::where('user_id', $user->id)
                ->latest()
                ->take(20)
                ->get(['id', 'type', 'delta', 'balance_after', 'report_id', 'token_purchase_id', 'created_at']),
        ]);
    }

    public function preview(PreviewTokenPurchaseRequest $request): JsonResponse
    {
        $quantity = $request->validated('quantity');
        $unitPrice = TokenPrice::current();
        abort_if($unitPrice === null, 422, 'Harga token belum dikonfigurasi.');

        $baseAmount = $unitPrice * $quantity;

        $codeInput = $request->validated('code');
        $discount = null;
        $codeValid = null;
        $codeMessage = null;

        if ($codeInput) {
            $discount = DiscountCode::where('code', strtoupper($codeInput))->first();
            $codeValid = (bool) $discount?->isValidFor('token');
            $codeMessage = $codeValid ? null : 'Kode diskon tidak berlaku.';
        }

        $discountAmount = $codeValid ? $discount->amountOff($baseAmount) : 0;

        return response()->json([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'base_amount' => $baseAmount,
            'code' => $codeInput,
            'code_valid' => $codeValid,
            'code_message' => $codeMessage,
            'discount_amount' => $discountAmount,
            'final_amount' => $baseAmount - $discountAmount,
        ]);
    }
}
