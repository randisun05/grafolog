<?php

namespace App\Http\Controllers\Api\Games;

use App\Http\Controllers\Controller;
use App\Models\TriviaQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Game "Trivia Grafologi" - lihat guratan-api/CLAUDE.md "Konten publik —
 * Fase 3". Publik, tanpa login.
 */
class TriviaController extends Controller
{
    /**
     * Balas N soal acak TANPA jawaban_benar_index - itu cuma dipakai
     * server-side saat check().
     */
    public function questions(): JsonResponse
    {
        $questions = TriviaQuestion::where('is_active', true)
            ->inRandomOrder()
            ->limit(10)
            ->get(['id', 'pertanyaan', 'pilihan']);

        return response()->json($questions);
    }

    /**
     * Skor dihitung SERVER-SIDE dari jawaban yang dikirim - frontend
     * pakai `skor` hasil hitungan ini untuk submit ke leaderboard, bukan
     * hitungan sendiri (mencegah cara curang paling gampang).
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:trivia_questions,id'],
            'answers.*.chosen_index' => ['required', 'integer', 'between:0,3'],
        ]);

        $questions = TriviaQuestion::whereIn('id', collect($data['answers'])->pluck('question_id'))
            ->get()
            ->keyBy('id');

        $detail = collect($data['answers'])->map(function ($answer) use ($questions) {
            $question = $questions->get($answer['question_id']);
            $correct = $question->jawaban_benar_index === (int) $answer['chosen_index'];

            return [
                'question_id' => $question->id,
                'correct' => $correct,
                'jawaban_benar_index' => $question->jawaban_benar_index,
                'penjelasan' => $question->penjelasan,
            ];
        });

        return response()->json([
            'skor' => $detail->where('correct', true)->count(),
            'total' => $detail->count(),
            'detail' => $detail->values(),
        ]);
    }
}
