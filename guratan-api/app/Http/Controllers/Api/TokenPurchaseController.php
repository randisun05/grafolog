<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTokenPurchaseRequest;
use App\Models\AuditLog;
use App\Models\DiscountCode;
use App\Models\TokenPrice;
use App\Models\TokenPurchase;
use App\Services\Payment\DokuService;
use App\Services\Payment\PaymentMode;
use App\Services\TokenWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mulai pembayaran DOKU untuk pembelian token grafolog. Struktur method
 * ini sengaja dibuat semirip mungkin dengan PaymentController::store
 * (base_amount/discount_code/try-catch 503) - reuse pola yang sudah
 * terverifikasi, bukan menciptakan cara baru untuk hal yang sama.
 */
class TokenPurchaseController extends Controller
{
    public function __construct(private DokuService $doku, private TokenWalletService $tokenWallet) {}

    public function store(CreateTokenPurchaseRequest $request): JsonResponse
    {
        $user = $request->user();
        $quantity = $request->validated('quantity');

        $unitPrice = TokenPrice::current();
        abort_if($unitPrice === null, 422, 'Harga token belum dikonfigurasi.');

        $baseAmount = $unitPrice * $quantity;

        $discountCode = null;
        $amount = $baseAmount;
        if ($codeInput = $request->validated('discount_code')) {
            $discountCode = DiscountCode::where('code', strtoupper($codeInput))->first();
            abort_unless($discountCode?->isValidFor('token', 'grafolog'), 422, 'Kode diskon tidak berlaku.');
            $amount = $baseAmount - $discountCode->amountOff($baseAmount);
        }

        $purchase = TokenPurchase::create([
            'user_id' => $user->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'base_amount' => $baseAmount,
            'discount_code_id' => $discountCode?->id,
            'amount' => $amount,
            'currency' => 'IDR',
            'status' => 'pending',
            'invoice_number' => 'TOKEN-'.now()->format('Ymd').'-'.$user->id.'-'.Str::upper(Str::random(6)),
        ]);

        if (PaymentMode::bypassed()) {
            $purchase->update(['status' => 'paid', 'paid_at' => now(), 'notification_payload' => ['bypass' => true]]);
            $this->tokenWallet->credit($user, $purchase->quantity, ['token_purchase_id' => $purchase->id]);
            $purchase->discountCode?->incrementUsage();
            AuditLog::record('pembelian_token_mode_uji', 'token_purchase', $purchase->id, $user->id, $request->ip());

            return response()->json([
                'paid' => true,
                'bypass' => true,
                'invoice_number' => $purchase->invoice_number,
            ], 201);
        }

        try {
            $checkout = $this->doku->createCheckout(
                $purchase->invoice_number,
                $purchase->amount,
                $purchase->currency,
                $user->name,
                $user->email,
            );
        } catch (RuntimeException $e) {
            Log::error('DOKU checkout token gagal dibuat', ['token_purchase_id' => $purchase->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Pembayaran sedang tidak tersedia, coba lagi nanti.',
            ], 503);
        }

        $purchase->update([
            'doku_token_id' => $checkout['token_id'],
            'doku_payment_url' => $checkout['url'],
        ]);

        return response()->json([
            'payment_url' => $checkout['url'],
            'invoice_number' => $purchase->invoice_number,
        ], 201);
    }
}
