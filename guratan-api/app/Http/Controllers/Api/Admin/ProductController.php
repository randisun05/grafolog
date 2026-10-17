<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\StoreProductComponentRequest;
use App\Http\Requests\Commerce\StoreProductRequest;
use App\Models\AuditLog;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Models\ProductComponent;
use Database\Seeders\ProductSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gated 'role:administrator'. Kelola Produk = dasar harga (PUT /admin/pricing/{kode}) dan diskon
 * (applicable_tiers berisi kode produk). Produk yang sudah punya pesanan tidak bisa dihapus -
 * nonaktifkan saja, supaya riwayat pesanan tetap utuh.
 */
class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Product::with('components')->withCount('orders')->orderBy('sort')->get()
                ->map(fn (Product $p) => $p->toArray() + [
                    'price' => $p->currentPrice(),
                    'price_history' => PricingPlan::where('tier', $p->kode)->latest()->limit(10)->get(['price', 'is_active', 'created_at']),
                ])
        );
    }

    public function templates(): JsonResponse
    {
        return response()->json(ProductSeeder::TEMPLATES);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create(collect($data)->except('price')->all() + ['created_by' => $request->user()->id]);
            PricingPlan::setPriceFor($product->kode, $data['price'], $request->user());

            return $product;
        });
        AuditLog::record('buat_produk', Product::class, $product->id, $request->user()->id, $request->ip());

        return response()->json($product->load('components'), 201);
    }

    public function update(StoreProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        abort_if(isset($data['kode']) && $data['kode'] !== $product->kode && $product->orders()->exists(), 422,
            'Kode produk tidak bisa diubah setelah ada pesanan.');

        DB::transaction(function () use ($product, $data) {
            if (isset($data['kode']) && $data['kode'] !== $product->kode) {
                PricingPlan::where('tier', $product->kode)->update(['tier' => $data['kode']]);
            }
            $product->update($data);
        });
        AuditLog::record('ubah_produk', Product::class, $product->id, $request->user()->id, $request->ip());

        return response()->json($product->fresh('components'));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_if($product->orders()->exists(), 422, 'Produk sudah punya pesanan - nonaktifkan saja.');
        DB::transaction(function () use ($product) {
            PricingPlan::where('tier', $product->kode)->delete();
            $product->delete();
        });
        AuditLog::record('hapus_produk', Product::class, $product->id, $request->user()->id, $request->ip());

        return response()->json(null, 204);
    }

    public function storeComponent(StoreProductComponentRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $data['report_fields'] ??= ProductSeeder::TEMPLATES[$data['tipe']] ?? [];
        $component = $product->components()->create($data);
        AuditLog::record('buat_komponen_produk', ProductComponent::class, $component->id, $request->user()->id, $request->ip());

        return response()->json($component, 201);
    }

    public function updateComponent(StoreProductComponentRequest $request, ProductComponent $component): JsonResponse
    {
        $component->update($request->validated());
        AuditLog::record('ubah_komponen_produk', ProductComponent::class, $component->id, $request->user()->id, $request->ip());

        return response()->json($component);
    }

    public function destroyComponent(Request $request, ProductComponent $component): JsonResponse
    {
        abort_if($component->product->orders()->exists(), 422, 'Komponen produk yang sudah dipesan tidak bisa dihapus.');
        $component->delete();
        AuditLog::record('hapus_komponen_produk', ProductComponent::class, $component->id, $request->user()->id, $request->ip());

        return response()->json(null, 204);
    }
}
