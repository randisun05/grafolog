<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * Publik (tanpa login), sama pola PricingController/ContentController -
 * dipakai halaman harga/marketing/form pesan sebelum login. Cuma produk
 * AKTIF (beda dari Admin\ProductController::index() yang menyertakan
 * nonaktif juga untuk keperluan admin).
 */
class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'description', 'sort_order']);

        return response()->json($products);
    }
}
