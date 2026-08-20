<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider via Anthropic Messages API (https://api.anthropic.com/v1/messages).
 * Dipanggil HANYA oleh proses pregenerate cache (php artisan
 * narasi:pregenerate) - BUKAN saat user membuka laporan. Jadi data yang
 * terkirim ke API cuma teks generik dari knowledge base, TIDAK PERNAH data
 * psikologis spesifik milik user.
 *
 * Auth: header x-api-key + anthropic-version (BUKAN Authorization: Bearer -
 * itu bug yang pernah ada di sini sampai 2026-07-28, gagal auth diam-diam
 * dan mencemari narasi_cache dengan teks Inggris yang ditandai bahasa='id').
 */
class ApiLlmProvider implements LlmProviderInterface
{
    public function rangkaiSatuAspek(string $teksAsli, array $context = []): string
    {
        $namaAspek = $context['nama_aspek'] ?? '';
        $level = $context['level'] ?? '';

        $systemPrompt = <<<PROMPT
        Kamu membantu menerjemahkan & merapikan satu paragraf narasi grafologi
        dari Bahasa Inggris ke Bahasa Indonesia yang natural dan profesional
        namun tetap hangat (untuk laporan psikologi personal).

        ATURAN KETAT:
        - JANGAN menambah klaim, contoh, atau interpretasi baru di luar teks asli.
        - JANGAN mengubah makna atau tingkat kepastian pernyataan.
        - Terjemahkan makna, bukan kata per kata secara kaku.
        - Output HANYA paragraf hasil terjemahan, tanpa embel-embel/preamble.
        PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => config('services.llm.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->post(config('services.llm.endpoint'), [
            'model' => config('services.llm.model'),
            'max_tokens' => 500,
            'system' => $systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => "Aspek: {$namaAspek} (level: {$level})\n\nTeks asli:\n{$teksAsli}",
            ]],
        ]);

        if ($response->failed()) {
            // Fallback aman - jangan sampai batch pregenerate berhenti total
            // gara-gara satu call gagal. TAPI log keras, karena NarasiCacheService
            // akan tetap menyimpan hasil fallback ini ke cache permanen berlabel
            // bahasa='id' - kegagalan diam-diam di sini = teks Inggris nyangkut
            // di cache seolah-olah sudah diterjemahkan (persis bug 2026-07-28).
            Log::warning('ApiLlmProvider: request gagal, fallback ke teks asli', [
                'status' => $response->status(),
                'body' => $response->body(),
                'nama_aspek' => $namaAspek,
                'level' => $level,
            ]);

            return $teksAsli;
        }

        return trim($response->json('content.0.text') ?? $teksAsli);
    }
}
