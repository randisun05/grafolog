<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(
            $user->isGrafolog() ? $this->grafologDashboard($user) : $this->clientDashboard($user)
        );
    }

    private function grafologDashboard(User $user): array
    {
        $sampleIds = HandwritingSample::where('created_by', $user->id)->pluck('id');

        return [
            'role' => 'grafolog',
            'kpi' => [
                [
                    'key' => 'active_projects',
                    'label' => 'Project Aktif',
                    'value' => Project::where('created_by', $user->id)
                        ->whereHas('samples', fn ($q) => $q->where('status', '!=', 'completed'))
                        ->count(),
                ],
                [
                    'key' => 'pending_review',
                    'label' => 'Menunggu Skor',
                    'value' => HandwritingSample::where('created_by', $user->id)
                        ->where('status', 'pending')->count(),
                ],
                [
                    'key' => 'completed_this_month',
                    'label' => 'Selesai Bulan Ini',
                    'value' => PersonalityReport::whereIn('sample_id', $sampleIds)
                        ->where('status', 'completed')
                        ->whereBetween('generated_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->count(),
                ],
                [
                    'key' => 'avg_turnaround_days',
                    'label' => 'Rata-rata Durasi (hari)',
                    'value' => $this->avgTurnaroundDays($sampleIds),
                ],
                [
                    'key' => 'token_balance',
                    'label' => 'Sisa Token',
                    'value' => $user->token_balance,
                ],
            ],
            'activity' => $this->recentActivity($sampleIds),
        ];
    }

    private function clientDashboard(User $user): array
    {
        $sampleIds = HandwritingSample::where('user_id', $user->id)->pluck('id');
        $completed = HandwritingSample::whereIn('id', $sampleIds)->where('status', 'completed')->count();

        return [
            'role' => 'client',
            'kpi' => [
                ['key' => 'total_assessments', 'label' => 'Total Assessment', 'value' => $sampleIds->count()],
                ['key' => 'completed', 'label' => 'Selesai', 'value' => $completed],
                ['key' => 'in_progress', 'label' => 'Sedang Diproses', 'value' => $sampleIds->count() - $completed],
                [
                    'key' => 'avg_turnaround_days',
                    'label' => 'Rata-rata Durasi (hari)',
                    'value' => $this->avgTurnaroundDays($sampleIds),
                ],
            ],
            'activity' => $this->recentActivity($sampleIds),
        ];
    }

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

    private function recentActivity(Collection $sampleIds): array
    {
        return PersonalityReport::whereIn('sample_id', $sampleIds)
            ->latest('generated_at')
            ->take(5)
            ->get(['id', 'sample_id', 'tier', 'status', 'generated_at'])
            ->map(fn (PersonalityReport $report) => [
                'id' => $report->id,
                'label' => "Laporan #{$report->id} ({$report->tier}) {$this->statusLabel($report->status)}",
                'status' => $report->status,
                'occurred_at' => $report->generated_at,
            ])
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'selesai',
            'generating' => 'sedang diproses',
            'failed' => 'gagal',
            default => $status,
        };
    }
}
