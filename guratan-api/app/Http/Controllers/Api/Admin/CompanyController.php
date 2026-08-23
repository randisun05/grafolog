<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:administrator' on its routes (routes/api.php). Company must
 * exist before an HR account can be created for it - see
 * StoreStaffUserRequest's company_id rule.
 */
class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Company::query()->latest()->paginate(20));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create([
            'name' => $request->validated('name'),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('buat_perusahaan', Company::class, $company->id, $request->user()->id, $request->ip());

        return response()->json($company, 201);
    }

    /**
     * Sebelumnya tidak ada UI/endpoint update sama sekali untuk Company -
     * cuma create+list (lihat ROADMAP.md "Kesiapan Publikasi"). is_active
     * BUKAN hard-delete dan SENGAJA tidak mencabut akses akun hr yang sudah
     * terikat ke company ini - menonaktifkan company cuma mencegah company
     * itu dipakai untuk hr BARU (lihat StoreStaffUserRequest's exists check
     * tidak berubah), bukan otomatis menonaktifkan staf yang sudah ada.
     * Kalau perlu mencabut akses hr terkait, lakukan eksplisit lewat
     * AdminUserController::update() per akun.
     */
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());

        AuditLog::record('ubah_perusahaan', Company::class, $company->id, $request->user()->id, $request->ip());

        return response()->json($company);
    }
}
