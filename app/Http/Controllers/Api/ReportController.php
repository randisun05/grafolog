<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalityReport;
use App\Services\Reporting\ReportPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportPdfService $pdfService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reports = PersonalityReport::query()
            ->whereHas('sample', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('assignment', fn ($a) => $a->where('grafolog_id', $user->id));
            })
            ->with('sample:id,user_id,created_by,tier')
            ->latest()
            ->paginate(20);

        return response()->json($reports);
    }

    public function show(Request $request, PersonalityReport $report): JsonResponse
    {
        $this->authorizeAccess($request, $report);

        return response()->json($report->load('sample', 'aspekScores.aspek'));
    }

    public function pdf(Request $request, PersonalityReport $report): StreamedResponse
    {
        $this->authorizeAccess($request, $report);
        abort_unless($report->status === 'completed', 422, 'Laporan belum selesai dibuat.');

        $path = $this->pdfService->generate($report);

        return Storage::disk('local')->download($path, "laporan-{$report->id}.pdf");
    }

    private function authorizeAccess(Request $request, PersonalityReport $report): void
    {
        abort_unless($report->sample->isViewableBy($request->user()), 403);
    }
}
