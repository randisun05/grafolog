<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTriviaQuestionRequest;
use App\Http\Requests\Admin\UpdateTriviaQuestionRequest;
use App\Models\AuditLog;
use App\Models\TriviaQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator'. Tidak dipaginasi - daftar pendek yang
 * di-scan sekilas (pola sama AdminProductsView.vue), menyertakan soal
 * nonaktif juga supaya admin bisa aktifkan lagi kalau perlu.
 */
class TriviaQuestionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TriviaQuestion::latest()->get());
    }

    public function store(StoreTriviaQuestionRequest $request): JsonResponse
    {
        $question = TriviaQuestion::create($request->validated());

        AuditLog::record('buat_trivia', TriviaQuestion::class, $question->id, $request->user()->id, $request->ip());

        return response()->json($question, 201);
    }

    public function update(UpdateTriviaQuestionRequest $request, TriviaQuestion $triviaQuestion): JsonResponse
    {
        $triviaQuestion->update($request->validated());

        AuditLog::record('ubah_trivia', TriviaQuestion::class, $triviaQuestion->id, $request->user()->id, $request->ip());

        return response()->json($triviaQuestion);
    }

    public function destroy(Request $request, TriviaQuestion $triviaQuestion): JsonResponse
    {
        $id = $triviaQuestion->id;
        $triviaQuestion->delete();

        AuditLog::record('hapus_trivia', TriviaQuestion::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Soal trivia dihapus.']);
    }
}
