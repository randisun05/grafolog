<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrafologRequest;
use App\Models\Order;
use App\Services\Commerce\GrafologMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:grafolog'. Permintaan penanganan pesanan klien (jawab dalam 24 jam). */
class GrafologRequestController extends Controller
{
    public function __construct(private GrafologMatchingService $matching) {}

    public function index(Request $request): JsonResponse
    {
        $this->matching->sweep();
        $row = fn (GrafologRequest $r) => [
            'id' => $r->id, 'status' => $r->status, 'expires_at' => $r->expires_at, 'responded_at' => $r->responded_at, 'note' => $r->note,
            'order' => [
                'id' => $r->order_id, 'product' => $r->order->product?->nama, 'client' => $r->order->user?->name,
                'delivery_mode' => $r->order->delivery_mode, 'location_note' => $r->order->location_note,
                'scheduled_for' => $r->order->scheduled_for?->toDateString(), 'upload_deadline' => $r->order->upload_deadline?->toDateString(),
                'client_id' => $r->order->user_id,
            ],
        ];
        $mine = GrafologRequest::where('grafolog_id', $request->user()->id)->with('order.product', 'order.user:id,name');

        return response()->json([
            'pending' => (clone $mine)->where('status', GrafologRequest::WAITING)->orderBy('expires_at')->get()->map($row)->values(),
            'history' => (clone $mine)->where('status', '!=', GrafologRequest::WAITING)->latest('id')->limit(15)->get()->map($row)->values(),
            'accepted' => Order::whereNull('batch_id')->where('grafolog_id', $request->user()->id)->where('grafolog_state', 'diterima')
                ->whereIn('status', [Order::STATUS_PROCESSING, Order::STATUS_DONE])
                ->with(['user:id,name', 'product:id,nama'])
                ->withCount(['messages as unread' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)])
                ->latest('id')->limit(30)->get()
                ->map(fn ($o) => ['id' => $o->id, 'product' => $o->product?->nama, 'client' => $o->user?->name, 'status' => $o->status, 'unread' => $o->unread,
                    'delivery_mode' => $o->delivery_mode, 'scheduled_for' => $o->scheduled_for?->toDateString(), 'sample_id' => $o->sample_id])->values(),
            'confirm_hours' => GrafologMatchingService::CONFIRM_HOURS,
        ]);
    }

    public function respond(Request $request, GrafologRequest $grafologRequest): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:accept,decline'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->matching->respond($grafologRequest, $request->user(), $data['decision'] === 'accept', $data['note'] ?? null);

        return response()->json(['status' => $grafologRequest->fresh()->status]);
    }
}
