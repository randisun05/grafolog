<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\GrafologAvailability;
use App\Models\HandwritingSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Ketersediaan grafolog per bulan menentukan siapa yang bisa dipilih HR (2026-10-18). */
class GrafologAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::factory()->create(['role' => 'hr', 'company_id' => Company::create(['name' => 'PT Uji'])->id]);
    }

    private function grafolog(): User
    {
        return User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
    }

    public function test_grafolog_sets_availability_per_month(): void
    {
        $g = $this->grafolog();
        $thisMonth = now()->format('Y-m');
        $next = now()->startOfMonth()->addMonth()->format('Y-m');

        $res = $this->actingAs($g, 'sanctum')->getJson('/api/grafolog/availability')->assertOk();
        $this->assertCount(6, $res->json());
        $this->assertSame('tersedia', $res->json('0.status'));
        $this->assertFalse($res->json('0.configured'));

        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $next, 'status' => 'libur', 'note' => 'Cuti'])
            ->assertOk()->assertJsonPath('status', 'libur')->assertJsonPath('bookable', false);
        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $thisMonth, 'status' => 'terbatas', 'capacity' => 3])
            ->assertOk()->assertJsonPath('slots_left', 3)->assertJsonPath('bookable', true);

        // Di luar jendela 6 bulan / status asing ditolak; non-grafolog tidak boleh.
        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => '2020-01', 'status' => 'libur'])->assertUnprocessable();
        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $thisMonth, 'status' => 'xx'])->assertUnprocessable();
        $this->actingAs($this->hr(), 'sanctum')->getJson('/api/grafolog/availability')->assertForbidden();
    }

    public function test_hr_only_sees_bookable_grafologs_while_admin_sees_all(): void
    {
        $open = $this->grafolog();
        $off = $this->grafolog();
        $full = $this->grafolog();
        $m = now()->format('Y-m');
        GrafologAvailability::create(['user_id' => $off->id, 'month' => $m, 'status' => 'libur']);
        GrafologAvailability::create(['user_id' => $full->id, 'month' => $m, 'status' => 'tersedia', 'capacity' => 1]);
        $full->assignments()->create(['sample_id' => HandwritingSample::create(['user_id' => User::factory()->create()->id, 'created_by' => $full->id, 'tier' => 'master', 'status' => 'pending'])->id, 'assigned_by' => $full->id, 'status' => 'assigned']);

        $ids = collect($this->actingAs($this->hr(), 'sanctum')->getJson('/api/grafologs')->assertOk()->json())->pluck('id');
        $this->assertEquals([$open->id], $ids->all());

        $admin = User::factory()->create(['role' => 'administrator']);
        $all = $this->actingAs($admin, 'sanctum')->getJson('/api/grafologs')->json();
        $this->assertCount(3, $all);
        $this->assertSame('libur', collect($all)->firstWhere('id', $off->id)['availability']['status']);
    }

    public function test_b2b_order_rejects_unavailable_or_overbooked_grafolog(): void
    {
        Http::fake();
        config(['payment.bypass' => true]);
        $hr = $this->hr();
        $g = $this->grafolog();
        $ids = [];
        foreach (['a', 'b'] as $n) {
            $ids[] = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/candidates', [
                'name' => $n, 'email' => "$n@example.com", 'consent_confirmed' => true,
            ])->json('id');
        }
        $body = ['product' => 'paket-rekrutmen', 'candidate_ids' => $ids, 'grafolog_id' => $g->id];

        GrafologAvailability::create(['user_id' => $g->id, 'month' => now()->format('Y-m'), 'status' => 'libur']);
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', $body)->assertUnprocessable();

        GrafologAvailability::where('user_id', $g->id)->update(['status' => 'terbatas', 'capacity' => 1]);
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', $body)->assertUnprocessable()
            ->assertJsonPath('message', 'Kuota grafolog ini bulan ini tinggal 1 kandidat.');

        GrafologAvailability::where('user_id', $g->id)->update(['capacity' => 2]);
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', $body)->assertCreated();
    }
}
