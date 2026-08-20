<?php

namespace App\Services\Llm;

/**
 * Fallback paling aman: kembalikan teks asli apa adanya (Bahasa Inggris,
 * tanpa olahan). Dipakai kalau LLM_PROVIDER=none ATAU sebagai fallback
 * kalau provider lain gagal - laporan tetap tampil, cuma belum diterjemahkan.
 */
class NullLlmProvider implements LlmProviderInterface
{
    public function rangkaiSatuAspek(string $teksAsli, array $context = []): string
    {
        return $teksAsli;
    }
}
