<?php

namespace Tests\Feature\Api\Admin;

use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function order(bool $paid, string $source = 'client'): HandwritingSample
    {
        $client = User::factory()->create(['role' => 'user']);
        $project = Project::create(['source' => $source, 'created_by' => $client->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => $client->id, 'created_by' => $client->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        if ($paid) {
            Payment::create([
                'sample_id' => $sample->id, 'invoice_number' => 'INV-'.$sample->id, 'base_amount' => 49000,
                'amount' => 49000, 'currency' => 'IDR', 'status' => 'paid', 'paid_at' => now(),
            ]);
        }

        return $sample;
    }

    public function test_non_admin_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'grafolog']), 'sanctum')
            ->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_default_lists_only_paid_unassigned_client_orders(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $paid = $this->order(true);
        $this->order(false);
        $this->order(true, 'grafolog');
        $assigned = $this->order(true);
        $assigned->assignment()->create([
            'grafolog_id' => User::factory()->create(['role' => 'grafolog'])->id,
            'assigned_by' => $admin->id, 'status' => 'assigned',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/orders')->assertOk();

        $this->assertSame([$paid->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.is_paid', true);
    }

    public function test_unpaid_filter(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->order(true);
        $unpaid = $this->order(false);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/orders?status=unpaid')->assertOk();

        $this->assertSame([$unpaid->id], array_column($response->json('data'), 'id'));
    }

    public function test_admin_assigns_order_then_grafolog_sees_it(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $order = $this->order(true);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/samples/{$order->id}/assignment", ['grafolog_id' => $grafolog->id])->assertCreated();

        $ids = array_column($this->actingAs($grafolog, 'sanctum')->getJson('/api/samples')->json('data'), 'id');
        $this->assertContains($order->id, $ids);
    }
}
