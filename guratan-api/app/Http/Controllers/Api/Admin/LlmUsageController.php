<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmUsage;
use App\Services\Llm\LlmUsageRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:administrator'. Pemantauan pemakaian & biaya AI (perkiraan dari token x harga config/services.php). */
class LlmUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:365'], 'month' => ['nullable', 'boolean']]);
        $from = $request->boolean('month') ? now()->startOfMonth() : now()->subDays((int) ($f['days'] ?? 30) - 1)->startOfDay();
        $rate = (float) config('services.llm_cost.usd_idr');
        $idr = fn (float $usd) => (int) round($usd * $rate);

        $rows = LlmUsage::where('created_at', '>=', $from)->get();
        $ok = $rows->where('status', 'ok');
        $group = fn ($items, string $key) => $items->groupBy($key)->map(fn ($g, $k) => [
            $key => $k,
            'calls' => $g->where('status', 'ok')->count(),
            'failed' => $g->where('status', 'gagal')->count(),
            'cached' => $g->where('status', 'cache')->count(),
            'tokens' => (int) $g->sum(fn ($r) => $r->prompt_tokens + $r->output_tokens),
            'cost_idr' => $idr($g->sum('cost_usd')),
        ])->sortByDesc('cost_idr')->values();

        $perDay = $rows->groupBy(fn ($r) => $r->created_at->format('Y-m-d'));
        $days = collect();
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $g = $perDay[$d->format('Y-m-d')] ?? collect();
            $days->push(['date' => $d->format('Y-m-d'), 'calls' => $g->where('status', 'ok')->count(), 'failed' => $g->where('status', 'gagal')->count(), 'cost_idr' => $idr($g->sum('cost_usd'))]);
        }

        $budget = config('services.llm_cost.monthly_budget_idr');
        $spent = LlmUsageRecorder::monthSpendIdr();
        $dayOfMonth = max(1, now()->day);

        return response()->json([
            'from' => $from->toDateString(),
            'summary' => [
                'calls' => $ok->count(),
                'failed' => $rows->where('status', 'gagal')->count(),
                'cached' => $rows->where('status', 'cache')->count(),
                'prompt_tokens' => (int) $ok->sum('prompt_tokens'),
                'output_tokens' => (int) $ok->sum('output_tokens'),
                'cost_usd' => round($ok->sum('cost_usd'), 4),
                'cost_idr' => $idr($ok->sum('cost_usd')),
                'avg_latency_ms' => $ok->count() ? (int) round($ok->avg('latency_ms')) : null,
                'avg_cost_idr_per_call' => $ok->count() ? (int) round($idr($ok->sum('cost_usd')) / $ok->count()) : null,
            ],
            'budget' => [
                'monthly_idr' => $budget,
                'spent_idr' => (int) $spent,
                'percent' => $budget ? round($spent / $budget * 100, 1) : null,
                'projected_idr' => (int) round($spent / $dayOfMonth * now()->daysInMonth),
            ],
            'by_purpose' => $group($rows, 'purpose'),
            'by_model' => $group($rows, 'model'),
            'by_key' => $group($rows->whereNotNull('key_slot'), 'key_slot'),
            'by_day' => $days,
            'recent' => LlmUsage::latest('id')->limit(40)->get()->map(fn (LlmUsage $r) => [
                'at' => $r->created_at, 'purpose' => $r->purpose, 'model' => $r->model, 'status' => $r->status, 'key_slot' => $r->key_slot,
                'http_status' => $r->http_status, 'error' => $r->error, 'tokens' => $r->prompt_tokens + $r->output_tokens,
                'cost_idr' => round($r->cost_usd * $rate, 1), 'latency_ms' => $r->latency_ms,
            ]),
            'config' => [
                'provider' => config('services.llm.provider', 'none'), 'model' => config('services.gemini.model'),
                'usd_idr' => $rate, 'price_input_usd_per_m' => config('services.llm_cost.default_pricing.input'),
                'price_output_usd_per_m' => config('services.llm_cost.default_pricing.output'),
                'keys_configured' => collect(config('services.gemini.keys'))->filter(fn ($k) => filled($k))->count(),
            ],
        ]);
    }
}
