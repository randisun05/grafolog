<?php

namespace App\Services\Commerce;

use App\Models\GrafologEarning;
use App\Models\GrafologPayout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCommission;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Komisi grafolog per pesanan (2026-10-31). Aturan per produk: default produk (percent|fixed) dengan override per
 * grafolog (`product_commissions`). Dihitung dari nilai bayar BERSIH pesanan (setelah diskon) saat pesanan SELESAI;
 * dibatalkan bila pesanan direfund sebelum dicairkan. Pencairan dicatat admin (transfer di luar sistem).
 * Token bukan uang dan tidak berkaitan dengan komisi.
 */
class EarningService
{
    public function __construct(private Notifier $notifier) {}

    /** @return array{type:string, value:int} aturan yang berlaku untuk grafolog pada produk ini. */
    public function ruleFor(Product $product, int $grafologId): array
    {
        $override = ProductCommission::where('product_id', $product->id)->where('grafolog_id', $grafologId)->first();

        return $override
            ? ['type' => $override->commission_type, 'value' => (int) $override->commission_value]
            : ['type' => $product->commission_type, 'value' => (int) $product->commission_value];
    }

    public static function compute(string $type, int $value, int $base): int
    {
        return $type === 'fixed' ? $value : (int) round($base * min(100, $value) / 100);
    }

    /** Dipanggil saat pesanan selesai. Idempoten; tidak membuat baris bila komisi 0. */
    public function accrue(Order $order): ?GrafologEarning
    {
        if (! $order->grafolog_id || $order->status !== Order::STATUS_DONE) {
            return null;
        }
        $product = $order->product;
        $rule = $this->ruleFor($product, $order->grafolog_id);
        $amount = self::compute($rule['type'], $rule['value'], (int) $order->amount);
        if ($amount <= 0) {
            return null;
        }
        $earning = GrafologEarning::firstOrCreate(
            ['order_id' => $order->id, 'grafolog_id' => $order->grafolog_id],
            ['product_id' => $product->id, 'base_amount' => (int) $order->amount, 'commission_type' => $rule['type'],
                'commission_value' => $rule['value'], 'amount' => $amount, 'status' => GrafologEarning::EARNED, 'earned_at' => now()],
        );
        if ($earning->wasRecentlyCreated) {
            $this->notifier->send($order->grafolog, 'komisi', 'Komisi diperoleh',
                'Komisi Rp '.number_format($amount, 0, ',', '.')." dari pesanan #{$order->id} tercatat dan menunggu pencairan.", '/pendapatan', mail: false);
        }

        return $earning;
    }

    /** Pesanan direfund: komisi yang belum dicairkan dibatalkan; yang sudah dicairkan diberi catatan penyesuaian. */
    public function reverse(Order $order): void
    {
        GrafologEarning::where('order_id', $order->id)->get()->each(function (GrafologEarning $e) {
            if ($e->status === GrafologEarning::EARNED) {
                $e->update(['status' => GrafologEarning::CANCELLED, 'note' => 'Pesanan direfund.']);
            } elseif ($e->status === GrafologEarning::PAID) {
                $e->update(['note' => 'Pesanan direfund setelah komisi dicairkan - perlu penyesuaian pada pencairan berikutnya.']);
            }
        });
    }

    /** Cairkan komisi 'diperoleh' milik satu grafolog (semua, atau hanya id tertentu). */
    public function payout(User $grafolog, ?array $earningIds, User $by, ?string $reference, ?string $note): GrafologPayout
    {
        return DB::transaction(function () use ($grafolog, $earningIds, $by, $reference, $note) {
            $q = GrafologEarning::where('grafolog_id', $grafolog->id)->where('status', GrafologEarning::EARNED)->lockForUpdate();
            if ($earningIds !== null) {
                $q->whereIn('id', $earningIds);
            }
            $rows = $q->get();
            abort_if($rows->isEmpty(), 422, 'Tidak ada komisi yang bisa dicairkan.');

            $payout = GrafologPayout::create([
                'grafolog_id' => $grafolog->id, 'total' => $rows->sum('amount'), 'reference' => $reference, 'note' => $note,
                'paid_by' => $by->id, 'paid_at' => now(),
            ]);
            GrafologEarning::whereIn('id', $rows->pluck('id'))->update(['status' => GrafologEarning::PAID, 'payout_id' => $payout->id]);
            $this->notifier->send($grafolog, 'komisi', 'Komisi dicairkan',
                'Komisi Rp '.number_format($payout->total, 0, ',', '.').' telah dicairkan'.($reference ? " (ref. {$reference})" : '').'.', '/pendapatan');

            return $payout;
        });
    }
}
