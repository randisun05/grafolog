<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyContractRequest;
use App\Http\Requests\Admin\UpdateCompanyContractRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. B2B Fase 3 (ROADMAP.md
 * "Kesiapan Publikasi") - kontrak custom sales-led, record-only, TIDAK
 * menghitung tagihan atau menyentuh payment gate/flow apa pun yang ada.
 * Sama pola nested-route dengan MeasurementCategoryController: tidak ada
 * index() terpisah, daftar kontrak ikut eager-load di
 * CompanyController::index() (`with('contracts')`).
 */
class CompanyContractController extends Controller
{
    public function store(StoreCompanyContractRequest $request, Company $company): JsonResponse
    {
        $contract = $company->contracts()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('buat_kontrak_b2b', CompanyContract::class, $contract->id, $request->user()->id, $request->ip());

        return response()->json($contract, 201);
    }

    public function update(UpdateCompanyContractRequest $request, CompanyContract $companyContract): JsonResponse
    {
        $companyContract->update($request->validated());

        AuditLog::record('ubah_kontrak_b2b', CompanyContract::class, $companyContract->id, $request->user()->id, $request->ip());

        return response()->json($companyContract);
    }

    public function destroy(Request $request, CompanyContract $companyContract): JsonResponse
    {
        $id = $companyContract->id;
        $companyContract->delete();

        AuditLog::record('hapus_kontrak_b2b', CompanyContract::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Kontrak dihapus.']);
    }
}
