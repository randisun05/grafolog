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

        return response()->json(match (true) {
            $user->isGrafolog() => $this->grafologDashboard($user),
            $user->isHr() => $this->hrDashboard($user),
            default => $this->clientDashboard($user),
        });
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
        // "Selesai" dari sudut pandang klien berarti narasi_status='final' (yang
        // benar-benar bisa mereka lihat), BUKAN sample.status='completed' (cuma
        // berarti breakdown internal sudah dihitung) - dua hal itu sengaja beda
        // sejak narasi terpadu wajib direview grafolog dulu sebelum final, lihat
        // guratan-api/CLAUDE.md "Narasi terpadu (laporan klien)".
        $completed = PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('narasi_status', 'final')->count();

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
            'activity' => $this->recentActivity($sampleIds, clientView: true),
        ];
    }

    /**
     * HR sebelumnya jatuh ke clientDashboard() (bukan grafolog -> default
     * branch) - salah total, karena HR TIDAK PERNAH punya sample dengan
     * user_id=dirinya sendiri (HR bukan subjek tes) - KPI-nya selalu 0 dan
     * activity-nya selalu kosong meski HR sudah impor puluhan kandidat.
     * Scoping yang benar (created_by, sama seperti HrCandidatesView.vue
     * pakai lewat GET /api/samples) - lihat SampleController::index.
     */
    private function hrDashboard(User $user): array
    {
        $sampleIds = HandwritingSample::where('created_by', $user->id)->pluck('id');
        $samples = HandwritingSample::whereIn('id', $sampleIds)->get(['id', 'status']);
        $completed = $samples->where('status', 'completed')->count();
        $unassignedActive = HandwritingSample::whereIn('id', $sampleIds)
            ->where('status', '!=', 'completed')
            ->whereDoesntHave('assignment')
            ->count();

        return [
            'role' => 'hr',
            'kpi' => [
                ['key' => 'total_candidates', 'label' => 'Total Kandidat', 'value' => $samples->count()],
                ['key' => 'unassigned', 'label' => 'Menunggu Penugasan', 'value' => $unassignedActive],
                ['key' => 'completed', 'label' => 'Selesai', 'value' => $completed],
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

    /**
     * $clientView=true (klien saja) memakai narasi_status, bukan status,
     * untuk label+badge - klien cuma pernah bisa lihat laporan begitu
     * narasi_status='final' (lihat ReportController::show), jadi status
     * breakdown internal ('completed') menyesatkan buat mereka: sample bisa
     * saja 'completed' sementara narasi masih draft/belum dibuat, klien
     * lihat badge "Selesai" tapi begitu diklik malah 403 "belum final".
     */
    private function recentActivity(Collection $sampleIds, bool $clientView = false): array
    {
        return PersonalityReport::whereIn('sample_id', $sampleIds)
            ->latest('generated_at')
            ->take(5)
            ->get(['id', 'sample_id', 'tier', 'status', 'narasi_status', 'generated_at'])
            ->map(function (PersonalityReport $report) use ($clientView) {
                $status = $clientView ? $this->clientFacingStatus($report->narasi_status) : $report->status;

                return [
                    'id' => $report->id,
                    'label' => "Laporan #{$report->id} ({$report->tier}) {$this->statusLabel($status)}",
                    'status' => $status,
                    'occurred_at' => $report->generated_at,
                ];
            })
            ->all();
    }

    private function clientFacingStatus(string $narasiStatus): string
    {
        return $narasiStatus === 'final' ? 'completed' : 'generating';
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
