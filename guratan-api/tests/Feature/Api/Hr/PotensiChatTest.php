<?php

namespace Tests\Feature\Api\Hr;

use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\HrChatConversation;
use App\Models\Order;
use App\Models\OrderBatch;
use App\Models\PersonalityReport;
use App\Models\Product;
use App\Models\Sindrom;
use App\Models\User;
use App\Services\B2b\HrChatService;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\LlmUnavailableException;
use App\Services\Llm\NullLlmProvider;
use App\Services\Llm\TextGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/** Dashboard Potensi + asisten data AI untuk HR (dipindah dari fitur Supervisor branch lama). */
class PotensiChatTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private Sindrom $sindrom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sindrom = $this->seedMinimalAspek(1);
    }

    private function hr(Company $company): User
    {
        return User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
    }

    private function reportFor(Company $company, string $candidateName, int $skor, string $review = PersonalityReport::REVIEW_APPROVED): PersonalityReport
    {
        $hr = $this->hr($company);
        $candidate = User::factory()->create(['role' => 'user', 'name' => $candidateName]);
        $product = Product::firstOrFail();
        $batch = OrderBatch::create(['company_id' => $company->id, 'product_id' => $product->id, 'created_by' => $hr->id, 'base_amount' => 0, 'amount' => 0]);
        $sample = HandwritingSample::create(['user_id' => $candidate->id, 'created_by' => $hr->id, 'tier' => 'comprehensive', 'status' => 'completed']);
        Order::create(['batch_id' => $batch->id, 'user_id' => $candidate->id, 'product_id' => $product->id, 'sample_id' => $sample->id, 'created_by' => $hr->id, 'base_amount' => 0, 'amount' => 0]);

        return PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(), 'review_status' => $review,
            'data' => ['sindrom' => [[
                'id' => $this->sindrom->id, 'kode_romawi' => $this->sindrom->kode_romawi, 'nama' => $this->sindrom->nama,
                'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => $skor, 'band_label_rata_rata' => 'x',
                'aspek' => [['kode' => '01', 'nama' => 'Aspek Karier', 'skor' => $skor, 'band_label' => 'x', 'narasi_level' => 'high', 'narasi' => 'x']],
            ]]],
        ]);
    }

    private function fakeAi(string $reply = 'Jawaban asisten.'): object
    {
        $fake = new class($reply) extends NullLlmProvider implements TextGenerator
        {
            public array $calls = [];

            public function __construct(private string $reply) {}

            public function generateText(string $system, string $prompt, string $purpose = 'analisis_kandidat'): string
            {
                $this->calls[] = compact('system', 'prompt', 'purpose');

                return $this->reply;
            }
        };
        $this->app->instance(LlmProviderInterface::class, $fake);

        return $fake;
    }

    public function test_potensi_aggregates_only_approved_reports_of_own_company(): void
    {
        $mine = Company::create(['name' => 'PT Saya']);
        $other = Company::create(['name' => 'PT Lain']);
        $this->reportFor($mine, 'A', 4);
        $this->reportFor($mine, 'B', 8);
        $this->reportFor($mine, 'C', 10, PersonalityReport::REVIEW_PENDING); // belum disetujui
        $this->reportFor($other, 'D', 1);
        $hr = User::where('company_id', $mine->id)->first();

        $res = $this->actingAs($hr, 'sanctum')->getJson('/api/hr/potensi')->assertOk();

        $res->assertJsonPath('report_count', 2)
            ->assertJsonPath('sindrom.0.aspek.0.avg_skor', 6);
    }

    public function test_potensi_is_hr_only_and_needs_company(): void
    {
        $this->getJson('/api/hr/potensi')->assertUnauthorized();
        $client = User::factory()->create(['role' => 'user']);
        $this->actingAs($client, 'sanctum')->getJson('/api/hr/potensi')->assertForbidden();
        $noCompany = User::factory()->create(['role' => 'hr', 'company_id' => null]);
        $this->actingAs($noCompany, 'sanctum')->getJson('/api/hr/potensi')->assertStatus(422);
    }

    public function test_chat_sends_anonymous_context_and_stores_both_messages(): void
    {
        $company = Company::create(['name' => 'PT Saya']);
        $this->reportFor($company, 'Budi Rahasia', 7);
        $hr = User::where('company_id', $company->id)->first();
        $fake = $this->fakeAi('Rata-rata aspek karier 7.');

        $conv = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/chat')->assertCreated()->json();
        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/chat/{$conv['id']}/messages", ['message' => 'Bagaimana aspek karier?'])
            ->assertCreated()->assertJsonPath('role', 'assistant')->assertJsonPath('content', 'Rata-rata aspek karier 7.');

        $this->assertCount(1, $fake->calls);
        $this->assertSame(HrChatService::PURPOSE, $fake->calls[0]['purpose']);
        $this->assertStringContainsString('Kandidat 1', $fake->calls[0]['prompt']);
        $this->assertStringNotContainsString('Budi Rahasia', $fake->calls[0]['prompt']);
        $this->assertStringNotContainsString('@', $fake->calls[0]['prompt']);
        $this->assertSame(['user', 'assistant'], HrChatConversation::first()->messages()->orderBy('id')->pluck('role')->all());
    }

    public function test_chat_without_ai_keeps_the_question_and_returns_503(): void
    {
        $company = Company::create(['name' => 'PT Saya']);
        $hr = $this->hr($company);
        $this->app->instance(LlmProviderInterface::class, new NullLlmProvider);

        $conv = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/chat')->assertCreated()->json();
        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/chat/{$conv['id']}/messages", ['message' => 'Halo?'])
            ->assertStatus(503)->assertJsonMissingPath('trace');

        $this->assertSame(['user'], HrChatConversation::first()->messages()->pluck('role')->all());
    }

    public function test_conversations_are_private_to_their_owner(): void
    {
        $company = Company::create(['name' => 'PT Saya']);
        $owner = $this->hr($company);
        $colleague = $this->hr($company);
        $conv = $this->actingAs($owner, 'sanctum')->postJson('/api/hr/chat')->json();

        $this->actingAs($colleague, 'sanctum')->getJson("/api/hr/chat/{$conv['id']}")->assertForbidden();
        $this->actingAs($colleague, 'sanctum')->deleteJson("/api/hr/chat/{$conv['id']}")->assertForbidden();
        $this->actingAs($colleague, 'sanctum')->getJson('/api/hr/chat')->assertOk()->assertJsonCount(0);
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/hr/chat/{$conv['id']}")->assertNoContent();
    }

    public function test_message_validation(): void
    {
        $hr = $this->hr(Company::create(['name' => 'PT Saya']));
        $conv = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/chat')->json();

        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/chat/{$conv['id']}/messages", ['message' => ''])->assertStatus(422);
        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/chat/{$conv['id']}/messages", ['message' => str_repeat('x', 2001)])->assertStatus(422);
        $this->assertInstanceOf(LlmUnavailableException::class, new LlmUnavailableException('x'));
    }
}
