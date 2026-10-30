<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat teks per pesanan klien individu (2026-10-30). Peserta: klien pemilik + grafolog yang sudah MENERIMA;
 * administrator boleh membaca (untuk sengketa; tercatat audit). Teks polos, tanpa lampiran.
 */
class OrderChatController extends Controller
{
    private const READ_ONLY_AFTER_DAYS = 14;

    /** @return 'client'|'grafolog'|'admin' */
    private function role(Request $request, Order $order): string
    {
        $u = $request->user();
        abort_if($order->batch_id, 404);
        if ($u->id === $order->user_id) {
            return 'client';
        }
        if ($order->grafolog_state === 'diterima' && $order->grafolog_id === $u->id) {
            return 'grafolog';
        }
        abort_unless($u->isAdministrator(), 403);

        return 'admin';
    }

    private function open(Order $order): bool
    {
        if ($order->grafolog_state !== 'diterima') {
            return false;
        }
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
            return false;
        }

        return $order->status !== Order::STATUS_DONE || ($order->completed_at && $order->completed_at->gt(now()->subDays(self::READ_ONLY_AFTER_DAYS)));
    }

    public function index(Request $request, Order $order): JsonResponse
    {
        $role = $this->role($request, $order);
        $after = (int) $request->query('after', 0);
        $messages = OrderMessage::where('order_id', $order->id)->where('id', '>', $after)->with('sender:id,name,role')->orderBy('id')->limit(200)->get();
        if ($role !== 'admin') {
            OrderMessage::where('order_id', $order->id)->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        } elseif ($after === 0) {
            AuditLog::record('lihat_chat_pesanan', Order::class, $order->id, $request->user()->id, $request->ip());
        }

        $other = $role === 'client' ? $order->grafolog : $order->user;

        return response()->json([
            'open' => $this->open($order) && $role !== 'admin',
            'you' => $request->user()->id,
            'with' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
            'order' => ['id' => $order->id, 'product' => $order->product?->nama],
            'messages' => $messages->map(fn (OrderMessage $m) => [
                'id' => $m->id, 'body' => $m->body, 'at' => $m->created_at, 'mine' => $m->sender_id === $request->user()->id,
                'sender' => $m->sender?->name, 'read' => $m->read_at !== null,
            ])->values(),
        ]);
    }

    public function store(Request $request, Order $order, Notifier $notifier): JsonResponse
    {
        $role = $this->role($request, $order);
        abort_if($role === 'admin', 403, 'Administrator hanya dapat membaca.');
        abort_unless($this->open($order), 422, 'Chat belum dibuka (menunggu grafolog menerima) atau sudah ditutup.');
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $body = trim(strip_tags($data['body']));
        abort_if($body === '', 422, 'Pesan kosong.');

        $hadUnread = OrderMessage::where('order_id', $order->id)->where('sender_id', $request->user()->id)->whereNull('read_at')->exists();
        $msg = OrderMessage::create(['order_id' => $order->id, 'sender_id' => $request->user()->id, 'body' => $body]);

        // Satu pemberitahuan per "rentetan" pesan belum dibaca - tidak membanjiri email.
        if (! $hadUnread) {
            $to = $role === 'client' ? $order->grafolog : $order->user;
            if ($to instanceof User) {
                $notifier->send($to, 'pesan_baru', 'Pesan baru pada pesanan #'.$order->id,
                    "{$request->user()->name} mengirim pesan tentang {$order->product?->nama}.", $role === 'client' ? "/pesanan/{$order->id}/chat" : "/pesanan/{$order->id}/chat");
            }
        }

        return response()->json(['id' => $msg->id], 201);
    }

    /** Jumlah pesan belum dibaca per pesanan untuk pengguna ini (lencana). */
    public function unread(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $rows = OrderMessage::whereNull('read_at')->where('sender_id', '!=', $uid)
            ->whereHas('order', fn ($q) => $q->where('user_id', $uid)->orWhere('grafolog_id', $uid))
            ->selectRaw('order_id, count(*) as n')->groupBy('order_id')->pluck('n', 'order_id');

        return response()->json(['total' => (int) $rows->sum(), 'by_order' => $rows]);
    }
}
