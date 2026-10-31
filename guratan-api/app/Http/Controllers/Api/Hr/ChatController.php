<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SendChatMessageRequest;
use App\Models\HrChatConversation;
use App\Services\B2b\HrChatService;
use App\Services\Llm\LlmUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Asisten data HR. Percakapan milik akun HR yang membuatnya saja. */
class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            HrChatConversation::where('user_id', $request->user()->id)->latest('updated_at')->get(['id', 'title', 'created_at', 'updated_at'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->company_id === null, 422, 'Akun HR Anda belum terikat ke perusahaan.');

        $conversation = HrChatConversation::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'title' => $request->validate(['title' => ['nullable', 'string', 'max:120']])['title'] ?? null,
        ]);

        return response()->json($conversation, 201);
    }

    public function show(Request $request, HrChatConversation $conversation): JsonResponse
    {
        $this->own($request, $conversation);

        return response()->json([
            ...$conversation->toArray(),
            'messages' => $conversation->messages()->orderBy('id')->get(['id', 'role', 'content', 'created_at']),
        ]);
    }

    public function sendMessage(SendChatMessageRequest $request, HrChatConversation $conversation, HrChatService $chat): JsonResponse
    {
        $this->own($request, $conversation);

        try {
            $message = $chat->sendMessage($conversation, $request->validated('message'));
        } catch (LlmUnavailableException $e) {
            Log::warning('Chat HR gagal', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Asisten chat sedang tidak tersedia, coba lagi nanti.'], 503);
        }

        $conversation->touch();

        return response()->json($message, 201);
    }

    public function destroy(Request $request, HrChatConversation $conversation): JsonResponse
    {
        $this->own($request, $conversation);
        $conversation->delete();

        return response()->json(null, 204);
    }

    private function own(Request $request, HrChatConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403, 'Percakapan ini bukan milik Anda.');
    }
}
