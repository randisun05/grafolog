<?php

namespace App\Services\Llm;

/**
 * Kontrak lapisan LLM. Bekerja PER-ASPEK PER-LEVEL (bukan per-laporan),
 * supaya hasilnya bisa di-cache permanen oleh NarasiCacheService -
 * 40 aspek x 4 level = 160 kombinasi unik total, tidak akan bertambah
 * seiring jumlah user/laporan.
 */
interface LlmProviderInterface
{
    /**
     * @param  string  $teksAsli  Narasi mentah (Bahasa Inggris) dari knowledge base
     *                            untuk SATU aspek pada SATU level skor.
     * @param  array  $context  Info tambahan untuk prompt, mis. ['nama_aspek' => ..., 'sindrom' => ...]
     * @return string Narasi hasil olahan (mis. sudah diterjemahkan ke Bahasa Indonesia).
     */
    public function rangkaiSatuAspek(string $teksAsli, array $context = []): string;
}
