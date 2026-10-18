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
        // Yang boleh diubah setelah dibuat hanya status aktif dan tampil-sebagai-promo (is_public):
        // nilai/kuota/produk tetap beku supaya arti pemakaian lama tidak berubah.
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            // Saluran aktif boleh diubah kapan saja (null/kosong = semua saluran).
            'audiences' => ['sometimes', 'nullable', 'array'],
            'audiences.*' => ['string', 'in:client,grafolog,b2b'],
        ]);
        abort_if($data === [], 422, 'Tidak ada perubahan.');
        if (array_key_exists('audiences', $data)) {
            $data['audiences'] = $data['audiences'] ? array_values(array_unique($data['audiences'])) : null;
        }

        $discountCode->update($data);

        $aksi = match (true) {
            array_key_exists('audiences', $data) => 'ubah_saluran_kode_diskon',
            array_key_exists('is_active', $data) => $data['is_active'] ? 'aktifkan_kode_diskon' : 'nonaktifkan_kode_diskon',
            default => $data['is_public'] ? 'tampilkan_promo' : 'sembunyikan_promo',
        };
        AuditLog::record($aksi, DiscountCode::class, $discountCode->id, $request->user()->id, $request->ip());

        return response()->json($discountCode);
    }
}
