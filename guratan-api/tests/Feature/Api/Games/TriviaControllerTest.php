<?php

namespace Tests\Feature\Api\Games;

use App\Models\TriviaQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriviaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_questions_does_not_leak_correct_answer_index(): void
    {
        TriviaQuestion::create([
            'pertanyaan' => 'Berapa jumlah Sindrom?', 'pilihan' => ['8', '5', '12', '3'],
            'jawaban_benar_index' => 0, 'penjelasan' => 'x',
        ]);

        $response = $this->getJson('/api/games/trivia/questions');

        $response->assertOk();
        $this->assertArrayNotHasKey('jawaban_benar_index', $response->json()[0]);
    }

    public function test_questions_only_returns_active_ones(): void
    {
        TriviaQuestion::create([
            'pertanyaan' => 'Aktif', 'pilihan' => ['a', 'b', 'c', 'd'], 'jawaban_benar_index' => 0, 'is_active' => true,
        ]);
        TriviaQuestion::create([
            'pertanyaan' => 'Nonaktif', 'pilihan' => ['a', 'b', 'c', 'd'], 'jawaban_benar_index' => 0, 'is_active' => false,
        ]);

        $response = $this->getJson('/api/games/trivia/questions');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_check_computes_score_server_side_from_real_answers(): void
    {
        $correct = TriviaQuestion::create([
            'pertanyaan' => 'Q1', 'pilihan' => ['a', 'b', 'c', 'd'], 'jawaban_benar_index' => 0, 'penjelasan' => 'jelasan1',
        ]);
        $wrong = TriviaQuestion::create([
            'pertanyaan' => 'Q2', 'pilihan' => ['a', 'b', 'c', 'd'], 'jawaban_benar_index' => 2, 'penjelasan' => 'jelasan2',
        ]);

        $response = $this->postJson('/api/games/trivia/check', [
            'answers' => [
                ['question_id' => $correct->id, 'chosen_index' => 0],
                ['question_id' => $wrong->id, 'chosen_index' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('skor', 1)
            ->assertJsonPath('total', 2);
    }

    public function test_check_rejects_score_client_could_fabricate(): void
    {
        // Kirim jawaban yang SEMUA salah - skor server harus 0 walau
        // klien "berharap" skor tinggi (membuktikan skor tidak dipercaya
        // dari input klien sama sekali).
        $question = TriviaQuestion::create([
            'pertanyaan' => 'Q1', 'pilihan' => ['a', 'b', 'c', 'd'], 'jawaban_benar_index' => 0,
        ]);

        $response = $this->postJson('/api/games/trivia/check', [
            'answers' => [['question_id' => $question->id, 'chosen_index' => 3]],
        ]);

        $response->assertOk()->assertJsonPath('skor', 0);
    }
}
