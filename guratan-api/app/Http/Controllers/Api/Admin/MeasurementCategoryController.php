<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMeasurementCategoryRequest;
use App\Http\Requests\Admin\UpdateMeasurementCategoryRequest;
use App\Models\AuditLog;
use App\Models\MeasurementCategory;
use App\Models\MeasurementVariable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. KM-B (2026-08-08). Kategori
 * selalu dibuat di bawah 1 variabel (nested route) - tidak ada index()
 * terpisah, daftarnya sudah ikut ke-eager-load di
 * MeasurementVariableController::index().
 */
class MeasurementCategoryController extends Controller
{
    public function store(StoreMeasurementCategoryRequest $request, MeasurementVariable $measurementVariable): JsonResponse
    {
        $category = $measurementVariable->kategori()->create($request->validated());

        AuditLog::record('buat_kategori_ukur', MeasurementCategory::class, $category->id, $request->user()->id, $request->ip());

        return response()->json($category, 201);
    }

    public function update(UpdateMeasurementCategoryRequest $request, MeasurementCategory $measurementCategory): JsonResponse
    {
        $measurementCategory->update($request->validated());

        AuditLog::record('ubah_kategori_ukur', MeasurementCategory::class, $measurementCategory->id, $request->user()->id, $request->ip());

        return response()->json($measurementCategory);
    }

    public function destroy(Request $request, MeasurementCategory $measurementCategory): JsonResponse
    {
        $id = $measurementCategory->id;
        $measurementCategory->delete();

        AuditLog::record('hapus_kategori_ukur', MeasurementCategory::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Kategori ukur dihapus.']);
    }
}
