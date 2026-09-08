<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\SendChatMessageRequest;
use App\Models\SupervisorChatConversation;
use App\Services\Supervisor\SupervisorChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Chat interaktif Supervisor (fitur Supervisor Fase 3, 2026-09-08) - lihat
 * CLAUDE.md "Peran Supervisor". Percakapan MILIK Supervisor yang login
 * SAJA - keputusan desain eksplisit, sekalipun Supervisor lain 1 company
 * yang sama tidak boleh lihat/pakai percakapan ini (kerja personal, bukan
 * dokumen resmi perusahaan).
 */
class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = SupervisorChatConversation::where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->company_id === null, 422, 'Akun Supervisor Anda belum terikat ke perusahaan.');

        $conversation = SupervisorChatConversation::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'title' => $request->input('title'),
        ]);

        return response()->json($conversation, 201);
    }

    public function show(Request $request, SupervisorChatConversation $conversation): JsonResponse
    {
        $this->authorizeOwnership($request, $conversation);

        return response()->json([
            ...$conversation->toArray(),
            'messages' => $conversation->messages()->orderBy('id')->get(['id', 'role', 'content', 'created_at']),
        ]);
    }

    public function sendMessage(
        SendChatMessageRequest $request,
        SupervisorChatConversation $conversation,
        SupervisorChatService $chatService
    ): JsonResponse {
        $this->authorizeOwnership($request, $conversation);

        try {
            $message = $chatService->sendMessage($conversation, $request->validated('message'), $request->user());
        } catch (RuntimeException $e) {
            Log::error('Chat Supervisor gagal', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Asisten chat sedang tidak tersedia, coba lagi nanti.',
            ], 503);
        }

        $conversation->touch();

        return response()->json($message, 201);
    }

    public function destroy(Request $request, SupervisorChatConversation $conversation): JsonResponse
    {
        $this->authorizeOwnership($request, $conversation);

        $conversation->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwnership(Request $request, SupervisorChatConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403, 'Percakapan ini bukan milik Anda.');
    }
}
