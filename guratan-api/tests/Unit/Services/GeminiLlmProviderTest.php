<?php

namespace Tests\Unit\Services;

use App\Services\Llm\GeminiKeyPool;
use App\Services\Llm\GeminiLlmProvider;
use App\Services\Llm\LlmUnavailableException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiLlmProviderTest extends TestCase
{
    private const CONTEXT = ['nama_aspek' => 'Authoritarian', 'level' => 'low'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.gemini.base_url' => 'https://gemini.test/v1beta', 'services.gemini.model' => 'gemini-test']);
    }

    private function provider(int $keyCount = 3): GeminiLlmProvider
    {
        $keys = [];
        foreach (range(1, $keyCount) as $slot) {
            $keys[$slot] = "key-{$slot}";
        }

        return new GeminiLlmProvider(new GeminiKeyPool($keys));
    }

    private static function ok(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP']]];
    }

    private static function rateLimited(string $quotaId = 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier', string $retry = '30s'): array
    {
        return ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'details' => [
            ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => $quotaId]]],
            ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => $retry],
        ]]];
    }

    private function fakeByKey(array $byKey): void
    {
        Http::fake(function (Request $request) use ($byKey) {
            [$body, $status] = $byKey[$request->header('x-goog-api-key')[0]] ?? [self::ok('?'), 200];

            return Http::response($body, $status);
        });
    }

    public function test_uses_first_key_and_returns_text(): void
    {
        $this->fakeByKey(['key-1' => [self::ok('Penulis bersikap santai.'), 200]]);

        $this->assertSame('Penulis bersikap santai.', $this->provider()->rangkaiSatuAspek('The writer is relaxed.', self::CONTEXT));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://gemini.test/v1beta/models/gemini-test:generateContent'
            && $r->header('x-goog-api-key')[0] === 'key-1');
    }

    public function test_rate_limited_key_fails_over_to_next_and_rests(): void
    {
        $this->fakeByKey([
            'key-1' => [self::rateLimited(), 429],
            'key-2' => [self::ok('dari key 2'), 200],
        ]);
        $provider = $this->provider();

        $this->assertSame('dari key 2', $provider->rangkaiSatuAspek('x', self::CONTEXT));
        $this->assertSame('dari key 2', $provider->rangkaiSatuAspek('x', self::CONTEXT));

        // Request kedua langsung ke key 2 (key 1 istirahat, key 2 jadi aktif).
        $this->assertSame(['key-1', 'key-2', 'key-2'], Http::recorded()->map(fn ($pair) => $pair[0]->header('x-goog-api-key')[0])->all());
        $status = (new GeminiKeyPool([1 => 'key-1', 2 => 'key-2']))->status();
        $this->assertSame(['istirahat', 'aktif'], array_column($status, 'status'));
    }

    public function test_rested_key_is_used_again_after_cooldown(): void
    {
        $state = (object) ['recovered' => false];
        Http::fake(fn () => $state->recovered
            ? Http::response(self::ok('pulih'))
            : Http::response(self::rateLimited(retry: '10s'), 429));
        $provider = $this->provider(2);

        $this->assertThrows(fn () => $provider->rangkaiSatuAspek('x', self::CONTEXT), LlmUnavailableException::class, 'gagal/limit');

        $this->travel(30)->seconds();
        $this->assertThrows(fn () => $provider->rangkaiSatuAspek('x', self::CONTEXT), LlmUnavailableException::class, 'sedang limit/istirahat');
        $this->assertCount(2, Http::recorded());

        $this->travel(31)->seconds();
        $state->recovered = true;
        $this->assertSame('pulih', $provider->rangkaiSatuAspek('x', self::CONTEXT));
    }

    public function test_daily_quota_uses_long_cooldown(): void
    {
        config(['services.gemini.daily_cooldown' => 3600]);
        $this->fakeByKey([
            'key-1' => [self::rateLimited('GenerateRequestsPerDayPerProjectPerModel-FreeTier'), 429],
            'key-2' => [self::ok('ok'), 200],
        ]);
        $this->provider(2)->rangkaiSatuAspek('x', self::CONTEXT);

        $status = (new GeminiKeyPool([1 => 'key-1', 2 => 'key-2']))->status();
        $this->assertSame('kuota harian habis', $status[0]['reason']);
        $this->travel(30)->minutes();
        $this->assertSame('istirahat', (new GeminiKeyPool([1 => 'key-1', 2 => 'key-2']))->status()[0]['status']);
    }

    public function test_invalid_key_is_skipped(): void
    {
        $this->fakeByKey([
            'key-1' => [['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'details' => [['reason' => 'API_KEY_INVALID']]]], 400],
            'key-2' => [self::ok('ok'), 200],
        ]);

        $this->assertSame('ok', $this->provider(2)->rangkaiSatuAspek('x', self::CONTEXT));
        $this->assertSame('key tidak valid/ditolak', (new GeminiKeyPool([1 => 'key-1', 2 => 'key-2']))->status()[0]['reason']);
    }

    public function test_server_error_tries_next_key_without_cooldown(): void
    {
        $this->fakeByKey([
            'key-1' => [['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503],
            'key-2' => [self::ok('ok'), 200],
        ]);

        $this->assertSame('ok', $this->provider(2)->rangkaiSatuAspek('x', self::CONTEXT));
        $this->assertNotSame('istirahat', (new GeminiKeyPool([1 => 'key-1', 2 => 'key-2']))->status()[0]['status']);
    }

    public function test_all_keys_limited_throws_instead_of_returning_source_text(): void
    {
        $this->fakeByKey(['key-1' => [self::rateLimited(), 429], 'key-2' => [self::rateLimited(), 429]]);

        $this->expectException(LlmUnavailableException::class);
        $this->provider(2)->rangkaiSatuAspek('x', self::CONTEXT);
    }

    public function test_no_keys_configured_throws(): void
    {
        $this->expectException(LlmUnavailableException::class);
        (new GeminiLlmProvider(new GeminiKeyPool([])))->rangkaiSatuAspek('x', self::CONTEXT);
    }

    public function test_pool_reads_up_to_ten_slots_and_ignores_empty(): void
    {
        config(['services.gemini.keys' => [1 => 'a', 2 => '', 3 => null, 10 => 'j']]);

        $this->assertSame([1, 10], array_column(GeminiKeyPool::fromConfig()->status(), 'slot'));
    }
}
