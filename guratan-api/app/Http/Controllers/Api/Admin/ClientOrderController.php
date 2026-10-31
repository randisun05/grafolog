<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\GrafologMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:administrator'. Pesanan klien individu menurut tahap penentuan grafolog + rekomendasi. */
class ClientOrderController extends Controller
{
    public function __construct(private GrafologMatchingService $matching) {}

    public function index(Request $request): JsonResponse
    {
        $this->matching->sweep();
        $state = $request->validate(['state' => ['nullable', 'in:menunggu_admin,menunggu_konfirmasi,menunggu_pilih,diterima']])['state'] ?? 'menunggu_admin';

        $orders = Order::whereNull('batch_id')->whereNotNull('paid_at')->where('grafolog_state', $state)
            ->with(['user:id,name,email', 'product:id,nama,ringkasan', 'grafolog:id,name', 'grafologRequests.grafolog:id,name'])
            ->latest('id')->limit(50)->get()
            ->map(fn (Order $o) => [
                'id' => $o->id, 'client' => $o->user?->name, 'product' => $o->product?->nama, 'state' => $o->grafolog_state,
                'delivery_mode' => $o->delivery_mode, 'location_note' => $o->location_note, 'scheduled_for' => $o->scheduled_for?->toDateString(),
                'paid_at' => $o->paid_at, 'attempts' => $o->grafolog_attempts, 'grafolog' => $o->grafolog?->name, 'deadline' => $o->grafolog_deadline,
                'history' => $o->grafologRequests->map(fn ($r) => ['grafolog' => $r->grafolog?->name, 'status' => $r->status, 'note' => $r->note])->values(),
                'recommendations' => $state === 'menunggu_admin' ? $this->matching->recommend($o) : [],
            ]);

        return response()->json($orders);
    }

    public function assign(Request $request, Order $order): JsonResponse
    {
        abort_if($order->batch_id || ! $order->paid_at, 422, 'Hanya pesanan klien individu yang sudah dibayar.');
        $data = $request->validate(['grafolog_id' => ['required', 'integer', 'exists:users,id']]);
        $this->matching->adminAssign($order->load(['user', 'product']), User::findOrFail($data['grafolog_id']), $request->user());

        return response()->json(['state' => $order->fresh()->grafolog_state]);
    }
}
