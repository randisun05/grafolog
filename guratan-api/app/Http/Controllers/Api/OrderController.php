<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Services\Commerce\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Pesanan produk oleh klien (B2C). Klien hanya melihat & membayar pesanannya sendiri. */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            // Pesanan B2B (batch) milik perusahaan, bukan kandidat - tidak ditampilkan/dibayar di sini.
            Order::where('user_id', $request->user()->id)->whereNull('batch_id')
                ->with(['product:id,kode,nama,kategori', 'deliveries.component:id,tipe,nama', 'deliveries.assignee:id,name',
                    'sample:id,status', 'sample.reports:id,sample_id,review_status'])
                ->latest()->get()
                ->map(fn (Order $o) => $this->present($o))
        );
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'discount_code' => ['nullable', 'string', 'max:32'],
        ]);
        $product = Product::findOrFail($data['product_id']);

        try {
            $quote = $this->orders->quote($product, $data['discount_code'] ?? null);
            $valid = true;
            $message = null;
        } catch (HttpException $e) {
            $quote = $this->orders->quote($product, null);
            $valid = false;
            $message = $e->getMessage();
        }

        return response()->json([
            'base_price' => $quote['base'],
            'final_price' => $quote['amount'],
            'discount_amount' => $quote['base'] - $quote['amount'],
            'code_valid' => ($data['discount_code'] ?? null) ? $valid : null,
            'code_message' => $message,
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'user', 403, 'Hanya klien yang memesan lewat halaman ini.');
        $product = Product::forClients()->find($request->validated('product_id'));
        abort_unless($product, 422, 'Produk tidak tersedia.');

        $order = $this->orders->create($user, $product, $request->validated('discount_code'), 'website', $user);

        return response()->json($this->present($order->fresh(['product', 'deliveries', 'sample'])), 201);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id && ! $order->batch_id, 403);

        try {
            return response()->json($this->orders->startPayment($order, $request->user(), $request->ip()), 201);
        } catch (RuntimeException $e) {
            // abort() (422 sudah dibayar/dibatalkan) juga RuntimeException - teruskan apa adanya.
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            Log::error('DOKU checkout pesanan gagal dibuat', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Pembayaran sedang tidak tersedia, coba lagi nanti.'], 503);
        }
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id && ! $order->batch_id, 403);
        $this->orders->cancel($order, 'Dibatalkan klien sebelum pembayaran.', $request->user(), $request->ip());

        return response()->json($this->present($order->fresh()));
    }

    private function present(Order $order): array
    {
        $report = $order->sample?->reports?->firstWhere('review_status', 'disetujui');

        return [
            'id' => $order->id,
            'status' => $order->status,
            'amount' => $order->amount,
            'base_amount' => $order->base_amount,
            'created_at' => $order->created_at,
            'paid_at' => $order->paid_at,
            'product' => $order->product?->only(['id', 'kode', 'nama', 'kategori']),
            'sample_id' => $order->sample_id,
            'sample_status' => $order->sample?->status,
            'report_id' => $report?->id,
            'refunded_at' => $order->refunded_at,
            'refund_note' => $order->refund_note,
            'deliveries' => $order->deliveries->map(fn ($d) => [
                'id' => $d->id,
                'status' => $d->status,
                'tipe' => $d->component?->tipe,
                'nama' => $d->component?->nama,
                'assignee' => $d->assignee?->name,
                'scheduled_at' => $d->scheduled_at,
                'meeting_url' => $d->meeting_url,
                'has_report' => $d->report_submitted_at !== null,
            ])->values(),
        ];
    }
}
