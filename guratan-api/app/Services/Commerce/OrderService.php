<?php

namespace App\Services\Commerce;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Order;
use App\Models\OrderBatch;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Payment\DokuService;
use App\Services\Payment\PaymentMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alur pesanan produk (2026-10-17): buat pesanan -> bayar (DOKU atau mode uji) -> lunas: layanan
 * pendukung dibuat -> selesai saat laporan grafologi disetujui supervisor dan semua layanan
 * dilaporkan. Komponen grafologi selalu memakai tier `master` (Comprehensive tidak dijual lagi).
 */
class OrderService
{
    public function __construct(private DokuService $doku, private Notifier $notifier) {}

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

    public function create(User $client, Product $product, ?string $code, string $source, User $createdBy, ?array $quote = null, array $extra = []): Order
    {
        abort_unless($product->is_active, 422, 'Produk ini sedang tidak dijual.');
        $quote ??= $this->quote($product, $code);

        return DB::transaction(function () use ($client, $product, $quote, $source, $createdBy, $extra) {
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
            ] + $extra);
        });
    }

    /**
     * Pesanan B2B (Fase C): satu produk untuk banyak kandidat, satu pembayaran. Harga & diskon
     * dihitung per kandidat lalu dikalikan. Grafolog pilihan HR ditugaskan otomatis saat lunas.
     *
     * @param  Collection<int, User>  $candidates
     */
    public function createBatch(Company $company, User $hr, Product $product, Collection $candidates, ?string $code, ?User $grafolog, ?string $serviceMonth = null): OrderBatch
    {
        abort_unless($product->is_active, 422, 'Produk ini sedang tidak dijual.');
        $quote = $this->quote($product, $code);
        $n = $candidates->count();

        return DB::transaction(function () use ($company, $hr, $product, $candidates, $quote, $grafolog, $n, $serviceMonth) {
            $batch = OrderBatch::create([
                'company_id' => $company->id,
                'product_id' => $product->id,
                'grafolog_id' => $grafolog?->id,
                'service_month' => $serviceMonth ?? now()->format('Y-m'),
                'base_amount' => $quote['base'] * $n,
                'amount' => $quote['amount'] * $n,
                'discount_code_id' => $quote['discount']?->id,
                'created_by' => $hr->id,
            ]);
            foreach ($candidates as $candidate) {
                $this->create($candidate, $product, null, 'b2b', $hr, $quote, [
                    'batch_id' => $batch->id,
                    'grafolog_id' => $grafolog?->id,
                ]);
            }

            return $batch;
        });
    }

    /** @return array{paid:bool, bypass?:bool, payment_url?:string, invoice_number:string} */
    public function startBatchPayment(OrderBatch $batch, User $payer, ?string $ip = null): array
    {
        abort_if($batch->status === Order::STATUS_CANCELLED, 422, 'Pesanan sudah dibatalkan.');
        abort_if($batch->isPaid(), 422, 'Pesanan ini sudah dibayar.');

        $payment = Payment::create([
            'order_batch_id' => $batch->id,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-B'.$batch->id.'-'.Str::upper(Str::random(6)),
            'base_amount' => $batch->base_amount,
            'amount' => $batch->amount,
            'discount_code_id' => $batch->discount_code_id,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        if ($batch->amount === 0 || PaymentMode::bypassed()) {
            $payment->update(['status' => 'paid', 'paid_at' => now(), 'notification_payload' => ['bypass' => PaymentMode::bypassed()]]);
            $batch->discountCode?->incrementUsage();
            $this->markBatchPaid($batch);
            AuditLog::record('pembayaran_mode_uji', OrderBatch::class, $batch->id, $payer->id, $ip);

            return ['paid' => true, 'bypass' => true, 'invoice_number' => $payment->invoice_number];
        }

        $checkout = $this->doku->createCheckout($payment->invoice_number, $payment->amount, 'IDR', $payer->name, $payer->email);
        $payment->update(['doku_token_id' => $checkout['token_id'], 'doku_payment_url' => $checkout['url']]);

        return ['paid' => false, 'payment_url' => $checkout['url'], 'invoice_number' => $payment->invoice_number];
    }

    /** Idempoten - dipanggil mode uji/gratis atau webhook DOKU. */
    public function markBatchPaid(OrderBatch $batch): void
    {
        if ($batch->isPaid()) {
            return;
        }

        DB::transaction(function () use ($batch) {
            $batch->update(['status' => Order::STATUS_PROCESSING, 'paid_at' => now()]);
            $orders = $batch->orders()->where('status', Order::STATUS_UNPAID)->get();
            $orders->each(fn (Order $o) => $this->markPaid($o));
            CandidateProfile::where('company_id', $batch->company_id)
                ->whereIn('user_id', $orders->pluck('user_id'))
                ->where('stage', 'baru')
                ->update(['stage' => 'asesmen']);
        });

        // Satu notifikasi per pihak (bukan per kandidat): HR, grafolog pilihan, dan tiap kandidat (kotak masuk saja).
        $n = $batch->orders()->count();
        if ($batch->creator) {
            $this->notifier->send($batch->creator, 'pembayaran', 'Pembayaran pesanan perusahaan diterima',
                "Pesanan #{$batch->id} ({$n} kandidat) lunas. Grafolog pilihan sudah ditugaskan.", '/hr/candidates?tab=pesanan');
        }
        if ($batch->grafolog) {
            $this->notifier->send($batch->grafolog, 'penugasan', 'Penugasan baru dari perusahaan',
                "Anda ditugaskan menangani {$n} kandidat (bulan layanan {$batch->service_month}) dari pesanan #{$batch->id}.", '/grafolog/ditugaskan');
        }
        $batch->orders()->with('user')->get()->each(fn (Order $o) => $this->notifier->send(
            $o->user, 'tugas_asesmen', 'Tugas asesmen dari perusahaan',
            'Unggah hasil scan tulisan tangan Anda (PDF) untuk asesmen yang dipesan perusahaan.', '/tugas-asesmen', mail: false,
        ));
    }

    public function cancelBatch(OrderBatch $batch, string $reason, User $by, ?string $ip = null): void
    {
        abort_if($batch->isPaid(), 422, 'Pesanan yang sudah dibayar dibatalkan lewat proses refund.');
        abort_if($batch->status === Order::STATUS_CANCELLED, 422, 'Pesanan sudah dibatalkan.');

        DB::transaction(function () use ($batch, $reason) {
            $batch->update(['status' => Order::STATUS_CANCELLED]);
            $batch->orders()->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        });
        AuditLog::record('batalkan_pesanan_b2b', OrderBatch::class, $batch->id, $by->id, $ip);
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
                ] + ($order->grafolog_id ? ['assigned_to' => $order->grafolog_id, 'status' => 'ditugaskan'] : []));
            }

            // Grafolog pilihan pemesan (B2B: HR memilih grafolog) langsung menangani sampelnya.
            if ($order->grafolog_id && $order->sample_id) {
                Assignment::updateOrCreate(
                    ['sample_id' => $order->sample_id],
                    ['grafolog_id' => $order->grafolog_id, 'assigned_by' => $order->created_by, 'status' => 'assigned'],
                );
            }
        });

        // Notifikasi di luar transaksi. B2C: ke klien; layanan/penugasan: ke grafolog yang ditugaskan.
        if (! $order->batch_id) {
            $this->notifier->send($order->user, 'pembayaran', 'Pembayaran diterima',
                "Pesanan #{$order->id} ({$order->product->nama}) sudah dibayar dan sedang diproses.", '/riwayat');
        }
        if ($order->grafolog_id && ! $order->batch_id) {
            $this->notifier->send($order->grafolog, 'penugasan', 'Penugasan baru',
                "Anda ditugaskan menangani pesanan #{$order->id} ({$order->product->nama}) untuk kandidat/klien.", '/grafolog/ditugaskan');
        }
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
            if ($order->batch && $order->batch->orders()->whereNotIn('status', [Order::STATUS_DONE, Order::STATUS_CANCELLED])->doesntExist()) {
                $order->batch->update(['status' => Order::STATUS_DONE]);
            }
            if ($order->batch) {
                CandidateProfile::where('company_id', $order->batch->company_id)->where('user_id', $order->user_id)
                    ->whereIn('stage', ['baru', 'asesmen'])->update(['stage' => 'selesai']);
            }
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
