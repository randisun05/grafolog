<?php

namespace Tests\Feature\Api;

use App\Mail\NotificationMail;
use App\Models\InboxMessage;
use App\Models\LlmUsage;
use App\Models\User;
use App\Services\Llm\GeminiKeyPool;
use App\Services\Llm\GeminiLlmProvider;
use App\Services\Llm\LlmUnavailableException;
use App\Services\Llm\LlmUsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Pemantauan pemakaian & biaya AI (2026-10-28). */
class LlmUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
        config([
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.llm_cost.pricing' => ['gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50]],
            'services.llm_cost.default_pricing' => ['input' => 0.30, 'output' => 2.50],
            'services.llm_cost.usd_idr' => 16000,
            'services.llm_cost.monthly_budget_idr' => null,
        ]);
    }

    private function provider(array $keys = [1 => 'k1', 2 => 'k2']): GeminiLlmProvider
    {
        return new GeminiLlmProvider(new GeminiKeyPool($keys));
    }

    public function test_cost_formula_uses_price_per_million_tokens(): void
    {
        // 1.000.000 token input x $0.30 + 100.000 output x $2.50 = $0.55
        $this->assertSame(0.55, LlmUsageRecorder::costUsd('gemini-2.5-flash', 1_000_000, 100_000));
        $this->assertSame(0.0, LlmUsageRecorder::costUsd('apa-saja', 0, 0));
    }

    public function test_successful_call_records_tokens_including_thinking_and_cost(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Halo']]]]],
            'usageMetadata' => ['promptTokenCount' => 1000, 'candidatesTokenCount' => 200, 'thoughtsTokenCount' => 300],
        ], 200)]);

        $this->assertSame('Halo', $this->provider()->generateText('sys', 'prompt'));
        $u = LlmUsage::first();
        $this->assertSame(['ok', 'analisis_kandidat', 1000, 500], [$u->status, $u->purpose, $u->prompt_tokens, $u->output_tokens]);
        $this->assertEqualsWithDelta((1000 * 0.30 + 500 * 2.50) / 1_000_000, $u->cost_usd, 1e-9);
        $this->assertNotNull($u->latency_ms);
    }

    public function test_rate_limited_key_is_logged_as_failure_then_next_key_succeeds(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::sequence()
            ->push(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]], 'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5]], 200)]);

        $this->provider()->rangkaiSatuAspek('Teks', ['nama_aspek' => 'A', 'level' => 'low']);
        $this->assertSame(['gagal', 'ok'], LlmUsage::orderBy('id')->pluck('status')->all());
        $this->assertSame('terjemah_narasi', LlmUsage::first()->purpose);
        $this->assertSame(429, LlmUsage::first()->http_status);
        $this->assertSame(0.0, LlmUsage::first()->cost_usd);
    }

    public function test_all_keys_exhausted_is_logged_without_leaking_secrets(): void
    {
        $pool = new GeminiKeyPool([1 => 'rahasia-key']);
        $pool->coolDown(1, 60, 'uji');
        $this->expectException(LlmUnavailableException::class);
        try {
            (new GeminiLlmProvider($pool))->generateText('s', 'p');
        } finally {
            $u = LlmUsage::first();
            $this->assertSame('gagal', $u->status);
            $this->assertStringNotContainsString('rahasia-key', json_encode($u->toArray()));
        }
    }

    public function test_budget_alert_fires_once_per_level(): void
    {
        config(['services.llm_cost.monthly_budget_idr' => 1000]);
        $admin = User::factory()->create(['role' => 'administrator']);
        $rec = app(LlmUsageRecorder::class);
        // $0.0625 x 16000 = Rp1000 -> 100%
        $rec->record('x', 'ok', ['prompt_tokens' => 0, 'output_tokens' => 25000]);
        $rec->record('x', 'ok', ['prompt_tokens' => 0, 'output_tokens' => 1000]);
        $this->assertSame(1, InboxMessage::where('user_id', $admin->id)->where('type', 'anggaran_ai')->count());
        Mail::assertNotQueued(NotificationMail::class); // admin: kotak masuk saja
    }

    public function test_admin_endpoint_aggregates_and_is_admin_only(): void
    {
        LlmUsage::create(['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'purpose' => 'analisis_kandidat', 'status' => 'ok', 'key_slot' => 1, 'prompt_tokens' => 1000, 'output_tokens' => 1000, 'cost_usd' => 0.0028, 'latency_ms' => 800]);
        LlmUsage::create(['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'purpose' => 'terjemah_narasi', 'status' => 'gagal', 'key_slot' => 2, 'http_status' => 429, 'error' => 'RESOURCE_EXHAUSTED']);
        LlmUsage::create(['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'purpose' => 'analisis_kandidat', 'status' => 'cache']);

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/llm-usage')->assertForbidden();

        $admin = User::factory()->create(['role' => 'administrator']);
        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/llm-usage?days=7')->assertOk();
        $res->assertJsonPath('summary.calls', 1)->assertJsonPath('summary.failed', 1)->assertJsonPath('summary.cached', 1)
            ->assertJsonPath('summary.cost_idr', 45)->assertJsonPath('by_purpose.0.purpose', 'analisis_kandidat')
            ->assertJsonPath('by_key.0.key_slot', 1)->assertJsonCount(7, 'by_day');
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/llm-usage?days=0')->assertStatus(422);
    }
}
