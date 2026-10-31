<?php

namespace App\Services\Llm;

use App\Models\LlmUsage;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mencatat tiap panggilan AI (token, biaya perkiraan, latensi, status) untuk pemantauan admin.
 * TIDAK menyimpan isi prompt/jawaban. Kegagalan mencatat tidak boleh mengganggu alur utama.
 */
class LlmUsageRecorder
{
    public const PURPOSE_NARASI = 'terjemah_narasi';

    public const PURPOSE_ANALISIS = 'analisis_kandidat';

    public function __construct(private Notifier $notifier) {}

    /** Perkiraan biaya USD dari jumlah token menurut harga per 1 juta token pada config. */
    public static function costUsd(?string $model, int $promptTokens, int $outputTokens): float
    {
        $price = config("services.llm_cost.pricing.{$model}") ?? config('services.llm_cost.default_pricing');

        return round(($promptTokens * $price['input'] + $outputTokens * $price['output']) / 1_000_000, 6);
    }

    public function record(string $purpose, string $status, array $attrs = []): void
    {
        try {
            $prompt = (int) ($attrs['prompt_tokens'] ?? 0);
            $output = (int) ($attrs['output_tokens'] ?? 0);
            $model = $attrs['model'] ?? config('services.gemini.model');
            LlmUsage::create([
                'provider' => $attrs['provider'] ?? config('services.llm.provider', 'gemini'),
                'model' => $model,
                'purpose' => $purpose,
                'status' => $status,
                'key_slot' => $attrs['key_slot'] ?? null,
                'http_status' => $attrs['http_status'] ?? null,
                'error' => isset($attrs['error']) ? mb_substr($attrs['error'], 0, 120) : null,
                'prompt_tokens' => $prompt,
                'output_tokens' => $output,
                'cost_usd' => $status === 'ok' ? self::costUsd($model, $prompt, $output) : 0,
                'latency_ms' => $attrs['latency_ms'] ?? null,
            ]);
            if ($status === 'ok') {
                $this->checkBudget();
            }
        } catch (Throwable $e) {
            Log::warning('Gagal mencatat pemakaian AI', ['error' => $e->getMessage()]);
        }
    }

    /** Biaya bulan berjalan (Rp). */
    public static function monthSpendIdr(): float
    {
        return round((float) LlmUsage::where('created_at', '>=', now()->startOfMonth())->sum('cost_usd') * (float) config('services.llm_cost.usd_idr'));
    }

    /** Peringatan admin sekali per level (80%, 100%) per bulan. */
    private function checkBudget(): void
    {
        $budget = config('services.llm_cost.monthly_budget_idr');
        if (! $budget) {
            return;
        }
        $spent = self::monthSpendIdr();
        foreach ([100, 80] as $level) {
            if ($spent >= $budget * $level / 100) {
                $key = 'llm_budget_alert:'.now()->format('Ym').':'.$level;
                if (Cache::add($key, true, now()->endOfMonth())) {
                    $this->notifier->toAdmins('anggaran_ai', "Biaya AI mencapai {$level}% anggaran bulan ini",
                        'Terpakai Rp '.number_format($spent, 0, ',', '.').' dari anggaran Rp '.number_format($budget, 0, ',', '.').'. Lihat Pemakaian AI.', '/admin/ai-usage');
                }

                return;
            }
        }
    }
}
