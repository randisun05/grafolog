<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Gated by 'role:administrator' on its routes (routes/api.php). Company must
 * exist before an HR account can be created for it - see
 * StoreStaffUserRequest's company_id rule.
 */
class CompanyController extends Controller
{
    /**
     * Dashboard admin lintas-perusahaan (ROADMAP.md "Kesiapan Publikasi" -
     * sebelumnya admin cuma lihat nama+status, tidak ada ringkasan aktivitas
     * sama sekali). Tidak ada company_id langsung di Project/HandwritingSample
     * - rantainya company -> user(role=hr) -> Project.created_by ->
     * HandwritingSample, sama seperti yang dipakai HrCandidatesView/
     * DashboardController::hrDashboard() per HR individual, di sini
     * digabung per company (bisa lebih dari 1 akun hr per company).
     */
    public function index(): JsonResponse
    {
        $companies = Company::query()->latest()->paginate(20);

        $companies->getCollection()->transform(function (Company $company) {
            $hrIds = User::where('company_id', $company->id)->where('role', 'hr')->pluck('id');
            $sampleIds = HandwritingSample::whereHas('project', fn ($q) => $q->whereIn('created_by', $hrIds))
                ->pluck('id');

            $company->hr_count = $hrIds->count();
            $company->total_candidates = $sampleIds->count();
            $company->completed_reports = PersonalityReport::whereIn('sample_id', $sampleIds)
                ->where('status', 'completed')->count();
            $company->avg_turnaround_days = $this->avgTurnaroundDays($sampleIds);

            return $company;
        });

        return response()->json($companies);
    }

    /**
     * Duplikasi kecil dari DashboardController::avgTurnaroundDays() (private
     * di sana, beda konteks agregasi - per company di sini, per user di
     * sana) - diekstrak jadi helper bersama akan bikin coupling Admin\*
     * controller ke controller non-admin untuk logika yang genuinely cuma
     * ~10 baris, tidak sepadan.
     */
    private function avgTurnaroundDays(Collection $sampleIds): ?float
    {
        $reports = PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('status', 'completed')
            ->whereNotNull('generated_at')
            ->with('sample:id,created_at')
            ->get();

        if ($reports->isEmpty()) {
            return null;
        }

        $totalDays = $reports->sum(
            fn (PersonalityReport $report) => $report->sample->created_at->diffInHours($report->generated_at) / 24
        );

        return round($totalDays / $reports->count(), 1);
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
