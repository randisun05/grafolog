<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\GrafologMatchingService;
use App\Services\Commerce\OrderService;
use App\Services\Cv\CvService;
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
            'code_payment_methods' => $quote['discount']?->payment_methods,
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'user', 403, 'Hanya klien yang memesan lewat halaman ini.');
        $product = Product::forClients()->find($request->validated('product_id'));
        abort_unless($product, 422, 'Produk tidak tersedia.');

        abort_if(app(CvService::class)->missingRequired($user) > 0, 422, 'Lengkapi CV Anda (isian wajib) sebelum memesan.');
        // Klien bawaan grafolog: lihat komentar pada 'grafolog_id' di bawah. Source tetap 'website'
        // supaya gerbang bayar tidak terlewati.
        $bringer = $user->referred_by ? User::find($user->referred_by) : null;
        $data = $request->validated();
        $mode = $data['delivery_mode'] ?? $product->availableModes()[0];
        abort_unless(in_array($mode, $product->availableModes(), true), 422, 'Mode pelaksanaan tidak tersedia untuk produk ini.');
        abort_if($mode === 'offline' && blank($data['location_note'] ?? null), 422, 'Isi kota/tempat pelaksanaan untuk sesi offline.');
        // Tanpa komitmen eksplisit: batas unggah persyaratan default 3 hari (UI selalu meminta klien menentukan).

        $choice = ($data['grafolog_choice'] ?? 'rekomendasi') === 'pilih' ? 'pilih' : 'rekomendasi';
        $pick = null;
        if ($choice === 'pilih') {
            abort_unless($data['grafolog_id'] ?? null, 422, 'Pilih grafolog atau minta rekomendasi.');
            $pick = User::findOrFail($data['grafolog_id']);
            app(GrafologMatchingService::class)->assertSelectable($pick, ($data['scheduled_for'] ?? null) ? substr($data['scheduled_for'], 0, 7) : now()->format('Y-m'));
        }

        $order = $this->orders->create($user, $product, $data['discount_code'] ?? null, 'website', $user, null, [
            'delivery_mode' => $mode,
            'scheduled_for' => $data['scheduled_for'] ?? null,
            'upload_deadline' => $data['upload_deadline'] ?? ($product->requires_handwriting ? now()->addDays(3)->toDateString() : null),
            'location_note' => $mode === 'offline' ? $data['location_note'] : null,
            'grafolog_choice' => $choice,
            // Pilihan eksplisit klien menang; kalau tidak memilih, klien bawaan grafolog
            // tetap ditugaskan otomatis ke pembawanya saat lunas (OrderService::markPaid).
            'grafolog_id' => $pick?->id ?? ($bringer?->isGrafolog() ? $bringer->id : null),
        ]);

        return response()->json($this->present($order->fresh(['product', 'deliveries', 'sample'])), 201);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id && ! $order->batch_id, 403);

        try {
            return response()->json($this->orders->startPayment($order, $request->user(), $request->ip(), $this->method($request), $this->gateway($request)), 201);
        } catch (RuntimeException $e) {
            // abort() (422 sudah dibayar/dibatalkan) juga RuntimeException - teruskan apa adanya.
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            Log::error('Checkout pesanan gagal dibuat', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Pembayaran sedang tidak tersedia, coba lagi nanti.'], 503);
        }
    }

    /** Klien memilih (ulang) grafolog atau meminta rekomendasi tim. */
    public function chooseGrafolog(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id && ! $order->batch_id, 403);
        $data = $request->validate(['grafolog_id' => ['nullable', 'integer', 'exists:users,id'], 'rekomendasi' => ['sometimes', 'boolean']]);
        $grafolog = ($data['grafolog_id'] ?? null) ? User::find($data['grafolog_id']) : null;
        app(GrafologMatchingService::class)->clientChoose($order, $grafolog, (bool) ($data['rekomendasi'] ?? false));

        return response()->json($this->present($order->fresh(['product', 'deliveries', 'sample', 'grafolog'])));
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
            'image_count' => $order->sample?->images()->count() ?? 0,
            'report_id' => $report?->id,
            'delivery_mode' => $order->delivery_mode,
            'scheduled_for' => $order->scheduled_for?->toDateString(),
            'upload_deadline' => $order->upload_deadline?->toDateString(),
            'location_note' => $order->location_note,
            'grafolog_choice' => $order->grafolog_choice,
            'grafolog_state' => $order->grafolog_state,
            'grafolog_deadline' => $order->grafolog_deadline,
            'grafolog_attempts' => $order->grafolog_attempts,
            'grafolog' => $order->grafolog_id ? $order->grafolog?->only(['id', 'name', 'city']) : null,
            'grafolog_history' => $order->grafologRequests()->with('grafolog:id,name')->get()->map(fn ($r) => ['grafolog_id' => $r->grafolog_id, 'grafolog' => $r->grafolog?->name, 'status' => $r->status, 'at' => $r->responded_at ?? $r->created_at])->values(),
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
