<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

/**
 * Provider self-hosted (mis. Ollama). Sama seperti ApiLlmProvider,
 * hanya dipanggil saat pregenerate cache, bukan per-laporan user.
 */
class SelfHostedLlmProvider implements LlmProviderInterface
{
    public function rangkaiSatuAspek(string $teksAsli, array $context = []): string
    {
        $namaAspek = $context['nama_aspek'] ?? '';
        $level = $context['level'] ?? '';

        $prompt = <<<PROMPT
        Terjemahkan & rapikan narasi grafologi berikut dari Bahasa Inggris ke
        Bahasa Indonesia yang natural dan profesional. JANGAN menambah klaim
        baru di luar teks asli. Output hanya paragraf hasil terjemahan.

        Aspek: {$namaAspek} (level: {$level})

        Teks asli:
        {$teksAsli}
        PROMPT;

        $response = Http::post(config('services.llm.selfhosted_url').'/api/generate', [
            'model' => config('services.llm.selfhosted_model'),
            'prompt' => $prompt,
            'stream' => false,
        ]);

        if ($response->failed()) {
            return $teksAsli;
        }

        return trim($response->json('response') ?? $teksAsli);
    }
}
