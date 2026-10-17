<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:administrator'. CRUD produk afiliasi + jumlah klik. */
class AffiliateAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AffiliateProduct::withCount('clicks')->orderBy('sort')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $product = AffiliateProduct::create($this->validated($request, true) + ['created_by' => $request->user()->id]);
        AuditLog::record('buat_produk_afiliasi', AffiliateProduct::class, $product->id, $request->user()->id, $request->ip());

        return response()->json($product, 201);
    }

    public function update(Request $request, AffiliateProduct $product): JsonResponse
    {
        $product->update($this->validated($request, false));
        AuditLog::record('ubah_produk_afiliasi', AffiliateProduct::class, $product->id, $request->user()->id, $request->ip());

        return response()->json($product->loadCount('clicks'));
    }

    private function validated(Request $request, bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return $request->validate([
            'nama' => [$req, 'string', 'max:255'],
            'mitra' => [$req, 'string', 'max:255'],
            'ringkasan' => ['sometimes', 'nullable', 'string', 'max:500'],
            'url' => [$req, 'url:https', 'max:255'],
            'kategori' => ['sometimes', 'string', 'max:50'],
            'target' => ['sometimes', 'in:semua,b2c,b2b'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
