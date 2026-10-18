<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tugas asesmen untuk kandidat B2B (2026-10-18): jalur kedua unggah scan - kandidat sendiri,
 * bukan HR. Kandidat hanya melihat tugas & berkas scannya; laporan dan hasil milik perusahaan.
 */
class CandidateTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)->whereNotNull('batch_id')
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->with(['product:id,nama', 'batch.company:id,name', 'sample' => fn ($q) => $q->withCount('images')])
            ->latest('id')->get();

        return response()->json($orders->map(fn (Order $o) => [
            'order_id' => $o->id,
            'company' => $o->batch?->company?->name,
            'product' => $o->product?->nama,
            'paid' => $o->isPaid(),
            'needs_scan' => $o->sample_id !== null,
            'sample_id' => $o->sample_id,
            'scan_count' => $o->sample?->images_count ?? 0,
            'done' => $o->status === Order::STATUS_DONE || $o->sample?->status === 'completed',
        ]));
    }
}
