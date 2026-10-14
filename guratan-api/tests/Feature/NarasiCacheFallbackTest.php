<?php

namespace Tests\Feature;

use App\Models\Aspek;
use App\Models\NarasiCache;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\LlmUnavailableException;
use App\Services\Llm\NarasiCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class NarasiCacheFallbackTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    public function test_unavailable_llm_returns_source_text_without_caching(): void
    {
        $this->seedMinimalAspek(1);
        $aspek = Aspek::first();
        $aspek->update(['narasi_low' => 'The writer is relaxed.']);

        $failing = new class implements LlmProviderInterface
        {
            public function rangkaiSatuAspek(string $teksAsli, array $context = []): string
            {
                throw new LlmUnavailableException('semua key limit');
            }
        };

        $result = (new NarasiCacheService($failing))->ambil($aspek, 'low');

        $this->assertSame('The writer is relaxed.', $result);
        $this->assertSame(0, NarasiCache::count());
    }
}
