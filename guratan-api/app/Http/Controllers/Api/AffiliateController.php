<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateClick;
use App\Models\AffiliateProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Produk mitra eksternal (afiliasi, Fase E). Publik; target menyesuaikan role bila login. */
class AffiliateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $targets = match (true) {
            $user?->isHr() => ['semua', 'b2b'],
            $user === null, $user->role === 'user' => ['semua', 'b2c'],
            default => ['semua', 'b2c', 'b2b'],
        };

        return response()->json(
            AffiliateProduct::where('is_active', true)->whereIn('target', $targets)
                ->orderBy('sort')->orderBy('id')
                ->get(['id', 'nama', 'mitra', 'ringkasan', 'image_url', 'harga', 'kategori', 'target'])
        );
    }

    /** Catat klik lalu kembalikan URL tujuan (frontend membuka di tab baru). */
    public function click(Request $request, AffiliateProduct $product): JsonResponse
    {
        abort_unless($product->is_active, 404);
        AffiliateClick::create(['affiliate_product_id' => $product->id, 'user_id' => $request->user('sanctum')?->id]);

        return response()->json(['url' => $product->url]);
    }
}
