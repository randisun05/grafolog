<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\GrafologRequest;
use App\Models\HandwritingSample;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Payment;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\ReportValidation;
use App\Models\User;
use App\Services\Llm\LlmUsageRecorder;
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
            $user->isAdministrator() => $this->adminDashboard(),
            $user->isHr() => $this->hrDashboard($user),
            default => $this->clientDashboard($user),
        });
    }

    private function adminDashboard(): array
    {
        $paid = fn ($q) => $q->where('status', 'paid');
        $monthRange = [now()->startOfMonth(), now()->endOfMonth()];
        $unassigned = HandwritingSample::whereHas('project', fn ($q) => $q->where('source', 'client'))
            ->whereHas('payments', $paid)->doesntHave('assignment')
            ->where('status', '!=', 'completed')->count();

        return [
            'role' => 'administrator',
            'kpi' => [
                [
                    'key' => 'orders_unassigned',
                    'label' => 'Pesanan Perlu Ditugaskan',
                    'value' => $unassigned,
                ],
                [
                    'key' => 'reviews_pending',
                    'label' => 'Supervisi Berjalan',
                    'value' => ReportValidation::whereIn('status', ReportValidation::ACTIVE)->count(),
                ],
                [
                    'key' => 'reports_this_month',
                    'label' => 'Laporan Dirilis Bulan Ini',
                    'value' => PersonalityReport::where('review_status', PersonalityReport::REVIEW_APPROVED)
                        ->whereBetween('reviewed_at', $monthRange)->count(),
                ],
                [
                    'key' => 'revenue_this_month',
                    'label' => 'Pendapatan Bulan Ini (Rp)',
                    'value' => (int) Payment::where('status', 'paid')->whereBetween('paid_at', $monthRange)->sum('amount'),
                ],
                [
                    'key' => 'ai_cost_month',
                    'label' => 'Biaya AI Bulan Ini (Rp)',
                    'value' => (int) LlmUsageRecorder::monthSpendIdr(),
                ],
                [
                    'key' => 'active_grafologs',
                    'label' => 'Grafolog Terdaftar',
                    'value' => User::where('role', 'grafolog')->count(),
                ],
            ],
            // Antrean yang butuh tindakan admin - tampil sebagai tautan langsung di dashboard.
            'actions' => [
                ['label' => 'Pendaftar grafolog menunggu verifikasi', 'count' => User::where('role', 'grafolog')->where('grafolog_status', 'pending')->count(), 'to' => '/admin/verifikasi?tab=grafolog'],
                ['label' => 'Perusahaan menunggu verifikasi', 'count' => Company::where('status', 'pending')->count(), 'to' => '/admin/verifikasi?tab=perusahaan'],
                ['label' => 'Pesanan lunas perlu ditugaskan', 'count' => $unassigned, 'to' => '/admin/orders'],
                ['label' => 'Pesanan > 90 hari belum selesai (kandidat refund)', 'count' => Order::where('status', Order::STATUS_PROCESSING)->where('paid_at', '<', now()->subDays(Order::REFUND_OVERDUE_DAYS))->count(), 'to' => '/admin/orders'],
                ['label' => 'Pesanan klien menunggu rekomendasi grafolog', 'count' => Order::whereNull('batch_id')->where('grafolog_state', 'menunggu_admin')->count(), 'to' => '/admin/orders'],
                ['label' => 'Konfirmasi grafolog melewati 24 jam', 'count' => GrafologRequest::where('status', 'menunggu')->where('expires_at', '<', now())->count(), 'to' => '/admin/orders'],
                ['label' => 'Penugasan supervisi melewati tenggat', 'count' => ReportValidation::where('status', ReportValidation::WAITING)->where('due_at', '<', now())->count(), 'to' => '/admin/validasi'],
            ],
            'activity' => $this->recentActivity(HandwritingSample::pluck('id')),
        ];
    }

    private function hrDashboard(User $user): array
    {
        $samples = HandwritingSample::where('created_by', $user->id);
        $sampleIds = (clone $samples)->pluck('id');

        return [
            'role' => 'hr',
            'kpi' => [
                ['key' => 'total_candidates', 'label' => 'Total Kandidat', 'value' => $sampleIds->count()],
                [
                    'key' => 'unassigned',
                    'label' => 'Belum Ditugaskan',
                    'value' => (clone $samples)->doesntHave('assignment')->where('status', '!=', 'completed')->count(),
                ],
                [
                    'key' => 'in_progress',
                    'label' => 'Sedang Diproses',
                    'value' => $sampleIds->count() - $this->approvedSampleCount($sampleIds)
                        - (clone $samples)->doesntHave('assignment')->where('status', '!=', 'completed')->count(),
                ],
                ['key' => 'completed', 'label' => 'Laporan Siap', 'value' => $this->approvedSampleCount($sampleIds)],
            ],
            'activity' => $this->recentActivity($sampleIds, approvedOnly: true),
        ];
    }

    private function approvedSampleCount(Collection $sampleIds): int
    {
        return PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('review_status', PersonalityReport::REVIEW_APPROVED)
            ->distinct('sample_id')->count('sample_id');
    }

    private function grafologDashboard(User $user): array
    {
        $sampleIds = HandwritingSample::where('created_by', $user->id)
            ->orWhereHas('assignment', fn ($q) => $q->where('grafolog_id', $user->id))
            ->pluck('id');

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
                    'key' => 'requests_waiting',
                    'label' => 'Permintaan Menunggu Konfirmasi',
                    'value' => GrafologRequest::where('grafolog_id', $user->id)->where('status', 'menunggu')->where('expires_at', '>', now())->count(),
                ],
                [
                    'key' => 'pending_review',
                    'label' => 'Menunggu Skor',
                    'value' => HandwritingSample::whereIn('id', $sampleIds)->where('status', 'pending')->count(),
                ],
                [
                    'key' => 'drafts',
                    'label' => 'Laporan Draf (belum terbit)',
                    'value' => PersonalityReport::whereIn('sample_id', $sampleIds)
                        ->where('review_status', PersonalityReport::REVIEW_DRAFT)->count(),
                ],
                [
                    'key' => 'needs_revision',
                    'label' => 'Perlu Revisi',
                    'value' => PersonalityReport::whereIn('sample_id', $sampleIds)
                        ->where('review_status', PersonalityReport::REVIEW_REVISION)->count(),
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
        $completed = $this->approvedSampleCount($sampleIds);

        return [
            'role' => 'client',
            'kpi' => [
                ['key' => 'total_assessments', 'label' => 'Total Assessment', 'value' => $sampleIds->count()],
                ['key' => 'unread_chat', 'label' => 'Pesan Belum Dibaca', 'value' => OrderMessage::whereNull('read_at')->where('sender_id', '!=', $user->id)->whereHas('order', fn ($q) => $q->where('user_id', $user->id))->count()],
                ['key' => 'completed', 'label' => 'Selesai', 'value' => $completed],
                ['key' => 'in_progress', 'label' => 'Sedang Diproses', 'value' => $sampleIds->count() - $completed],
                [
                    'key' => 'avg_turnaround_days',
                    'label' => 'Rata-rata Durasi (hari)',
                    'value' => $this->avgTurnaroundDays($sampleIds),
                ],
            ],
            'activity' => $this->recentActivity($sampleIds, approvedOnly: true),
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

    private function recentActivity(Collection $sampleIds, bool $approvedOnly = false): array
    {
        return PersonalityReport::whereIn('sample_id', $sampleIds)
            ->when($approvedOnly, fn ($q) => $q->where('review_status', PersonalityReport::REVIEW_APPROVED))
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
