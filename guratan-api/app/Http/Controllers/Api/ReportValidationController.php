<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalityReport;
use App\Models\ReportValidation;
use App\Models\User;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supervisi laporan antar-grafolog (2026-10-18). Pembuat laporan: terbitkan sendiri / tugaskan
 * validator + tenggat. Validator: setujui / minta revisi / tolak penugasan. Pembuat: konfirmasi
 * hasil (token cair). Lihat ReportValidationService.
 */
class ReportValidationController extends Controller
{
    public function __construct(private ReportValidationService $validation) {}

    public function fee(Request $request): JsonResponse
    {
        return response()->json([
            'tokens' => $this->validation->fee(),
            'balance' => $request->user()->token_balance,
            'max_days' => ReportValidationService::MAX_DEADLINE_DAYS,
            'auto_confirm_days' => ReportValidationService::AUTO_CONFIRM_DAYS,
        ]);
    }

    public function validators(Request $request): JsonResponse
    {
        return response()->json($this->validation->validatorsFor($request->user()));
    }

    /** Daftar tugas supervisi saya (assigned) dan penugasan yang saya buat (requested). */
    public function index(Request $request): JsonResponse
    {
        $this->validation->sweep();
        $user = $request->user();
        $with = ['report:id,sample_id,tier,review_status', 'report.sample:id,user_id', 'report.sample.user:id,name',
            'requester:id,name', 'validator:id,name'];

        return response()->json([
            'assigned' => ReportValidation::where('validator_id', $user->id)->with($with)->latest('id')->limit(100)->get(),
            'requested' => ReportValidation::where('requester_id', $user->id)->with($with)->latest('id')->limit(100)->get(),
        ]);
    }

    /** Riwayat supervisi satu laporan (pembuat, validator, atau admin). */
    public function forReport(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->validation->sweep();
        $user = $request->user();
        abort_unless($user->isAdministrator() || $report->sample->isScorableBy($user) || $report->isValidatedBy($user), 403);

        return response()->json($report->validations()->with(['requester:id,name', 'validator:id,name'])->latest('id')->get());
    }

    public function publish(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeScorer($request, $report);

        return response()->json($this->validation->publish($report, $request->user(), $request->ip())->fresh());
    }

    public function request(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeScorer($request, $report);
        $data = $request->validate([
            'validator_id' => ['required', 'integer', 'exists:users,id'],
            'deadline_days' => ['required', 'integer', 'min:1', 'max:'.ReportValidationService::MAX_DEADLINE_DAYS],
        ]);
        $v = $this->validation->request($report, $request->user(), User::findOrFail($data['validator_id']), $data['deadline_days'], $request->ip());

        return response()->json($v->load(['validator:id,name']), 201);
    }

    public function decide(Request $request, ReportValidation $validation): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,request_revision'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($this->validation->decide($validation, $request->user(), $data['decision'], $data['note'] ?? null, $request->ip())->fresh());
    }

    public function decline(Request $request, ReportValidation $validation): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        return response()->json($this->validation->decline($validation, $request->user(), $note, $request->ip())->fresh());
    }

    public function confirm(Request $request, ReportValidation $validation): JsonResponse
    {
        return response()->json($this->validation->confirm($validation, $request->user(), $request->ip())->fresh());
    }

    private function authorizeScorer(Request $request, PersonalityReport $report): void
    {
        $user = $request->user();
        abort_unless($user->isGrafolog() && $report->sample->isScorableBy($user), 403, 'Anda bukan grafolog yang menangani laporan ini.');
    }
}
