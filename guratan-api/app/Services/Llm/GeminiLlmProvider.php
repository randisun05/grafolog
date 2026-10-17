<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider Google Gemini (generateContent) dengan rotasi sampai 10 API key.
 * Sama seperti ApiLlmProvider: hanya mengirim teks generik knowledge base
 * (per aspek per level), TIDAK PERNAH data psikologis milik user.
 *
 * Kegagalan dilempar sebagai LlmUnavailableException - JANGAN diganti
 * return $teksAsli, karena NarasiCacheService akan menyimpannya permanen
 * berlabel bahasa='id' (bug 2026-07-28).
 */
class GeminiLlmProvider implements LlmProviderInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
    Kamu membantu menerjemahkan & merapikan satu paragraf narasi grafologi
    dari Bahasa Inggris ke Bahasa Indonesia yang natural dan profesional
    namun tetap hangat (untuk laporan psikologi personal).

    ATURAN KETAT:
    - JANGAN menambah klaim, contoh, atau interpretasi baru di luar teks asli.
    - JANGAN mengubah makna atau tingkat kepastian pernyataan.
    - Gunakan kata ganti netral ("penulis", "ia", "dirinya"), bukan "he/his".
    - Terjemahkan makna, bukan kata per kata secara kaku.
    - Output HANYA paragraf hasil terjemahan, tanpa embel-embel/preamble.
    PROMPT;

    public function __construct(private GeminiKeyPool $pool) {}

    public function rangkaiSatuAspek(string $teksAsli, array $context = []): string
    {
        if ($this->pool->isEmpty()) {
            throw new LlmUnavailableException('Belum ada GEMINI_API_KEY_1..10 yang diisi.');
        }

        $available = $this->pool->available();
        if ($available === []) {
            throw new LlmUnavailableException('Semua API key Gemini sedang limit/istirahat.');
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => "Aspek: {$context['nama_aspek']} (level: {$context['level']})\n\nTeks asli:\n{$teksAsli}"]],
            ]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => (int) config('services.gemini.max_output_tokens', 2048),
            ],
        ];

        foreach ($available as $slot => $key) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $key])
                    ->timeout((int) config('services.gemini.timeout', 60))
                    ->post($this->endpoint(), $payload);
            } catch (ConnectionException $e) {
                Log::warning('Gemini: koneksi gagal, coba key berikutnya', ['slot' => $slot, 'error' => $e->getMessage()]);

                continue;
            }

            if ($response->successful()) {
                $text = $this->extractText($response);
                if ($text === null) {
                    Log::warning('Gemini: respons tanpa teks', ['slot' => $slot, 'finish_reason' => $response->json('candidates.0.finishReason')]);
                    throw new LlmUnavailableException('Gemini tidak mengembalikan teks (mis. diblokir safety filter).');
                }
                $this->pool->markSuccess($slot);

                return $text;
            }

            $this->handleFailure($slot, $response);
        }

        throw new LlmUnavailableException('Semua API key Gemini gagal/limit untuk request ini.');
    }

    private function endpoint(): string
    {
        return rtrim(config('services.gemini.base_url'), '/')
            .'/models/'.config('services.gemini.model').':generateContent';
    }

    private function extractText(Response $response): ?string
    {
        $text = collect($response->json('candidates.0.content.parts', []))
            ->reject(fn ($part) => $part['thought'] ?? false)
            ->pluck('text')
            ->implode('');

        return trim($text) === '' ? null : trim($text);
    }

    private function handleFailure(int $slot, Response $response): void
    {
        $status = $response->status();
        $apiStatus = (string) $response->json('error.status');
        $details = collect($response->json('error.details', []));

        if ($status === 429 || $apiStatus === 'RESOURCE_EXHAUSTED') {
            $quotaIds = $details->pluck('violations')->flatten(1)->pluck('quotaId')->filter()->implode(',');
            $retryDelay = (int) rtrim((string) $details->pluck('retryDelay')->filter()->first(), 's');
            $isDaily = str_contains($quotaIds, 'PerDay');
            $seconds = $isDaily
                ? (int) config('services.gemini.daily_cooldown', 3600)
                : max($retryDelay, (int) config('services.gemini.cooldown', 60));

            $this->pool->coolDown($slot, $seconds, $isDaily ? 'kuota harian habis' : 'rate limit');
            Log::info('Gemini: key limit, pindah ke key berikutnya', ['slot' => $slot, 'cooldown_detik' => $seconds, 'quota' => $quotaIds]);

            return;
        }

        $reasons = $details->pluck('reason')->filter()->implode(',');
        if (in_array($status, [401, 403], true) || str_contains($reasons, 'API_KEY')) {
            $this->pool->coolDown($slot, (int) config('services.gemini.invalid_cooldown', 86400), 'key tidak valid/ditolak');
            Log::error('Gemini: key ditolak, dinonaktifkan sementara', ['slot' => $slot, 'status' => $status, 'reason' => $reasons ?: $apiStatus]);

            return;
        }

        // 5xx / overload: bukan salah key - coba key lain tanpa cooldown.
        Log::warning('Gemini: request gagal, coba key berikutnya', ['slot' => $slot, 'status' => $status, 'api_status' => $apiStatus]);
    }
}
