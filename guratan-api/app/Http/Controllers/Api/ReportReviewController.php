<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ReviewReportRequest;
use App\Models\AuditLog;
use App\Models\PersonalityReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QA laporan oleh Supervisor (administrator juga boleh): laporan selesai
 * berstatus menunggu_review dan baru terlihat klien/HR setelah disetujui.
 * Minta revisi mengembalikan catatan ke grafolog; setiap koreksi/edit
 * grafolog otomatis mengembalikan laporan ke antrean (sendBackToReview).
 */
class ReportReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:menunggu_review,perlu_revisi,disetujui']]);

        $reports = PersonalityReport::query()
            ->where('status', 'completed')
            ->where('review_status', $request->query('status', PersonalityReport::REVIEW_PENDING))
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

    public function store(ReviewReportRequest $request, PersonalityReport $report): JsonResponse
    {
        abort_unless($report->status === 'completed', 422, 'Laporan belum selesai dibuat.');
        abort_if($report->isApproved(), 422, 'Laporan ini sudah disetujui.');

        $approve = $request->validated('decision') === 'approve';

        $report->update([
            'review_status' => $approve ? PersonalityReport::REVIEW_APPROVED : PersonalityReport::REVIEW_REVISION,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->validated('note'),
        ]);

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
