<?php

namespace App\Services\Llm;

use RuntimeException;

/**
 * Provider LLM tidak bisa menghasilkan teks (semua key limit/invalid, error
 * server, atau respons kosong). NarasiCacheService menangkap ini dan memakai
 * teks asli TANPA menyimpannya ke cache, supaya bisa dicoba ulang nanti.
 */
class LlmUnavailableException extends RuntimeException {}
