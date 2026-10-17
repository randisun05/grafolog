<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gated 'role:administrator'. SOP refund (Fase E, 2026-10-17): admin memutuskan permintaan refund
 * klien, atau me-refund pesanan lunas atas inisiatif sendiri (mis. B2B, gangguan teknis). Dana
 * dikembalikan manual lewat dashboard DOKU - sistem mencatat keputusan & menutup layanan.
 */
class RefundController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Order::where('refund_status', 'diajukan')
                ->with(['user:id,name,email', 'product:id,kode,nama'])
                ->oldest('refund_requested_at')->get()
        );
    }

    public function decide(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['required', 'string', 'max:1000'],
        ]);
        abort_unless($order->isPaid(), 422, 'Pesanan belum dibayar - batalkan saja, tidak perlu refund.');
        abort_if($order->status === Order::STATUS_REFUNDED, 422, 'Pesanan ini sudah di-refund.');

        $approve = $data['decision'] === 'approve';
        abort_if(! $approve && $order->refund_status !== 'diajukan', 422, 'Tidak ada permintaan refund untuk ditolak.');

        DB::transaction(function () use ($order, $approve, $data, $request) {
            $order->update([
                'refund_status' => $approve ? 'disetujui' : 'ditolak',
                'refund_note' => $data['note'],
                'refund_decided_by' => $request->user()->id,
            ] + ($approve ? ['status' => Order::STATUS_REFUNDED, 'refunded_at' => now()] : []));

            if ($approve) {
                // Sampel ikut terkunci: HandwritingSample::isPaid() menolak pesanan berstatus dikembalikan.
                $order->deliveries()->whereNotIn('status', ['selesai'])->update(['status' => 'dibatalkan']);
            }
        });
        AuditLog::record($approve ? 'refund_disetujui' : 'refund_ditolak', Order::class, $order->id, $request->user()->id, $request->ip());

        return response()->json($order->fresh());
    }
}
