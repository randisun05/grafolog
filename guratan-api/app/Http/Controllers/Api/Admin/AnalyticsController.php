<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PersonalityReport;
use App\Models\TokenLedgerEntry;
use App\Models\TokenPrice;
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
     * Sengaja terpisah dari GrafologRecapController (Fase 1) - beda
     * kebutuhan UI, tabel penuh+export CSV vs ringkasan siap-chart dalam
     * satu rentang tanggal, tapi keduanya pakai
     * PersonalityReport::avgTurnaroundDaysFor() yang sama.
     */
    public function grafologPerformance(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $data = User::where('role', 'grafolog')->get(['id', 'name'])
            ->map(function (User $grafolog) use ($from, $to) {
                $sampleIds = HandwritingSample::where('created_by', $grafolog->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->pluck('id');

                return [
                    'grafolog' => $grafolog->name,
                    'completed_reports' => PersonalityReport::whereIn('sample_id', $sampleIds)
                        ->where('status', 'completed')->count(),
                    'avg_turnaround_days' => PersonalityReport::avgTurnaroundDaysFor($sampleIds),
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }

    public function tokenEconomy(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $periodKey = $this->periodKeyFn($request);

        $tokenPurchases = TokenPurchase::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->get();
        $consumption = TokenLedgerEntry::where('type', 'consumption')->whereBetween('created_at', [$from, $to])->get();

        $soldByPeriod = $tokenPurchases->groupBy(fn (TokenPurchase $p) => $periodKey($p->paid_at))->map->sum('quantity');
        $consumedByPeriod = $consumption->groupBy(fn (TokenLedgerEntry $e) => $periodKey($e->created_at))
            ->map(fn ($group) => abs($group->sum('delta')));

        $periods = $soldByPeriod->keys()->merge($consumedByPeriod->keys())->unique()->sort()->values();
        $series = $periods->map(fn (string $period) => [
            'period' => $period,
            'tokens_sold' => (int) ($soldByPeriod[$period] ?? 0),
            'tokens_consumed' => (int) ($consumedByPeriod[$period] ?? 0),
        ])->values();

        return response()->json([
            'series' => $series,
            'tokens_sold' => $tokenPurchases->sum('quantity'),
            'token_revenue' => $tokenPurchases->sum('amount'),
            'tokens_consumed' => (int) abs($consumption->sum('delta')),
            'outstanding_grafolog_balance' => User::where('role', 'grafolog')->sum('token_balance'),
            'current_price_per_token' => TokenPrice::current(),
        ]);
    }

    /**
     * `used_count`/`max_uses` tetap counter seumur-hidup kode (sama yang
     * ditampilkan AdminDiscountsView.vue), TAPI `discount_given`/
     * `revenue_generated` di-scope ke rentang tanggal seperti section
     * lain - jadi baris ini menunjukkan "kode ini secara keseluruhan
     * sudah dipakai N kali, dan di rentang yang dipilih menghasilkan
     * sekian revenue/potongan". `discount_given` digabung dari Payment
     * DAN TokenPurchase per discount_code_id DI PHP (bukan join SQL
     * lintas tabel) - satu kode bisa dipakai di pembelian laporan
     * maupun pembelian token sekaligus.
     */
    public function discountEffectiveness(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $payments = Payment::where('status', 'paid')->whereNotNull('discount_code_id')
            ->whereBetween('paid_at', [$from, $to])->get(['discount_code_id', 'base_amount', 'amount']);
        $tokenPurchases = TokenPurchase::where('status', 'paid')->whereNotNull('discount_code_id')
            ->whereBetween('paid_at', [$from, $to])->get(['discount_code_id', 'base_amount', 'amount']);

        $discountGivenByCode = [];
        $revenueByCode = [];
        foreach ($payments->concat($tokenPurchases) as $tx) {
            $id = $tx->discount_code_id;
            $discountGivenByCode[$id] = ($discountGivenByCode[$id] ?? 0) + ($tx->base_amount - $tx->amount);
            $revenueByCode[$id] = ($revenueByCode[$id] ?? 0) + $tx->amount;
        }

        $data = DiscountCode::all()->map(fn (DiscountCode $code) => [
            'code' => $code->code,
            'used_count' => $code->used_count,
            'max_uses' => $code->max_uses,
            'discount_given' => $discountGivenByCode[$code->id] ?? 0,
            'revenue_generated' => $revenueByCode[$code->id] ?? 0,
        ])->values();

        return response()->json(['data' => $data]);
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
