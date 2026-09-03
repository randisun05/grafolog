<?php

namespace Tests\Feature\Api\Admin;

use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\Project;
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

    public function test_guest_cannot_access_any_analytics_endpoint(): void
    {
        $this->getJson('/api/admin/analytics/revenue')->assertUnauthorized();
        $this->getJson('/api/admin/analytics/product-usage')->assertUnauthorized();
        $this->getJson('/api/admin/analytics/user-growth')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')->getJson('/api/admin/analytics/revenue')->assertForbidden();
        $this->actingAs($hr, 'sanctum')->getJson('/api/admin/analytics/product-usage')->assertForbidden();
        $this->actingAs($hr, 'sanctum')->getJson('/api/admin/analytics/user-growth')->assertForbidden();
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
}
