<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\TokenPurchase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Gated by 'role:administrator'. Dashboard analitik admin, 6 method aksi
 * independen (bukan satu index() raksasa) - meniru Admin\ConceptMapController
 * (KM-H), tiap section dashboard di frontend fetch method-nya sendiri-
 * sendiri supaya loading/error state per-section, bukan satu blok besar.
 * Volume data di app ini kecil (puluhan-ratusan baris per fixture) -
 * agregasi SUM/COUNT/GROUP BY langsung lewat Eloquent, pengelompokan per
 * periode dilakukan DI PHP (bukan SQL DATE_FORMAT/strftime) supaya tidak
 * perlu percabangan driver-aware MySQL-vs-sqlite lagi - lihat
 * guratan-api/CLAUDE.md "Laporan/Rekap admin — Fase 3".
 */
class AnalyticsController extends Controller
{
    public function revenue(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $periodKey = $this->periodKeyFn($request);

        $payments = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->with('sample:id,tier')
            ->get();
        $tokenPurchases = TokenPurchase::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        $reportByPeriod = $payments->groupBy(fn (Payment $p) => $periodKey($p->paid_at))->map->sum('amount');
        $tokenByPeriod = $tokenPurchases->groupBy(fn (TokenPurchase $p) => $periodKey($p->paid_at))->map->sum('amount');

        $periods = $reportByPeriod->keys()->merge($tokenByPeriod->keys())->unique()->sort()->values();
        $series = $periods->map(fn (string $period) => [
            'period' => $period,
            'report_revenue' => (int) ($reportByPeriod[$period] ?? 0),
            'token_revenue' => (int) ($tokenByPeriod[$period] ?? 0),
            'total' => (int) ($reportByPeriod[$period] ?? 0) + (int) ($tokenByPeriod[$period] ?? 0),
        ])->values();

        $revenueByTier = $payments->groupBy(fn (Payment $p) => $p->sample?->tier ?? 'lainnya')->map->sum('amount');

        return response()->json([
            'series' => $series,
            'total_revenue' => $payments->sum('amount') + $tokenPurchases->sum('amount'),
            'report_revenue' => $payments->sum('amount'),
            'token_revenue' => $tokenPurchases->sum('amount'),
            'revenue_by_tier' => $revenueByTier,
        ]);
    }

    public function productUsage(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $samples = HandwritingSample::whereBetween('created_at', [$from, $to])
            ->with('project:id,source')
            ->get();

        return response()->json([
            'total_samples' => $samples->count(),
            'by_tier' => $samples->groupBy('tier')->map->count(),
            'by_status' => $samples->groupBy('status')->map->count(),
            'by_source' => $samples->groupBy(fn (HandwritingSample $s) => $s->project?->source ?? 'lainnya')->map->count(),
        ]);
    }

    public function userGrowth(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $periodKey = $this->periodKeyFn($request);

        $users = User::whereBetween('created_at', [$from, $to])->get(['id', 'role', 'created_at']);

        $series = $users->groupBy(fn (User $u) => $periodKey($u->created_at))
            ->map(fn ($group, $period) => [
                'period' => $period,
                'total' => $group->count(),
                'by_role' => $group->groupBy('role')->map->count(),
            ])
            ->sortBy('period')
            ->values();

        return response()->json([
            'series' => $series,
            'total_new_users' => $users->count(),
            'by_role' => $users->groupBy('role')->map->count(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(90)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function periodKeyFn(Request $request): \Closure
    {
        $groupBy = $request->input('group_by', 'month');

        return fn (Carbon $date) => match ($groupBy) {
            'day' => $date->format('Y-m-d'),
            'week' => $date->clone()->startOfWeek()->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }
}
