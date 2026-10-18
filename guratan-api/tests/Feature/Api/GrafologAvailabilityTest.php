<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\GrafologAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Ketersediaan grafolog setahun menentukan siapa yang bisa dipilih HR & dipakai sebagai validator. */
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

    public function test_grafolog_sets_a_year_of_availability_with_supervision_flag(): void
    {
        $g = $this->grafolog();
        $thisMonth = now()->format('Y-m');
        $far = now()->startOfMonth()->addMonths(11)->format('Y-m');

        $res = $this->actingAs($g, 'sanctum')->getJson('/api/grafolog/availability')->assertOk();
        $this->assertCount(12, $res->json());
        $this->assertSame('tersedia', $res->json('0.status'));
        $this->assertFalse($res->json('0.supervision'));
        $this->assertFalse($res->json('0.configured'));

        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $far, 'status' => 'libur', 'note' => 'Cuti'])
            ->assertOk()->assertJsonPath('status', 'libur')->assertJsonPath('bookable', false)->assertJsonPath('supervisable', false);
        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $thisMonth, 'status' => 'terbatas', 'capacity' => 3, 'supervision' => true])
            ->assertOk()->assertJsonPath('slots_left', 3)->assertJsonPath('bookable', true)->assertJsonPath('supervisable', true);

        $beyond = now()->startOfMonth()->addMonths(12)->format('Y-m');
        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $beyond, 'status' => 'libur'])->assertUnprocessable();
        $this->actingAs($g, 'sanctum')->putJson('/api/grafolog/availability', ['month' => $thisMonth, 'status' => 'xx'])->assertUnprocessable();
        $this->actingAs($this->hr(), 'sanctum')->getJson('/api/grafolog/availability')->assertForbidden();
    }

    public function test_hr_filters_by_month_and_sees_yearly_calendar_note(): void
    {
        $open = $this->grafolog();
        $off = $this->grafolog();
        $next = now()->startOfMonth()->addMonth()->format('Y-m');
        $now = now()->format('Y-m');
        GrafologAvailability::create(['user_id' => $off->id, 'month' => $now, 'status' => 'libur']);
        GrafologAvailability::create(['user_id' => $off->id, 'month' => $next, 'status' => 'tersedia']);

        $hr = $this->hr();
        $this->assertEquals([$open->id], collect($this->actingAs($hr, 'sanctum')->getJson('/api/grafologs')->json())->pluck('id')->all());

        $res = $this->actingAs($hr, 'sanctum')->getJson("/api/grafologs?month={$next}")->assertOk();
        $this->assertEqualsCanonicalizing([$open->id, $off->id], collect($res->json())->pluck('id')->all());
        $calendar = collect($res->json())->firstWhere('id', $off->id)['calendar'];
        $this->assertSame([['month' => $now, 'status' => 'libur'], ['month' => $next, 'status' => 'tersedia']], $calendar);

        $this->actingAs($hr, 'sanctum')->getJson('/api/grafologs?month=2020-01')->assertUnprocessable();
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->assertCount(2, $this->actingAs($admin, 'sanctum')->getJson('/api/grafologs')->json());
    }

    public function test_b2b_order_checks_chosen_month_and_capacity_per_service_month(): void
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
        $now = now()->format('Y-m');
        $next = now()->startOfMonth()->addMonth()->format('Y-m');
        $body = ['product' => 'paket-rekrutmen', 'candidate_ids' => $ids, 'grafolog_id' => $g->id];

        GrafologAvailability::create(['user_id' => $g->id, 'month' => $now, 'status' => 'libur']);
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', $body)->assertUnprocessable();

        GrafologAvailability::where('user_id', $g->id)->update(['status' => 'terbatas', 'capacity' => 1]);
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', $body)->assertUnprocessable()
            ->assertJsonPath('message', 'Kuota grafolog ini untuk bulan tersebut tinggal 1 kandidat.');

        // Bulan depan belum diatur -> tersedia; kuota bulan ini tidak ikut terpakai.
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', $body + ['month' => $next])->assertCreated()->assertJsonPath('service_month', $next);
        $this->assertSame(2, GrafologAvailability::forMonth($g, $next)['workload']);
        $this->assertSame(0, GrafologAvailability::forMonth($g, $now)['workload']);
    }
}
