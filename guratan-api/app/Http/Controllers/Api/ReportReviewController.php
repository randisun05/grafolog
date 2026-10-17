<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ReviewReportRequest;
use App\Models\AuditLog;
use App\Models\PersonalityReport;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validasi laporan oleh Supervisor (administrator juga boleh). Sejak Fase D (2026-10-17)
 * validasi OPSIONAL: hanya laporan yang grafolognya meminta validasi (menunggu_review) masuk
 * antrean. Keputusan pertama mentransfer biaya token validasi ke peninjau
 * (ReportValidationService::payReviewer). Koreksi grafolog mengembalikan laporan ke antrean.
 */
class ReportReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:menunggu_review,perlu_revisi,disetujui']]);
        $status = $request->query('status', PersonalityReport::REVIEW_PENDING);

        $reports = PersonalityReport::query()
            ->where('status', 'completed')
            ->where('review_status', $status)
            // Tab "disetujui" hanya laporan yang memang divalidasi supervisor, bukan terbit mandiri.
            ->where('validation', PersonalityReport::VALIDATION_SUPERVISOR)
            ->with([
                'sample:id,user_id,created_by,tier',
                'sample.user:id,name,email',
                'sample.creator:id,name,role',
                'sample.assignment.grafolog:id,name',
                'reviewer:id,name',
            ])
            ->oldest('generated_at')
            ->paginate(25);

        return response()->json($reports);
    }

    public function store(ReviewReportRequest $request, PersonalityReport $report, ReportValidationService $validation): JsonResponse
    {
        abort_unless($report->status === 'completed', 422, 'Laporan belum selesai dibuat.');
        abort_if($report->isApproved(), 422, 'Laporan ini sudah disetujui.');
        abort_unless($report->validation === PersonalityReport::VALIDATION_SUPERVISOR, 422, 'Grafolog belum meminta validasi supervisor untuk laporan ini.');

        $approve = $request->validated('decision') === 'approve';

        $report->update([
            'review_status' => $approve ? PersonalityReport::REVIEW_APPROVED : PersonalityReport::REVIEW_REVISION,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->validated('note'),
        ]);

        $validation->payReviewer($report, $request->user());

        AuditLog::record(
            $approve ? 'review_laporan_disetujui' : 'review_laporan_minta_revisi',
            PersonalityReport::class,
            $report->id,
            $request->user()->id,
            $request->ip(),
        );

        return response()->json($report->fresh('reviewer:id,name'));
    }
}
