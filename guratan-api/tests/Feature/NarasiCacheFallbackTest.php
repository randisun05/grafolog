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

    public function test_very_high_falls_back_to_high_narasi_when_source_is_empty(): void
    {
        $this->seedMinimalAspek(1);
        $aspek = Aspek::first();
        $aspek->update(['narasi_very_high' => null, 'narasi_high' => 'High text.', 'keterangan_umum' => 'Generic.']);

        $this->assertSame('High text.', NarasiCacheService::teksSumber($aspek->fresh(), 'very_high'));

        $aspek->update(['narasi_very_high' => 'Very high text.']);
        $this->assertSame('Very high text.', NarasiCacheService::teksSumber($aspek->fresh(), 'very_high'));
    }

    public function test_empty_string_level_falls_back_to_keterangan_umum(): void
    {
        $this->seedMinimalAspek(1);
        $aspek = Aspek::first();
        $aspek->update(['narasi_low' => '', 'keterangan_umum' => 'Generic.']);

        $this->assertSame('Generic.', NarasiCacheService::teksSumber($aspek->fresh(), 'low'));
    }
}
