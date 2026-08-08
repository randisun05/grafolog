<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\MeasurementVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class MeasurementControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function sample(User $grafolog, string $tier = 'comprehensive'): HandwritingSample
    {
        return HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => $tier,
            'status' => 'pending',
        ]);
    }

    public function test_public_measurement_variable_list_is_readable_by_any_authenticated_user(): void
    {
        MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $user = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/measurement-variables');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_grafolog_can_store_and_read_back_measurements(): void
    {
        $this->seedMinimalAspek(1);
        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => $variable->id, 'nilai' => 5.2]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('measurement_readings', ['sample_id' => $sample->id, 'variable_id' => $variable->id, 'nilai' => 5.2]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'isi_pengukuran', 'actor_user_id' => $grafolog->id]);

        $read = $this->actingAs($grafolog, 'sanctum')->getJson("/api/samples/{$sample->id}/measurements");
        $read->assertOk()->assertJsonCount(1);
    }

    public function test_resubmitting_a_variable_updates_instead_of_duplicating(): void
    {
        $this->seedMinimalAspek(1);
        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => $variable->id, 'nilai' => 5.2]],
        ]);
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => $variable->id, 'nilai' => 7.0]],
        ]);

        $this->assertDatabaseCount('measurement_readings', 1);
        $this->assertDatabaseHas('measurement_readings', ['sample_id' => $sample->id, 'variable_id' => $variable->id, 'nilai' => 7.0]);
    }

    public function test_non_grafolog_cannot_store_measurements(): void
    {
        $this->seedMinimalAspek(1);
        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $user = User::factory()->create(['role' => 'user']);
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => $variable->id, 'nilai' => 5.2]],
        ])->assertForbidden();
    }

    public function test_grafolog_who_did_not_create_sample_cannot_store_measurements(): void
    {
        $this->seedMinimalAspek(1);
        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $grafologA = User::factory()->create(['role' => 'grafolog']);
        $grafologB = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafologA);

        $this->actingAs($grafologB, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => $variable->id, 'nilai' => 5.2]],
        ])->assertForbidden();
    }

    public function test_rapid_tier_sample_rejects_measurements(): void
    {
        $this->seedMinimalAspek(1);
        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog, 'rapid');

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => $variable->id, 'nilai' => 5.2]],
        ])->assertStatus(422);
    }

    public function test_unknown_variable_id_is_rejected(): void
    {
        $this->seedMinimalAspek(1);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/measurements", [
            'pengukuran' => [['variable_id' => 99999, 'nilai' => 5.2]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['pengukuran.0.variable_id']);
    }
}
