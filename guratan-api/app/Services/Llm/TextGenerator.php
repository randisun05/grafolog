<?php

namespace App\Services\Llm;

/**
 * Kemampuan tambahan provider LLM: teks bebas dengan instruksi sistem sendiri (di luar kontrak
 * per-aspek LlmProviderInterface). Dipakai analisis kandidat HR (2026-10-18). Provider yang tidak
 * mengimplementasikannya (Null/Api/SelfHosted) membuat analisis memakai ringkasan berbasis aturan.
 */
interface TextGenerator
{
    /** @throws LlmUnavailableException */
    public function generateText(string $system, string $prompt, string $purpose = 'analisis_kandidat'): string;
}
