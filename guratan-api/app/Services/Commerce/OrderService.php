<?php

namespace App\Services\Commerce;

use App\Models\AuditLog;
use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\DokuService;
use App\Services\Payment\PaymentMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alur pesanan produk (2026-10-17): buat pesanan -> bayar (DOKU atau mode uji) -> lunas: layanan
 * pendukung dibuat -> selesai saat laporan grafologi disetujui supervisor dan semua layanan
 * dilaporkan. Komponen grafologi selalu memakai tier `master` (Comprehensive tidak dijual lagi).
 */
class OrderService
{
    public function __construct(private DokuService $doku) {}

    /** @return array{base:int, amount:int, discount:?DiscountCode} */
    public function quote(Product $product, ?string $code): array
    {
        $base = $product->currentPrice();
        abort_if($base === null, 422, "Harga produk {$product->nama} belum diatur.");

        $discount = null;
        $amount = $base;
        if ($code) {
            $discount = DiscountCode::where('code', strtoupper($code))->first();
            abort_unless($discount?->isValidFor($product->kode), 422, 'Kode promo tidak berlaku untuk produk ini.');
            $amount = $base - $discount->amountOff($base);
        }

        return ['base' => $base, 'amount' => $amount, 'discount' => $discount];
    }

    public function create(User $client, Product $product, ?string $code, string $source, User $createdBy): Order
    {
        abort_unless($product->is_active, 422, 'Produk ini sedang tidak dijual.');
        $quote = $this->quote($product, $code);

        return DB::transaction(function () use ($client, $product, $quote, $source, $createdBy) {
            $sample = null;
            if ($product->hasGraphology()) {
                $project = Project::create([
                    'source' => match ($source) {
                        'b2b' => 'hr',
                        'grafolog' => 'grafolog',
                        default => 'client',
                    },
                    'created_by' => $createdBy->id,
                ]);
                $sample = HandwritingSample::create([
                    'project_id' => $project->id,
                    'user_id' => $client->id,
                    'created_by' => $createdBy->id,
                    'tier' => 'master',
                    'status' => 'pending',
                ]);
            }

            return Order::create([
                'user_id' => $client->id,
                'product_id' => $product->id,
                'sample_id' => $sample?->id,
                'base_amount' => $quote['base'],
                'amount' => $quote['amount'],
                'discount_code_id' => $quote['discount']?->id,
                'source' => $source,
                'created_by' => $createdBy->id,
            ]);
        });
    }

    /**
     * @return array{paid:bool, bypass?:bool, payment_url?:string, invoice_number:string}
     *
     * @throws \RuntimeException bila DOKU menolak/tidak terkonfigurasi (pemanggil mengubahnya jadi 503)
     */
    public function startPayment(Order $order, User $payer, ?string $ip = null): array
    {
        abort_if($order->status === Order::STATUS_CANCELLED, 422, 'Pesanan sudah dibatalkan.');
        abort_if($order->isPaid(), 422, 'Pesanan ini sudah dibayar.');

        $payment = Payment::create([
            'order_id' => $order->id,
            'sample_id' => $order->sample_id,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-O'.$order->id.'-'.Str::upper(Str::random(6)),
            'base_amount' => $order->base_amount,
            'amount' => $order->amount,
            'discount_code_id' => $order->discount_code_id,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        // Gratis (diskon 100%) atau mode uji: langsung lunas tanpa DOKU.
        if ($order->amount === 0 || PaymentMode::bypassed()) {
            $payment->update(['status' => 'paid', 'paid_at' => now(), 'notification_payload' => ['bypass' => PaymentMode::bypassed()]]);
            $order->discountCode?->incrementUsage();
            $this->markPaid($order);
            AuditLog::record('pembayaran_mode_uji', Order::class, $order->id, $payer->id, $ip);

            return ['paid' => true, 'bypass' => true, 'invoice_number' => $payment->invoice_number];
        }

        $checkout = $this->doku->createCheckout($payment->invoice_number, $payment->amount, 'IDR', $payer->name, $payer->email);
        $payment->update(['doku_token_id' => $checkout['token_id'], 'doku_payment_url' => $checkout['url']]);

        return ['paid' => false, 'payment_url' => $checkout['url'], 'invoice_number' => $payment->invoice_number];
    }

    /** Dipanggil saat pembayaran sukses (mode uji, gratis, atau webhook DOKU). Idempoten. */
    public function markPaid(Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => Order::STATUS_PROCESSING, 'paid_at' => now()]);
            foreach ($order->product->components as $component) {
                if ($component->tipe === 'grafologi') {
                    continue;
                }
                $order->deliveries()->create([
                    'product_component_id' => $component->id,
                    'user_id' => $order->user_id,
                ]);
            }
        });
    }

    /** Tandai selesai bila laporan grafologi disetujui dan semua layanan sudah dilaporkan. */
    public function refreshStatus(Order $order): void
    {
        if ($order->status !== Order::STATUS_PROCESSING) {
            return;
        }

        $graphologyDone = ! $order->sample_id
            || $order->sample?->reports()->where('review_status', 'disetujui')->exists();
        $servicesDone = $order->deliveries()->whereNotIn('status', ['selesai', 'dibatalkan'])->doesntExist();

        if ($graphologyDone && $servicesDone) {
            $order->update(['status' => Order::STATUS_DONE, 'completed_at' => now()]);
        }
    }

    public function cancel(Order $order, string $reason, User $by, ?string $ip = null): void
    {
        abort_if($order->isPaid(), 422, 'Pesanan yang sudah dibayar dibatalkan lewat proses refund.');
        abort_if($order->status === Order::STATUS_CANCELLED, 422, 'Pesanan sudah dibatalkan.');

        $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        AuditLog::record('batalkan_pesanan', Order::class, $order->id, $by->id, $ip);
    }
}
