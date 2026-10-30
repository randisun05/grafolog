<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Publik (tanpa login): katalog produk B2C dan promo untuk beranda & halaman klien. */
class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Product::forClients()->with('components')->orderByDesc('is_featured')->orderBy('sort')->get()
                ->map->toCatalogArray()->values()
        );
    }

    /** Detail satu produk untuk halaman katalog publik. */
    public function show(string $kode): JsonResponse
    {
        $product = Product::forClients()->with('components')->where('kode', $kode)->firstOrFail();

        return response()->json($product->toCatalogArray());
    }

    /** Promo aktif: kode diskon publik yang berlaku sekarang + produk unggulan. */
    public function promos(Request $request): JsonResponse
    {
        $audience = in_array($request->query('audience'), DiscountCode::AUDIENCES, true) ? $request->query('audience') : 'client';
        $codes = DiscountCode::where('is_public', true)->get()
            ->filter(fn (DiscountCode $c) => $c->isCurrentlyUsable() && $c->appliesToAudience($audience))
            ->map(fn (DiscountCode $c) => [
                'code' => $c->code, 'description' => $c->description, 'type' => $c->type, 'value' => $c->value,
                'products' => $c->applicable_tiers, 'payment_methods' => $c->payment_methods, 'valid_until' => $c->valid_until,
            ])->values();

        $featured = Product::forClients()->where('is_featured', true)->with('components')->orderBy('sort')->get()
            ->map->toCatalogArray()->values();

        return response()->json(['codes' => $codes, 'products' => $featured]);
    }
}
