<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:administrator' on its routes (routes/api.php). Lihat
 * guratan-api/CLAUDE.md "Sistem Products data-driven" untuk konteks penuh.
 */
class ProductController extends Controller
{
    /**
     * Tidak dipaginasi (beda dari PricingController/TokenCostController
     * yang menyimpan histori) - ini daftar pendek yang di-scan sekilas,
     * bukan log transaksi. Menyertakan produk NONAKTIF juga (termasuk
     * 'rapid') - admin perlu lihat semuanya dan bisa aktifkan lagi kalau
     * perlu, sama pola tabel Perusahaan yang juga tampilkan baris nonaktif.
     */
    public function index(): JsonResponse
    {
        return response()->json(Product::orderBy('sort_order')->get());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        AuditLog::record('buat_produk', Product::class, $product->id, $request->user()->id, $request->ip());

        return response()->json($product, 201);
    }

    /**
     * `code` immutable - $request->validated() dari UpdateProductRequest
     * sengaja tidak punya rule `code`, jadi field itu diabaikan diam-diam
     * kalau ikut dikirim, bukan error.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        AuditLog::record('ubah_produk', Product::class, $product->id, $request->user()->id, $request->ip());

        return response()->json($product);
    }
}
