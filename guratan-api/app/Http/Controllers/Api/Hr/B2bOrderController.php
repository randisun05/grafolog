<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreB2bOrderRequest;
use App\Models\AuditLog;
use App\Models\CandidateProfile;
use App\Models\GrafologAvailability;
use App\Models\Order;
use App\Models\OrderBatch;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Gated 'role:hr'. Pesanan B2B (Fase C, 2026-10-17): HR memilih produk, kandidat, dan grafolog
 * terverifikasi; satu pembayaran per batch. Grafolog ditugaskan otomatis saat lunas.
 */
class B2bOrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function products(): JsonResponse
    {
        return response()->json(
            Product::forCompanies()->with('components')->orderBy('sort')->get()
                ->map(fn (Product $p) => $p->toCatalogArray())
                ->filter(fn ($p) => $p['price'] !== null)
                ->values()
        );
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string'],
            'count' => ['required', 'integer', 'min:1', 'max:200'],
            'discount_code' => ['nullable', 'string', 'max:50'],
        ]);
        $product = Product::forCompanies()->where('kode', $data['product'])->firstOrFail();

        try {
            $quote = $this->orders->quote($product, $data['discount_code'] ?? null);
            $message = null;
        } catch (HttpException $e) {
            $quote = $this->orders->quote($product, null);
            $message = $e->getMessage();
        }

        return response()->json([
            'unit_price' => $quote['base'],
            'unit_final' => $quote['amount'],
            'base_total' => $quote['base'] * $data['count'],
            'final_total' => $quote['amount'] * $data['count'],
            'code_valid' => ($data['discount_code'] ?? null) ? $message === null : null,
            'code_message' => $message,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            OrderBatch::where('company_id', $request->user()->company_id)
                ->with(['product:id,kode,nama', 'grafolog:id,name', 'orders:id,batch_id,user_id,status', 'orders.user:id,name'])
                ->latest()
                ->get()
        );
    }

    public function store(StoreB2bOrderRequest $request): JsonResponse
    {
        $hr = $request->user();
        $data = $request->validated();
        $product = Product::forCompanies()->where('kode', $data['product'])->first();
        abort_unless($product, 422, 'Produk tidak tersedia untuk perusahaan.');

        $grafolog = User::find($data['grafolog_id']);
        abort_unless($grafolog?->isGrafolog(), 422, 'Grafolog yang dipilih tidak terverifikasi.');

        $month = $data['month'] ?? now()->format('Y-m');
        $availability = GrafologAvailability::forMonth($grafolog, $month);
        abort_unless($availability['bookable'], 422, 'Grafolog ini tidak tersedia pada bulan layanan yang dipilih. Pilih grafolog atau bulan lain.');
        abort_if(
            $availability['slots_left'] !== null && count($data['candidate_ids']) > $availability['slots_left'],
            422,
            "Kuota grafolog ini untuk bulan tersebut tinggal {$availability['slots_left']} kandidat.",
        );

        $profiles = CandidateProfile::where('company_id', $hr->company_id)
            ->whereIn('id', $data['candidate_ids'])->with('user')->get();
        abort_unless($profiles->count() === count($data['candidate_ids']), 422, 'Ada kandidat yang tidak ditemukan di perusahaan Anda.');

        // Cegah pesanan ganda: kandidat yang masih punya pesanan aktif untuk produk yang sama.
        $busy = Order::whereIn('user_id', $profiles->pluck('user_id'))
            ->where('product_id', $product->id)
            ->whereIn('status', [Order::STATUS_UNPAID, Order::STATUS_PROCESSING])
            ->whereHas('batch', fn ($q) => $q->where('company_id', $hr->company_id))
            ->with('user:id,name')->get();
        if ($busy->isNotEmpty()) {
            return response()->json([
                'message' => 'Kandidat berikut masih punya pesanan aktif untuk produk ini: '.$busy->pluck('user.name')->unique()->join(', ').'.',
            ], 422);
        }

        $batch = $this->orders->createBatch($hr->company, $hr, $product, $profiles->pluck('user'), $data['discount_code'] ?? null, $grafolog, $month);
        AuditLog::record('pesan_b2b', OrderBatch::class, $batch->id, $hr->id, $request->ip());

        return response()->json($batch->load(['product:id,kode,nama', 'grafolog:id,name', 'orders:id,batch_id,user_id,status']), 201);
    }

    public function pay(Request $request, OrderBatch $batch): JsonResponse
    {
        abort_unless($batch->company_id === $request->user()->company_id, 404);

        try {
            return response()->json($this->orders->startBatchPayment($batch, $request->user(), $request->ip()), 201);
        } catch (RuntimeException $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            Log::error('DOKU checkout pesanan B2B gagal dibuat', ['batch_id' => $batch->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Pembayaran sedang tidak tersedia, coba lagi nanti.'], 503);
        }
    }

    public function cancel(Request $request, OrderBatch $batch): JsonResponse
    {
        abort_unless($batch->company_id === $request->user()->company_id, 404);
        $this->orders->cancelBatch($batch, 'Dibatalkan HR sebelum pembayaran.', $request->user(), $request->ip());

        return response()->json($batch->fresh());
    }
}
