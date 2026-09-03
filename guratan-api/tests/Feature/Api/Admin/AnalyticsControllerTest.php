<?php

namespace Tests\Feature\Api\Admin;

use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\TokenLedgerEntry;
use App\Models\TokenPrice;
use App\Models\TokenPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSample(array $overrides = []): HandwritingSample
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $project = Project::create(['source' => $overrides['source'] ?? 'grafolog', 'created_by' => $grafolog->id]);

        return HandwritingSample::create(array_merge([
            'project_id' => $project->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ], array_diff_key($overrides, ['source' => true])));
    }

    private function endpoints(): array
    {
        return [
            '/api/admin/analytics/revenue',
            '/api/admin/analytics/product-usage',
            '/api/admin/analytics/user-growth',
            '/api/admin/analytics/grafolog-performance',
            '/api/admin/analytics/token-economy',
            '/api/admin/analytics/discount-effectiveness',
        ];
    }

    public function test_guest_cannot_access_any_analytics_endpoint(): void
    {
        foreach ($this->endpoints() as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        foreach ($this->endpoints() as $endpoint) {
            $this->actingAs($hr, 'sanctum')->getJson($endpoint)->assertForbidden();
        }
    }

    public function test_revenue_aggregates_paid_transactions_grouped_by_month(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $sampleComprehensive = $this->makeSample(['tier' => 'comprehensive']);
        $sampleMaster = $this->makeSample(['tier' => 'master']);

        Payment::create([
            'sample_id' => $sampleComprehensive->id, 'invoice_number' => 'PAY-JAN',
            'base_amount' => 49000, 'amount' => 49000, 'status' => 'paid',
            'paid_at' => '2026-01-15',
        ]);
        Payment::create([
            'sample_id' => $sampleMaster->id, 'invoice_number' => 'PAY-FEB',
            'base_amount' => 99000, 'amount' => 99000, 'status' => 'paid',
            'paid_at' => '2026-02-10',
        ]);
        // Belum bayar - tidak boleh ikut terhitung.
        Payment::create([
            'sample_id' => $this->makeSample()->id, 'invoice_number' => 'PAY-PENDING',
            'base_amount' => 49000, 'amount' => 49000, 'status' => 'pending',
        ]);

        $grafolog = User::factory()->create(['role' => 'grafolog']);
        TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 10, 'unit_price' => 5000,
            'base_amount' => 50000, 'amount' => 50000, 'status' => 'paid',
            'invoice_number' => 'TOK-JAN', 'paid_at' => '2026-01-20',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson(
            '/api/admin/analytics/revenue?from=2026-01-01&to=2026-02-28'
        );

        $response->assertOk();
        $this->assertSame(49000 + 99000 + 50000, $response->json('total_revenue'));
        $this->assertSame(49000 + 99000, $response->json('report_revenue'));
        $this->assertSame(50000, $response->json('token_revenue'));
        $this->assertSame(49000, $response->json('revenue_by_tier.comprehensive'));
        $this->assertSame(99000, $response->json('revenue_by_tier.master'));

        $series = collect($response->json('series'))->keyBy('period');
        $this->assertSame(49000, $series['2026-01']['report_revenue']);
        $this->assertSame(50000, $series['2026-01']['token_revenue']);
        $this->assertSame(99000, $series['2026-01']['report_revenue'] + $series['2026-01']['token_revenue']);
        $this->assertSame(99000, $series['2026-02']['report_revenue']);
        $this->assertSame(0, $series['2026-02']['token_revenue']);
    }

    public function test_product_usage_breaks_down_by_tier_status_source(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->makeSample(['tier' => 'comprehensive', 'status' => 'completed', 'source' => 'grafolog']);
        $this->makeSample(['tier' => 'comprehensive', 'status' => 'pending', 'source' => 'grafolog']);
        $this->makeSample(['tier' => 'master', 'status' => 'completed', 'source' => 'hr']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/analytics/product-usage');

        $response->assertOk();
        $this->assertSame(3, $response->json('total_samples'));
        $this->assertSame(2, $response->json('by_tier.comprehensive'));
        $this->assertSame(1, $response->json('by_tier.master'));
        $this->assertSame(2, $response->json('by_status.completed'));
        $this->assertSame(1, $response->json('by_status.pending'));
        $this->assertSame(2, $response->json('by_source.grafolog'));
        $this->assertSame(1, $response->json('by_source.hr'));
    }

    public function test_user_growth_breaks_down_by_role_and_period(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $u1 = User::factory()->create(['role' => 'user']);
        $u1->created_at = '2026-01-05';
        $u1->save();

        $u2 = User::factory()->create(['role' => 'grafolog']);
        $u2->created_at = '2026-01-20';
        $u2->save();

        $u3 = User::factory()->create(['role' => 'user']);
        $u3->created_at = '2026-02-01';
        $u3->save();

        $response = $this->actingAs($admin, 'sanctum')->getJson(
            '/api/admin/analytics/user-growth?from=2026-01-01&to=2026-02-28'
        );

        $response->assertOk();
        $this->assertSame(3, $response->json('total_new_users'));
        $this->assertSame(2, $response->json('by_role.user'));
        $this->assertSame(1, $response->json('by_role.grafolog'));

        $series = collect($response->json('series'))->keyBy('period');
        $this->assertSame(2, $series['2026-01']['total']);
        $this->assertSame(1, $series['2026-02']['total']);
        $this->assertSame(1, $series['2026-01']['by_role']['user']);
        $this->assertSame(1, $series['2026-01']['by_role']['grafolog']);
    }

    public function test_grafolog_performance_reports_completed_and_turnaround_per_grafolog(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'name' => 'Grafolog Aktif']);
        $idleGrafolog = User::factory()->create(['role' => 'grafolog', 'name' => 'Grafolog Idle']);

        $project = Project::create(['source' => 'grafolog', 'created_by' => $grafolog->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        $sample->created_at = now()->subDays(4);
        $sample->save();
        PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed',
            'generated_at' => now(), 'data' => ['sindrom' => []],
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/analytics/grafolog-performance');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('grafolog');

        $this->assertSame(1, $data['Grafolog Aktif']['completed_reports']);
        $this->assertEqualsWithDelta(4.0, $data['Grafolog Aktif']['avg_turnaround_days'], 0.1);
        $this->assertSame(0, $data['Grafolog Idle']['completed_reports']);
        $this->assertNull($data['Grafolog Idle']['avg_turnaround_days']);
    }

    public function test_token_economy_aggregates_sales_and_consumption(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        TokenPrice::setPrice(5000, $admin);

        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 30]);
        TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 20, 'unit_price' => 5000,
            'base_amount' => 100000, 'amount' => 100000, 'status' => 'paid',
            'invoice_number' => 'TOK-ECO-1', 'paid_at' => now(),
        ]);
        // Belum bayar - tidak boleh ikut terhitung.
        TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 99, 'unit_price' => 5000,
            'base_amount' => 495000, 'amount' => 495000, 'status' => 'pending',
            'invoice_number' => 'TOK-ECO-PENDING',
        ]);

        TokenLedgerEntry::create([
            'user_id' => $grafolog->id, 'type' => 'consumption', 'delta' => -3, 'balance_after' => 27,
        ]);
        TokenLedgerEntry::create([
            'user_id' => $grafolog->id, 'type' => 'purchase', 'delta' => 20, 'balance_after' => 47,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/analytics/token-economy');

        $response->assertOk();
        $this->assertSame(20, $response->json('tokens_sold'));
        $this->assertSame(100000, $response->json('token_revenue'));
        $this->assertSame(3, $response->json('tokens_consumed'));
        $this->assertSame(30, $response->json('outstanding_grafolog_balance'));
        $this->assertSame(5000, $response->json('current_price_per_token'));
    }

    public function test_discount_effectiveness_merges_payment_and_token_purchase_usage(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $usedCode = DiscountCode::create([
            'code' => 'GABUNG10', 'type' => 'fixed', 'value' => 5000,
            'used_count' => 2, 'created_by' => $admin->id,
        ]);
        $unusedCode = DiscountCode::create([
            'code' => 'BELUM-PERNAH', 'type' => 'fixed', 'value' => 1000, 'created_by' => $admin->id,
        ]);

        $sample = $this->makeSample();
        Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'PAY-DISC-1',
            'base_amount' => 49000, 'discount_code_id' => $usedCode->id, 'amount' => 44000,
            'status' => 'paid', 'paid_at' => now(),
        ]);

        $grafolog = User::factory()->create(['role' => 'grafolog']);
        TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 10, 'unit_price' => 5000,
            'base_amount' => 50000, 'discount_code_id' => $usedCode->id, 'amount' => 45000,
            'status' => 'paid', 'invoice_number' => 'TOK-DISC-1', 'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/analytics/discount-effectiveness');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('code');

        $this->assertSame(2, $data['GABUNG10']['used_count']);
        // (49000-44000) dari Payment + (50000-45000) dari TokenPurchase.
        $this->assertSame(5000 + 5000, $data['GABUNG10']['discount_given']);
        $this->assertSame(44000 + 45000, $data['GABUNG10']['revenue_generated']);

        $this->assertSame(0, $data['BELUM-PERNAH']['used_count']);
        $this->assertSame(0, $data['BELUM-PERNAH']['discount_given']);
        $this->assertSame(0, $data['BELUM-PERNAH']['revenue_generated']);
    }
}
