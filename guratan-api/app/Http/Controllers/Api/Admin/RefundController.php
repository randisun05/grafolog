<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gated 'role:administrator'. Kebijakan refund (2026-10-18): pesanan yang sudah dibayar TIDAK
 * dapat dibatalkan pengguna. Refund hanya dipicu administrator, hanya karena:
 * - `kesalahan_sistem` (mis. bayar sukses tetapi layanan tidak terproses), atau
 * - `grafolog_terlambat`: pesanan belum selesai > 90 hari sejak dibayar.
 * Dana dikembalikan manual lewat dashboard DOKU - sistem mencatat keputusan & menutup layanan.
 */
class RefundController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'overdue' => Order::where('status', Order::STATUS_PROCESSING)
                ->where('paid_at', '<', now()->subDays(Order::REFUND_OVERDUE_DAYS))
                ->with(['user:id,name,email', 'product:id,kode,nama', 'grafolog:id,name'])
                ->oldest('paid_at')->get(),
            'refunded' => Order::where('status', Order::STATUS_REFUNDED)
                ->with(['user:id,name,email', 'product:id,kode,nama'])
                ->latest('refunded_at')->limit(50)->get(),
        ]);
    }

    public function refund(Request $request, Order $order, Notifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'in:kesalahan_sistem,grafolog_terlambat'],
            'note' => ['required', 'string', 'max:1000'],
        ]);
        abort_unless($order->isPaid(), 422, 'Pesanan belum dibayar - tidak perlu refund.');
        abort_if($order->status === Order::STATUS_REFUNDED, 422, 'Pesanan ini sudah di-refund.');
        if ($data['reason'] === 'grafolog_terlambat') {
            abort_unless($order->isOverdueForRefund(), 422, 'Pesanan belum melewati '.Order::REFUND_OVERDUE_DAYS.' hari sejak dibayar atau sudah selesai.');
        }

        DB::transaction(function () use ($order, $data, $request) {
            $order->update([
                'status' => Order::STATUS_REFUNDED, 'refunded_at' => now(), 'refund_status' => 'disetujui',
                'refund_reason' => $data['reason'], 'refund_note' => $data['note'], 'refund_decided_by' => $request->user()->id,
            ]);
            // Sampel ikut terkunci: HandwritingSample::isPaid() menolak pesanan berstatus dikembalikan.
            $order->deliveries()->whereNotIn('status', ['selesai'])->update(['status' => 'dibatalkan']);
        });
        AuditLog::record('refund_admin', Order::class, $order->id, $request->user()->id, $request->ip());
        $notifier->send($order->user, 'refund', 'Pesanan Anda di-refund',
            "Pesanan #{$order->id} dikembalikan dananya. Catatan: {$data['note']}", '/riwayat');

        return response()->json($order->fresh());
    }
}
