<?php

namespace Tests\Feature\Api\Supervisor;

use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\Sindrom;
use App\Models\SupervisorChatConversation;
use App\Models\SupervisorChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private Sindrom $sindrom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sindrom = $this->seedMinimalAspek(1);
    }

    private function reportForCompany(Company $company): void
    {
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $project = Project::create(['source' => 'hr', 'created_by' => $hr->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hr->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $this->sindrom->id, 'kode_romawi' => $this->sindrom->kode_romawi, 'nama' => $this->sindrom->nama,
                    'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => 7.0, 'band_label_rata_rata' => 'x',
                    'aspek' => [
                        ['kode' => '01', 'nama' => "Rahasia {$company->name}", 'skor' => 7, 'band_label' => 'x', 'narasi_level' => 'high', 'narasi' => 'x'],
                    ],
                ]],
            ],
        ]);
    }

    private function fakeLlm(string $replyText = 'ok'): void
    {
        config([
            'services.llm.provider' => 'api',
            'services.llm.api_key' => 'test-key',
            'services.llm.endpoint' => 'https://api.anthropic.com/v1/messages',
            'services.llm.model' => 'claude-test-model',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $replyText]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);
    }

    // --- index/store ---

    public function test_guest_cannot_access_chat_endpoints(): void
    {
        $this->getJson('/api/supervisor/chat/conversations')->assertUnauthorized();
    }

    public function test_non_supervisor_cannot_access_chat_endpoints(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')->getJson('/api/supervisor/chat/conversations')->assertForbidden();
    }

    public function test_index_lists_only_own_conversations(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $otherSupervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        SupervisorChatConversation::create(['user_id' => $supervisor->id, 'company_id' => $company->id, 'title' => 'Punya saya']);
        SupervisorChatConversation::create(['user_id' => $otherSupervisor->id, 'company_id' => $company->id, 'title' => 'Punya orang lain']);

        $response = $this->actingAs($supervisor, 'sanctum')->getJson('/api/supervisor/chat/conversations');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Punya saya');
    }

    public function test_store_creates_conversation_scoped_to_own_company(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);

        $response = $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/supervisor/chat/conversations', ['title' => 'Diskusi Kandidat']);

        $response->assertCreated()->assertJsonPath('company_id', $company->id);
        $this->assertDatabaseHas('supervisor_chat_conversations', [
            'user_id' => $supervisor->id, 'company_id' => $company->id, 'title' => 'Diskusi Kandidat',
        ]);
    }

    public function test_store_rejects_supervisor_without_company(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => null]);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/supervisor/chat/conversations', [])
            ->assertStatus(422);
    }

    // --- show/destroy ownership ---

    public function test_show_denies_a_different_supervisor_in_the_same_company(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $other = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/supervisor/chat/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    public function test_owner_can_show_own_conversation_with_messages(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);
        SupervisorChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Halo']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/supervisor/chat/conversations/{$conversation->id}");

        $response->assertOk()->assertJsonCount(1, 'messages');
    }

    public function test_destroy_denies_a_different_supervisor(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $other = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/supervisor/chat/conversations/{$conversation->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('supervisor_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_owner_can_delete_own_conversation(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/supervisor/chat/conversations/{$conversation->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('supervisor_chat_conversations', ['id' => $conversation->id]);
    }

    // --- sendMessage ---

    public function test_send_message_denies_a_different_supervisor(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $other = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/supervisor/chat/conversations/{$conversation->id}/messages", ['message' => 'Halo'])
            ->assertForbidden();
    }

    public function test_send_message_validates_max_length(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/supervisor/chat/conversations/{$conversation->id}/messages", ['message' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_send_message_returns_clean_503_when_llm_unconfigured(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);
        // LLM_PROVIDER default 'none' di test env - sengaja tidak dikonfigurasi.

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/supervisor/chat/conversations/{$conversation->id}/messages", ['message' => 'Halo'])
            ->assertStatus(503);
    }

    public function test_send_message_succeeds_and_returns_assistant_reply(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $this->reportForCompany($company);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);
        $this->fakeLlm('Balasan asisten.');

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/supervisor/chat/conversations/{$conversation->id}/messages", ['message' => 'Halo']);

        $response->assertCreated()
            ->assertJsonPath('role', 'assistant')
            ->assertJsonPath('content', 'Balasan asisten.');
    }

    /**
     * Isolasi lintas-company pada KONTEKS yang benar-benar dikirim ke
     * LLM - bukan cuma respons akhirnya, tapi body request Http::fake()
     * itu sendiri (lihat CLAUDE.md "Peran Supervisor" Fase 3, bukti
     * konkret yang diminta plan yang disetujui).
     */
    public function test_context_sent_to_llm_never_leaks_other_companys_data(): void
    {
        $companyA = Company::create(['name' => 'PT A']);
        $companyB = Company::create(['name' => 'PT B']);
        $this->reportForCompany($companyA);
        $this->reportForCompany($companyB);
        $supervisorA = User::factory()->create(['role' => 'supervisor', 'company_id' => $companyA->id]);
        $conversation = SupervisorChatConversation::create(['user_id' => $supervisorA->id, 'company_id' => $companyA->id]);
        $this->fakeLlm('ok');

        $this->actingAs($supervisorA, 'sanctum')
            ->postJson("/api/supervisor/chat/conversations/{$conversation->id}/messages", ['message' => 'Halo'])
            ->assertCreated();

        Http::assertSent(function ($request) {
            $lastMessage = end($request->data()['messages']);

            return str_contains($lastMessage['content'], 'Rahasia PT A')
                && ! str_contains($lastMessage['content'], 'Rahasia PT B');
        });
    }

    public function test_send_message_endpoint_is_rate_limited_separately_from_general_throttle(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $owner = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $this->reportForCompany($company);
        $conversation = SupervisorChatConversation::create(['user_id' => $owner->id, 'company_id' => $company->id]);
        $this->fakeLlm('ok');

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/supervisor/chat/conversations/{$conversation->id}/messages", ['message' => "pesan $i"]);
        }

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/supervisor/chat/conversations/{$conversation->id}/messages", ['message' => 'pesan ke-31']);

        $response->assertStatus(429);
    }
}
