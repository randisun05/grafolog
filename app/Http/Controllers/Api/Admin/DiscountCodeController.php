<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountCodeRequest;
use App\Models\AuditLog;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. Kode diskon adalah data
 * bisnis sensitif (mempengaruhi pendapatan) - setiap buat/nonaktifkan
 * tercatat di audit_logs, sama seperti perubahan harga di PricingController.
 */
class DiscountCodeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DiscountCode::latest()->paginate(20));
    }

    public function store(StoreDiscountCodeRequest $request): JsonResponse
    {
        $discount = DiscountCode::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('buat_kode_diskon', DiscountCode::class, $discount->id, $request->user()->id, $request->ip());

        return response()->json($discount, 201);
    }

    /**
     * Cuma toggle is_active - edit field lain (nilai, kuota, dst) sengaja
     * tidak didukung supaya kode yang sudah pernah dipakai tidak berubah
     * arti secara diam-diam. Butuh perilaku beda? Nonaktifkan, buat kode baru.
     */
    public function update(Request $request, DiscountCode $discountCode): JsonResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);

        $discountCode->update(['is_active' => $request->boolean('is_active')]);

        AuditLog::record(
            $request->boolean('is_active') ? 'aktifkan_kode_diskon' : 'nonaktifkan_kode_diskon',
            DiscountCode::class,
            $discountCode->id,
            $request->user()->id,
            $request->ip()
        );

        return response()->json($discountCode);
    }
}
