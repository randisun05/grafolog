<?php

namespace Tests\Unit\Services\Supervisor;

use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\Sindrom;
use App\Models\SupervisorChatConversation;
use App\Models\SupervisorChatMessage;
use App\Models\User;
use App\Services\Reporting\TopikFilterService;
use App\Services\Supervisor\PotensiAggregationService;
use App\Services\Supervisor\SupervisorChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class SupervisorChatServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private Sindrom $sindrom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sindrom = $this->seedMinimalAspek(1);
    }

    private function companyWithSupervisorAndReport(): array
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $candidate = User::factory()->create(['name' => 'Budi Kandidat']);
        $project = Project::create(['source' => 'hr', 'created_by' => $hr->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => $candidate->id,
            'created_by' => $hr->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $this->sindrom->id, 'kode_romawi' => $this->sindrom->kode_romawi, 'nama' => $this->sindrom->nama,
                    'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => 7.0, 'band_label_rata_rata' => 'x',
                    'aspek' => [
                        ['kode' => '01', 'nama' => 'Aspek Karier', 'skor' => 7, 'band_label' => 'x', 'narasi_level' => 'high', 'narasi' => 'x'],
                    ],
                ]],
            ],
        ]);

        return [$company, $supervisor];
    }

    private function fakeLlm(string $replyText, array $usage = ['input_tokens' => 100, 'output_tokens' => 20]): void
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
                'usage' => $usage,
            ]),
        ]);
    }

    private function service(): SupervisorChatService
    {
        return new SupervisorChatService(new PotensiAggregationService(new TopikFilterService));
    }

    public function test_throws_clean_exception_when_llm_not_configured(): void
    {
        [$company, $supervisor] = $this->companyWithSupervisorAndReport();
        $conversation = SupervisorChatConversation::create(['user_id' => $supervisor->id, 'company_id' => $company->id]);

        $this->expectException(RuntimeException::class);

        $this->service()->sendMessage($conversation, 'Halo', $supervisor);
    }

    /**
     * Pola sama PaymentController::store() - row user disimpan SEBELUM
     * pengecekan konfigurasi, supaya pertanyaan tidak hilang kalau LLM
     * ternyata belum dikonfigurasi (bukan cuma kalau gagal saat dipanggil).
     */
    public function test_user_message_is_saved_even_when_llm_is_not_configured(): void
    {
        [$company, $supervisor] = $this->companyWithSupervisorAndReport();
        $conversation = SupervisorChatConversation::create(['user_id' => $supervisor->id, 'company_id' => $company->id]);

        try {
            $this->service()->sendMessage($conversation, 'Pertanyaan sebelum LLM dikonfigurasi', $supervisor);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('supervisor_chat_messages', [
            'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Pertanyaan sebelum LLM dikonfigurasi',
        ]);
    }

    public function test_user_message_is_saved_even_when_llm_call_fails(): void
    {
        [$company, $supervisor] = $this->companyWithSupervisorAndReport();
        $conversation = SupervisorChatConversation::create(['user_id' => $supervisor->id, 'company_id' => $company->id]);
        config(['services.llm.provider' => 'api', 'services.llm.api_key' => 'test-key', 'services.llm.endpoint' => 'https://api.anthropic.com/v1/messages']);
        Http::fake(['api.anthropic.com/*' => Http::response('server error', 500)]);

        try {
            $this->service()->sendMessage($conversation, 'Pertanyaan penting', $supervisor);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('supervisor_chat_messages', [
            'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Pertanyaan penting',
        ]);
    }

    public function test_saves_assistant_reply_with_token_usage(): void
    {
        [$company, $supervisor] = $this->companyWithSupervisorAndReport();
        $conversation = SupervisorChatConversation::create(['user_id' => $supervisor->id, 'company_id' => $company->id]);
        $this->fakeLlm('Balasan asisten.', ['input_tokens' => 150, 'output_tokens' => 30]);

        $message = $this->service()->sendMessage($conversation, 'Halo', $supervisor);

        $this->assertSame('assistant', $message->role);
        $this->assertSame('Balasan asisten.', $message->content);
        $this->assertSame(180, $message->token_usage);
    }

    public function test_context_includes_aggregate_and_candidate_summary(): void
    {
        [$company, $supervisor] = $this->companyWithSupervisorAndReport();
        $conversation = SupervisorChatConversation::create(['user_id' => $supervisor->id, 'company_id' => $company->id]);
        $this->fakeLlm('ok');

        $this->service()->sendMessage($conversation, 'Bagaimana kandidat kami?', $supervisor);

        Http::assertSent(function ($request) {
            $lastMessage = end($request->data()['messages']);

            return str_contains($lastMessage['content'], 'Budi Kandidat')
                && str_contains($lastMessage['content'], 'Aspek Karier')
                && str_contains($lastMessage['content'], 'Bagaimana kandidat kami?');
        });
    }

    public function test_history_window_limited_to_last_12_messages(): void
    {
        [$company, $supervisor] = $this->companyWithSupervisorAndReport();
        $conversation = SupervisorChatConversation::create(['user_id' => $supervisor->id, 'company_id' => $company->id]);
        for ($i = 1; $i <= 13; $i++) {
            SupervisorChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => "pesan lama $i"]);
        }
        $this->fakeLlm('ok');

        $this->service()->sendMessage($conversation, 'pesan baru', $supervisor);

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];
            // 12 riwayat + 1 pesan baru (dengan blok data) = 13.
            $this->assertCount(13, $messages);

            return ! collect($messages)->pluck('content')->contains('pesan lama 1');
        });
    }
}
