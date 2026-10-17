<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran mandiri perusahaan B2B (2026-10-17). Membuat Company `pending` + akun HR pertamanya.
 * HR bisa login untuk melihat status, tetapi semua rute `role:hr` ditolak (User::isPendingHr())
 * sampai admin memverifikasi perusahaan.
 */
class CompanyRegistrationController extends Controller
{
    public function store(RegisterCompanyRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'status' => Company::STATUS_PENDING,
                'industry' => $data['industry'],
                'employee_range' => $data['employee_range'],
                'phone' => $data['company_phone'],
                'address' => $data['address'],
                'website' => $data['website'] ?? null,
            ]);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => 'hr',
                'company_id' => $company->id,
                'consented_at' => now(),
                'consent_source' => 'self',
                'consent_version' => User::CONSENT_VERSION,
            ]);
            $company->update(['created_by' => $user->id]);

            return $user;
        });

        AuditLog::record('daftar_perusahaan', Company::class, $user->company_id, $user->id, $request->ip());

        return response()->json([
            'user' => $user->fresh()->load('company'),
            'token' => $user->createToken('guratan-web')->plainTextToken,
        ], 201);
    }
}
