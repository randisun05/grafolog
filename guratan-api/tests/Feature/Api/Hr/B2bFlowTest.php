<?php

namespace Tests\Feature\Api\Hr;

use App\Mail\CompanyVerificationMail;
use App\Models\Aspek;
use App\Models\Assignment;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobPosition;
use App\Models\Order;
use App\Models\OrderBatch;
use App\Models\PersonalityReport;
use App\Models\ServiceDelivery;
use App\Models\User;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/** Fase C B2B (2026-10-17): daftar perusahaan, kandidat, pesanan batch, HR memilih grafolog, analisis. */
class B2bFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
        config(['payment.bypass' => true]);
    }

    private function hr(?Company $company = null): User
    {
        $company ??= Company::create(['name' => 'PT Maju']);

        return User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
    }

    private function grafolog(string $status = 'verified'): User
    {
        return User::factory()->create(['role' => 'grafolog', 'grafolog_status' => $status]);
    }

    private function candidate(User $hr, ?JobPosition $position = null, string $email = 'kandidat@example.com'): CandidateProfile
    {
        $id = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/candidates', [
            'name' => 'Kandidat', 'email' => $email, 'job_position_id' => $position?->id,
            'education' => 'S1', 'experience_years' => 3, 'consent_confirmed' => true,
        ])->assertCreated()->json('id');

        return CandidateProfile::findOrFail($id);
    }

    private function companyPayload(array $over = []): array
    {
        return $over + [
            'company_name' => 'PT Baru Jaya', 'industry' => 'Manufaktur', 'employee_range' => '51-200',
            'company_phone' => '021-555', 'address' => 'Jl. Uji 1, Jakarta',
            'name' => 'Rina HR', 'email' => 'rina@baru.test', 'phone' => '0812',
            'password' => 'RahasiaHR123', 'password_confirmation' => 'RahasiaHR123', 'consent' => true,
        ];
    }

    public function test_company_self_registration_is_pending_until_admin_approves(): void
    {
        $res = $this->postJson('/api/auth/register-company', $this->companyPayload())->assertCreated();
        $res->assertJsonPath('user.role', 'hr')->assertJsonPath('user.company.status', 'pending');
        $hr = User::where('email', 'rina@baru.test')->firstOrFail();
        $this->assertSame($hr->id, $hr->company->created_by);

        $this->actingAs($hr, 'sanctum')->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.company.status', 'pending');
        $this->actingAs($hr, 'sanctum')->getJson('/api/hr/candidates')->assertForbidden();
        $this->actingAs($hr, 'sanctum')->getJson('/api/grafologs')->assertForbidden();

        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/company-applications')
            ->assertOk()->assertJsonPath('0.name', 'PT Baru Jaya')->assertJsonPath('0.users.0.email', 'rina@baru.test');
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/company-applications/{$hr->company_id}/decision", ['decision' => 'reject'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/company-applications/{$hr->company_id}/decision", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('status', 'verified');
        Mail::assertQueued(CompanyVerificationMail::class);

        $this->actingAs($hr->fresh(), 'sanctum')->getJson('/api/hr/candidates')->assertOk();
    }

    public function test_company_registration_validates_input(): void
    {
        $this->postJson('/api/auth/register-company', $this->companyPayload(['employee_range' => '5', 'consent' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors(['employee_range', 'consent']);
    }

    public function test_hr_manages_positions_and_candidates_within_company(): void
    {
        $hr = $this->hr();
        $aspek = $this->seedMinimalAspek(3);
        $ids = Aspek::pluck('id')->take(2)->all();

        $position = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/positions', [
            'title' => 'Sales Executive', 'key_aspek_ids' => $ids,
        ])->assertCreated()->json();
        $profile = $this->candidate($hr, JobPosition::find($position['id']));
        $this->assertSame('b2b', $profile->user->acquisition_source);
        $this->assertSame('baru', $profile->stage);

        $this->actingAs($hr, 'sanctum')->putJson("/api/hr/candidates/{$profile->id}", ['stage' => 'lanjut', 'notes' => 'Bagus'])
            ->assertOk()->assertJsonPath('stage', 'lanjut');
        $this->actingAs($hr, 'sanctum')->putJson("/api/hr/candidates/{$profile->id}", ['email' => 'x@y.z'])
            ->assertUnprocessable();

        // HR perusahaan lain tidak bisa melihat/ubah.
        $other = $this->hr(Company::create(['name' => 'PT Lain']));
        $this->actingAs($other, 'sanctum')->getJson('/api/hr/candidates')->assertOk()->assertJsonCount(0);
        $this->actingAs($other, 'sanctum')->putJson("/api/hr/candidates/{$profile->id}", ['stage' => 'lanjut'])->assertNotFound();
        $this->actingAs($other, 'sanctum')->putJson("/api/hr/positions/{$position['id']}", ['title' => 'X'])->assertNotFound();
        $this->actingAs($other, 'sanctum')->postJson('/api/hr/candidates', [
            'name' => 'Y', 'email' => 'y@example.com', 'job_position_id' => $position['id'], 'consent_confirmed' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('job_position_id');
        // Kandidat perusahaan lain tidak bisa "diambil".
        $this->actingAs($other, 'sanctum')->postJson('/api/hr/candidates', [
            'name' => 'Z', 'email' => 'kandidat@example.com', 'consent_confirmed' => true,
        ])->assertUnprocessable();
    }

    public function test_batch_order_pays_once_and_assigns_chosen_grafolog(): void
    {
        $hr = $this->hr();
        $grafolog = $this->grafolog();
        $a = $this->candidate($hr, null, 'a@example.com');
        $b = $this->candidate($hr, null, 'b@example.com');

        $this->actingAs($hr, 'sanctum')->getJson('/api/hr/products')->assertOk()
            ->assertJsonFragment(['kode' => 'paket-rekrutmen'])->assertJsonMissing(['kode' => 'konseling']);
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders/preview', ['product' => 'paket-rekrutmen', 'count' => 2])
            ->assertOk()->assertJsonPath('final_total', 458000);

        $batch = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => [$a->id, $b->id], 'grafolog_id' => $grafolog->id,
        ])->assertCreated()->assertJsonPath('amount', 458000)->assertJsonCount(2, 'orders')->json();

        // Belum lunas: grafolog belum ditugaskan, sampel terkunci.
        $this->assertSame(0, Assignment::count());
        $order = Order::where('batch_id', $batch['id'])->first();
        $this->assertTrue($order->sample->requiresPayment());
        $this->assertFalse($order->sample->isPaid());

        // Pesanan ganda untuk produk yang sama ditolak selama masih aktif.
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => [$a->id], 'grafolog_id' => $grafolog->id,
        ])->assertUnprocessable();

        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/payment")->assertCreated()->assertJsonPath('paid', true);

        $this->assertSame('diproses', OrderBatch::find($batch['id'])->status);
        $this->assertSame(2, Assignment::where('grafolog_id', $grafolog->id)->count());
        $this->assertTrue($order->fresh()->sample->isPaid());
        $this->assertSame(2, ServiceDelivery::where('assigned_to', $grafolog->id)->where('status', 'ditugaskan')->count());
        $this->assertSame(['asesmen', 'asesmen'], CandidateProfile::pluck('stage')->all());

        // Grafolog melihat sampel & layanan sebagai penugasan.
        $this->actingAs($grafolog, 'sanctum')->getJson('/api/samples')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($grafolog, 'sanctum')->getJson('/api/service-deliveries')->assertOk()->assertJsonCount(2);

        // Idempoten: webhook susulan tidak menggandakan layanan.
        app(OrderService::class)->markBatchPaid(OrderBatch::find($batch['id']));
        $this->assertSame(2, ServiceDelivery::count());
    }

    public function test_batch_order_rejects_unverified_grafolog_and_foreign_candidates(): void
    {
        $hr = $this->hr();
        $mine = $this->candidate($hr);
        $other = $this->hr(Company::create(['name' => 'PT Lain']));
        $foreign = $this->candidate($other, null, 'asing@example.com');

        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => [$mine->id], 'grafolog_id' => $this->grafolog('pending')->id,
        ])->assertUnprocessable();
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => [$mine->id, $foreign->id], 'grafolog_id' => $this->grafolog()->id,
        ])->assertUnprocessable();
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'konseling', 'candidate_ids' => [$mine->id], 'grafolog_id' => $this->grafolog()->id,
        ])->assertUnprocessable(); // produk B2C saja
        $this->assertSame(0, OrderBatch::count());
    }

    public function test_candidate_cannot_see_or_pay_company_orders_and_interview_reports(): void
    {
        $hr = $this->hr();
        $grafolog = $this->grafolog();
        $profile = $this->candidate($hr);
        $batch = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => [$profile->id], 'grafolog_id' => $grafolog->id,
        ])->json();
        $order = Order::where('batch_id', $batch['id'])->first();
        $candidate = $profile->user;

        $this->actingAs($candidate, 'sanctum')->getJson('/api/orders')->assertOk()->assertJsonCount(0);
        $this->actingAs($candidate, 'sanctum')->postJson("/api/orders/{$order->id}/payment")->assertForbidden();
        $this->actingAs($candidate, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/payment")->assertForbidden();

        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/payment")->assertCreated();
        $delivery = ServiceDelivery::firstOrFail();
        $this->actingAs($candidate, 'sanctum')->getJson("/api/service-deliveries/{$delivery->id}")->assertForbidden();
        $this->actingAs($candidate, 'sanctum')->getJson('/api/service-deliveries')->assertOk()->assertJsonCount(0);

        // Rekan HR di perusahaan yang sama boleh melihat & menugaskan ulang.
        $colleague = $this->hr($hr->company);
        $this->actingAs($colleague, 'sanctum')->getJson("/api/service-deliveries/{$delivery->id}")->assertOk();
        $this->actingAs($colleague, 'sanctum')->postJson("/api/service-deliveries/{$delivery->id}/assign", ['grafolog_id' => $this->grafolog()->id])->assertOk();
    }

    public function test_unpaid_batch_can_be_cancelled(): void
    {
        $hr = $this->hr();
        $profile = $this->candidate($hr);
        $batch = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'wawancara', 'candidate_ids' => [$profile->id], 'grafolog_id' => $this->grafolog()->id,
        ])->assertCreated()->json();

        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/cancel")->assertOk()->assertJsonPath('status', 'dibatalkan');
        $this->assertSame('dibatalkan', Order::where('batch_id', $batch['id'])->value('status'));
        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/payment")->assertUnprocessable();
    }

    public function test_analysis_combines_approved_graphology_scores_and_interview(): void
    {
        $this->seedMinimalAspek(3);
        $aspek = Aspek::orderBy('id')->get();
        $hr = $this->hr();
        $grafolog = $this->grafolog();
        $position = JobPosition::create(['company_id' => $hr->company_id, 'title' => 'Sales', 'key_aspek_ids' => [$aspek[0]->id]]);
        $a = $this->candidate($hr, $position, 'a@example.com');
        $b = $this->candidate($hr, $position, 'b@example.com');
        $batch = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => [$a->id, $b->id], 'grafolog_id' => $grafolog->id,
        ])->json();
        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/payment")->assertCreated();

        $orderA = Order::where('user_id', $a->user_id)->first();
        $orderB = Order::where('user_id', $b->user_id)->first();
        $approved = PersonalityReport::create(['sample_id' => $orderA->sample_id, 'tier' => 'master', 'status' => 'completed',
            'review_status' => PersonalityReport::REVIEW_APPROVED, 'data' => []]);
        $approved->aspekScores()->create(['aspek_id' => $aspek[0]->id, 'skor' => 8]);
        $pending = PersonalityReport::create(['sample_id' => $orderB->sample_id, 'tier' => 'master', 'status' => 'completed',
            'review_status' => PersonalityReport::REVIEW_PENDING, 'data' => []]);
        $pending->aspekScores()->create(['aspek_id' => $aspek[0]->id, 'skor' => 3]);

        $delivery = ServiceDelivery::where('user_id', $a->user_id)->first();
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/service-deliveries/{$delivery->id}/report", ['report' => [
            'komunikasi' => 4, 'stabilitas_emosi' => 3, 'motivasi' => 5, 'integritas' => 4, 'kerja_sama' => 4,
            'catatan' => 'Lancar', 'rekomendasi' => 'Direkomendasikan',
        ]])->assertOk();

        $res = $this->actingAs($hr, 'sanctum')->getJson("/api/hr/analysis?position_id={$position->id}")->assertOk();
        $res->assertJsonPath('position.title', 'Sales')
            ->assertJsonCount(2, 'candidates')
            ->assertJsonPath('aspek.0.is_key', true)
            ->assertJsonPath('aspek.1.is_key', false);
        $byUser = collect($res->json('candidates'))->keyBy('user_id');
        $this->assertEquals(8, $byUser[$a->user_id]['scores'][$aspek[0]->id]);
        $this->assertSame('Direkomendasikan', $byUser[$a->user_id]['interview']['rekomendasi']);
        $this->assertNull($byUser[$b->user_id]['report_id']); // belum disetujui supervisor -> tidak tampil
        $this->assertNotEmpty($res->json('disclaimer'));

        $other = $this->hr(Company::create(['name' => 'PT Lain']));
        $this->actingAs($other, 'sanctum')->getJson("/api/hr/analysis?position_id={$position->id}")->assertNotFound();
    }

    public function test_grafolog_list_shows_profile_and_workload_for_hr(): void
    {
        $hr = $this->hr();
        $g = $this->grafolog();
        $this->actingAs($g, 'sanctum')->putJson('/api/profile', [
            'bio' => 'Grafolog rekrutmen 10 tahun', 'specializations' => ['Rekrutmen', 'Remaja'], 'experience_years' => 10, 'city' => 'Bandung',
        ])->assertOk()->assertJsonPath('user.specializations.0', 'Rekrutmen');
        $this->grafolog('pending');

        $res = $this->actingAs($hr, 'sanctum')->getJson('/api/grafologs')->assertOk();
        $res->assertJsonCount(1)->assertJsonPath('0.city', 'Bandung')->assertJsonPath('0.active_assignments', 0);
    }

    public function test_profile_fields_are_role_specific(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $this->actingAs($client, 'sanctum')->putJson('/api/profile', ['birth_date' => '1990-05-01', 'gender' => 'P', 'education' => 'S1'])
            ->assertOk()->assertJsonPath('user.birth_date', '1990-05-01');
        $this->actingAs($client, 'sanctum')->putJson('/api/profile', ['bio' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('bio');

        $hr = $this->hr();
        $this->actingAs($hr, 'sanctum')->putJson('/api/profile', ['company' => ['industry' => 'Retail', 'employee_range' => '11-50']])
            ->assertOk()->assertJsonPath('user.company.industry', 'Retail');
        $this->actingAs($hr, 'sanctum')->putJson('/api/profile', ['gender' => 'L'])->assertUnprocessable();
    }
}
