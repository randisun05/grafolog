<?php

namespace Tests\Feature\Api;

use App\Models\Aspek;
use App\Models\GrafologEarning;
use App\Models\HandwritingSample;
use App\Models\Order;
use App\Models\PersonalityReport;
use App\Models\Product;
use App\Models\ProductCommission;
use App\Models\TokenCost;
use App\Models\TokenLedgerEntry;
use App\Models\User;
use App\Services\Commerce\EarningService;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/** Regenerasi ditagih token; komisi grafolog per produk (+override per grafolog), pencairan, refund (2026-10-31). */
class CommissionAndRegenTokenTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
    }

    private function skor(): array
    {
        return collect(Aspek::pluck('kode'))->map(fn ($k) => ['kode' => $k, 'skor' => 5])->all();
    }

    public function test_regeneration_is_charged_like_the_original_and_failure_does_not_deduct(): void
    {
        $this->seedMinimalAspek(1);
        $admin = User::factory()->create(['role' => 'administrator']);
        TokenCost::setTokensFor('master', 3, $admin);
        $g = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified', 'token_balance' => 7]);
        $sample = HandwritingSample::create(['user_id' => User::factory()->create()->id, 'created_by' => $g->id, 'tier' => 'master', 'status' => 'pending']);

        $this->actingAs($g, 'sanctum')->postJson("/api/samples/{$sample->id}/scores", ['skor' => $this->skor()])->assertCreated();
        $this->assertSame(4, $g->fresh()->token_balance);

        // Koreksi = regenerasi: ditagih lagi 3 token.
        $this->actingAs($g, 'sanctum')->postJson("/api/samples/{$sample->id}/scores/correct", ['skor' => $this->skor()])->assertOk();
        $this->assertSame(1, $g->fresh()->token_balance);
        $this->assertSame(['consumption', 'regeneration'], TokenLedgerEntry::where('user_id', $g->id)->orderBy('id')->pluck('type')->all());

        // Saldo kurang: ditolak 402, tidak ada yang berubah, laporan tidak tersentuh.
        $before = PersonalityReport::first()->generated_at;
        $this->actingAs($g, 'sanctum')->postJson("/api/samples/{$sample->id}/scores/correct", ['skor' => $this->skor()])->assertStatus(402);
        $this->assertSame(1, $g->fresh()->token_balance);
        $this->assertEquals($before, PersonalityReport::first()->generated_at);
    }

    public function test_product_token_cost_overrides_tier_cost_for_creation_and_regeneration(): void
    {
        $this->seedMinimalAspek(1);
        $admin = User::factory()->create(['role' => 'administrator']);
        TokenCost::setTokensFor('master', 3, $admin);
        $client = User::factory()->create(['role' => 'user']);
        $g = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified', 'token_balance' => 20]);
        config(['payment.bypass' => true]);
        Product::where('kode', 'master')->update(['token_cost' => 5]);
        $oid = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => Product::where('kode', 'master')->value('id'), 'grafolog_choice' => 'rekomendasi'])->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$oid}/payment")->assertCreated();
        $order = Order::find($oid);
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/client-orders/{$oid}/assign", ['grafolog_id' => $g->id])->assertOk();

        $this->actingAs($g, 'sanctum')->postJson("/api/samples/{$order->sample_id}/scores", ['skor' => $this->skor()])->assertCreated();
        $this->assertSame(15, $g->fresh()->token_balance); // 5, bukan 3
        $this->actingAs($g, 'sanctum')->postJson("/api/samples/{$order->sample_id}/scores/correct", ['skor' => $this->skor()])->assertOk();
        $this->assertSame(10, $g->fresh()->token_balance);
    }

    public function test_commission_rules_default_override_accrual_payout_and_refund_reversal(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $a = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $b = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $client = User::factory()->create(['role' => 'user']);
        $product = Product::where('kode', 'konseling')->first(); // Rp250.000

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$product->id}", ['commission_type' => 'percent', 'commission_value' => 40])->assertOk();
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$product->id}", ['commission_type' => 'percent', 'commission_value' => 150])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$product->id}/commissions/{$b->id}", ['type' => 'fixed', 'value' => 80000])->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson("/api/admin/products/{$product->id}/commissions")->assertOk()->assertJsonPath('overrides.0.grafolog_id', $b->id);

        $svc = app(EarningService::class);
        $mk = function (User $g) use ($client, $product) {
            $o = Order::create(['user_id' => $client->id, 'product_id' => $product->id, 'grafolog_id' => $g->id, 'status' => Order::STATUS_DONE, 'base_amount' => 250000, 'amount' => 200000, 'paid_at' => now()]);

            return $o->load('product', 'grafolog');
        };
        $oa = $mk($a);
        $ob = $mk($b);
        $this->assertSame(80000, $svc->accrue($oa)->amount); // 40% dari bayar bersih 200.000 (setelah diskon)
        $this->assertSame(80000, $svc->accrue($ob)->amount); // override fixed
        $this->assertSame(80000, $svc->accrue($oa)->amount);
        $this->assertSame(2, GrafologEarning::count()); // idempoten

        // Komisi 0 = tidak dicatat.
        $zero = Product::where('kode', 'wawancara')->first();
        $oz = Order::create(['user_id' => $client->id, 'product_id' => $zero->id, 'grafolog_id' => $a->id, 'status' => Order::STATUS_DONE, 'base_amount' => 1, 'amount' => 99000, 'paid_at' => now()])->load('product', 'grafolog');
        $this->assertNull($svc->accrue($oz));

        $mine = $this->actingAs($a, 'sanctum')->getJson('/api/earnings')->assertOk();
        $this->assertSame([80000, 0], [$mine->json('totals.diperoleh'), $mine->json('totals.dibayar')]);
        $this->actingAs($client, 'sanctum')->getJson('/api/earnings')->assertForbidden();

        // Pencairan oleh admin.
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/earnings/payout', ['grafolog_id' => $a->id, 'reference' => 'TRF-001'])->assertCreated()->assertJsonPath('total', 80000);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/earnings/payout', ['grafolog_id' => $a->id])->assertStatus(422); // sudah habis
        $this->assertSame([0, 80000], [$this->actingAs($a, 'sanctum')->getJson('/api/earnings')->json('totals.diperoleh'), $this->actingAs($a, 'sanctum')->getJson('/api/earnings')->json('totals.dibayar')]);

        // Refund: komisi belum dicairkan dibatalkan; yang sudah dicairkan diberi catatan penyesuaian.
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$ob->id}/refund", ['reason' => 'kesalahan_sistem', 'note' => 'uji'])->assertOk();
        $this->assertSame('dibatalkan', GrafologEarning::where('order_id', $ob->id)->value('status'));
        $svc->reverse($oa);
        $this->assertSame('dibayar', GrafologEarning::where('order_id', $oa->id)->value('status'));
        $this->assertStringContainsString('penyesuaian', (string) GrafologEarning::where('order_id', $oa->id)->value('note'));
        $this->assertSame(1, ProductCommission::count());
    }

    public function test_supervisor_must_have_minimum_published_reports(): void
    {
        config(['grafolog.supervisor_min_reports' => 2]);
        $svc = app(ReportValidationService::class);
        $g = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $this->assertFalse($svc->meetsSupervisorMinimum($g));
        foreach (range(1, 2) as $i) {
            $s = HandwritingSample::create(['user_id' => User::factory()->create()->id, 'created_by' => $g->id, 'tier' => 'master', 'status' => 'completed']);
            PersonalityReport::create(['sample_id' => $s->id, 'tier' => 'master', 'status' => 'completed', 'review_status' => 'disetujui']);
        }
        $this->assertTrue($svc->meetsSupervisorMinimum($g));
        config(['grafolog.supervisor_min_reports' => 0]);
        $this->assertTrue($svc->meetsSupervisorMinimum(User::factory()->create(['role' => 'grafolog'])));
    }

    public function test_bank_account_is_encrypted_validated_masked_and_audited_on_admin_view(): void
    {
        $g = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($g, 'sanctum')->putJson('/api/bank-account', ['bank_name' => 'BCA', 'account_number' => '12ab', 'account_holder' => 'A'])->assertStatus(422);
        $this->actingAs($g, 'sanctum')->putJson('/api/bank-account', ['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Ani Grafolog'])->assertOk()->assertJsonPath('account_number', '1234567890');
        $this->assertStringNotContainsString('1234567890', (string) \DB::table('grafolog_bank_accounts')->value('account_number')); // terenkripsi di DB
        $this->actingAs($g, 'sanctum')->getJson('/api/earnings')->assertJsonPath('bank.masked', '••••••7890');

        $e = GrafologEarning::create(['grafolog_id' => $g->id, 'order_id' => Order::create(['user_id' => User::factory()->create()->id, 'product_id' => Product::first()->id, 'base_amount' => 1, 'amount' => 1])->id, 'base_amount' => 1, 'commission_type' => 'fixed', 'commission_value' => 1, 'amount' => 50000, 'status' => 'diperoleh', 'earned_at' => now()]);
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/earnings')->assertJsonPath('per_grafolog.0.bank', 'BCA ••••••7890');
        $this->actingAs($admin, 'sanctum')->getJson("/api/admin/earnings/bank/{$g->id}")->assertOk()->assertJsonPath('account_number', '1234567890');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'lihat_rekening_grafolog', 'actor_user_id' => $admin->id]);
        $this->actingAs($g, 'sanctum')->getJson("/api/admin/earnings/bank/{$g->id}")->assertForbidden();
        $this->assertNotNull($e);
    }
}
