<?php

namespace Tests\Feature\Api;

use App\Models\AffiliateProduct;
use App\Models\Article;
use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\SelfCheckOption;
use App\Models\SelfCheckQuestion;
use App\Models\SelfCheckResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/** Fase E (2026-10-17): cek tulisan terpandu, afiliasi, artikel, kuis. */
class EngagementFeaturesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    /** Seeder migrasi berjalan tanpa KB -> petakan 3 pertanyaan pertama ke indikator uji. */
    private function mapQuestions(): array
    {
        $this->seedMinimalAspek(4);
        $aspek = Aspek::orderBy('id')->get();
        $answers = [];
        foreach (SelfCheckQuestion::orderBy('sort')->take(4)->get() as $i => $q) {
            $indikator = Indikator::create([
                'kode' => "T{$i}", 'aspek_id' => $aspek[$i < 2 ? 0 : $i - 1]->id,
                'nama' => "Ciri uji {$i}", 'keterangan' => "Keterangan KB {$i}",
            ]);
            $option = $q->options()->first();
            $option->update(['indikator_id' => $indikator->id]);
            $answers[$q->id] = $option->id;
        }

        return [$answers, $aspek];
    }

    public function test_seeded_questions_are_public_without_exposing_mapping(): void
    {
        $res = $this->getJson('/api/self-check')->assertOk();
        $this->assertCount(8, $res->json());
        $this->assertArrayNotHasKey('indikator_id', $res->json('0.options.0'));
    }

    public function test_self_check_ranks_aspek_from_kb_indicators(): void
    {
        [$answers, $aspek] = $this->mapQuestions();

        $res = $this->postJson('/api/self-check', ['answers' => $answers])->assertCreated();
        $res->assertJsonPath('aspek.0.id', $aspek[0]->id) // dua indikator di aspek pertama
            ->assertJsonCount(2, 'aspek.0.ciri')
            ->assertJsonPath('aspek.0.ciri.0.keterangan_en', 'Keterangan KB 0')
            ->assertJsonPath('cta.kode', 'master');
        $this->assertNotEmpty($res->json('disclaimer'));
        $this->assertArrayNotHasKey('skor', $res->json('aspek.0'));

        $this->getJson('/api/self-check/'.$res->json('uuid'))->assertOk()->assertJsonPath('aspek.0.id', $aspek[0]->id);
    }

    public function test_self_check_needs_three_valid_answers_and_consent_for_email(): void
    {
        [$answers] = $this->mapQuestions();
        $two = array_slice($answers, 0, 2, true);
        $this->postJson('/api/self-check', ['answers' => $two])->assertUnprocessable();

        // Opsi milik pertanyaan lain tidak dihitung.
        $wrong = [array_key_first($answers) => SelfCheckOption::where('question_id', '!=', array_key_first($answers))->value('id')] + array_slice($answers, 1, 2, true);
        $this->postJson('/api/self-check', ['answers' => $wrong])->assertUnprocessable();

        $this->postJson('/api/self-check', ['answers' => $answers, 'email' => 'lead@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->postJson('/api/self-check', ['answers' => $answers, 'email' => 'lead@example.com', 'consent' => true])->assertCreated();
        $this->assertNotNull(SelfCheckResult::where('email', 'lead@example.com')->value('consented_at'));

        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/self-check/results')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('leads.0.email', 'lead@example.com');
    }

    public function test_admin_remaps_option_and_toggles_question(): void
    {
        $this->seedMinimalAspek(1);
        $indikator = Indikator::create(['kode' => 'X', 'aspek_id' => Aspek::value('id'), 'nama' => 'Ciri X', 'keterangan' => 'K']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $option = SelfCheckOption::first();

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/self-check/options/{$option->id}", ['indikator_id' => $indikator->id])
            ->assertOk()->assertJsonPath('indikator.nama', 'Ciri X');
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/self-check/questions/{$option->question_id}", ['is_active' => false])->assertOk();
        $this->assertCount(7, $this->getJson('/api/self-check')->json());

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/self-check')->assertForbidden();
    }

    public function test_affiliate_products_by_audience_and_click_tracking(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/affiliate-products', [
            'nama' => 'Buku Jurnal', 'mitra' => 'Toko Mitra', 'url' => 'http://tidak-aman.test',
        ])->assertUnprocessable(); // wajib https
        $b2c = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/affiliate-products', [
            'nama' => 'Buku Jurnal', 'mitra' => 'Toko Mitra', 'url' => 'https://mitra.test/jurnal', 'target' => 'b2c',
        ])->assertCreated()->json();
        AffiliateProduct::create(['nama' => 'Tes Kompetensi', 'mitra' => 'HR Tools', 'url' => 'https://hr.test', 'target' => 'b2b']);

        $this->app['auth']->forgetGuards(); // request berikut sebagai tamu
        $this->getJson('/api/affiliate-products')->assertOk()->assertJsonCount(1)->assertJsonMissing(['url' => 'https://mitra.test/jurnal']);
        $hr = User::factory()->create(['role' => 'hr']);
        $this->actingAs($hr, 'sanctum')->getJson('/api/affiliate-products')->assertJsonCount(1)->assertJsonPath('0.nama', 'Tes Kompetensi');

        $this->postJson("/api/affiliate-products/{$b2c['id']}/click")->assertOk()->assertJsonPath('url', 'https://mitra.test/jurnal');
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/affiliate-products')->assertJsonPath('0.clicks_count', 1);
    }

    public function test_articles_publish_flow(): void
    {
        $this->getJson('/api/articles')->assertOk()->assertJsonCount(2); // dari ContentSeeder
        $admin = User::factory()->create(['role' => 'administrator']);

        $draft = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/articles', [
            'judul' => 'Mengenal Margin', 'isi' => 'Paragraf satu.',
        ])->assertCreated()->assertJsonPath('slug', 'mengenal-margin')->json();
        $this->getJson('/api/articles/mengenal-margin')->assertNotFound();

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/articles/{$draft['id']}", ['is_published' => true])->assertOk();
        $this->assertNotNull(Article::find($draft['id'])->published_at);
        $this->getJson('/api/articles/mengenal-margin')->assertOk()->assertJsonPath('judul', 'Mengenal Margin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/articles', ['judul' => 'Mengenal Margin', 'isi' => 'x'])
            ->assertCreated()->assertJsonPath('slug', 'mengenal-margin-2');
    }

    public function test_quiz_uses_kb_pairs_and_skips_ambiguous_indicators(): void
    {
        $this->seedMinimalAspek(5);
        $aspek = Aspek::orderBy('id')->get();
        foreach ($aspek as $i => $a) {
            Indikator::create(['kode' => "Q{$i}", 'aspek_id' => $a->id, 'nama' => "Unik {$i}", 'keterangan' => "Penjelasan {$i}"]);
        }
        // Nama sama di dua aspek -> ambigu, tidak boleh jadi soal; "See listing" juga dilewati.
        Indikator::create(['kode' => 'D1', 'aspek_id' => $aspek[0]->id, 'nama' => 'Ganda', 'keterangan' => 'a']);
        Indikator::create(['kode' => 'D2', 'aspek_id' => $aspek[1]->id, 'nama' => 'Ganda', 'keterangan' => 'b']);
        Indikator::create(['kode' => 'S1', 'aspek_id' => $aspek[2]->id, 'nama' => 'Rujukan', 'keterangan' => 'See listing 01-1.']);

        $questions = $this->getJson('/api/games/quiz?n=10')->assertOk()->json('questions');
        $this->assertCount(5, $questions);
        foreach ($questions as $q) {
            $this->assertNotContains($q['ciri'], ['Ganda', 'Rujukan']);
            $this->assertCount(4, $q['options']);
            $this->assertContains($q['answer_id'], array_column($q['options'], 'id'));
            $this->assertSame($q['answer_id'], Indikator::find($q['id'])->aspek_id);
        }
    }
}
