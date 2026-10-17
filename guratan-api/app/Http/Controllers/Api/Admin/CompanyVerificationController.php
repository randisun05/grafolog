<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CompanyVerificationMail;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Gated 'role:administrator'. Verifikasi perusahaan yang mendaftar mandiri. */
class CompanyVerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['sometimes', 'in:pending,verified,rejected']])['status'] ?? 'pending';

        return response()->json(
            Company::where('status', $status)
                ->with(['users' => fn ($q) => $q->where('role', 'hr')->select('id', 'name', 'email', 'phone', 'company_id')])
                ->latest()
                ->get()
        );
    }

    public function decide(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);

        $approve = $data['decision'] === 'approve';
        $company->update([
            'status' => $approve ? Company::STATUS_VERIFIED : Company::STATUS_REJECTED,
            'verified_at' => $approve ? now() : null,
            'verified_by' => $request->user()->id,
            'review_note' => $data['note'] ?? null,
        ]);

        AuditLog::record($approve ? 'verifikasi_perusahaan' : 'tolak_perusahaan', Company::class, $company->id, $request->user()->id, $request->ip());
        foreach ($company->users()->where('role', 'hr')->get() as $hr) {
            Mail::to($hr->email)->queue(new CompanyVerificationMail($company, $hr));
        }

        return response()->json($company->fresh());
    }
}
