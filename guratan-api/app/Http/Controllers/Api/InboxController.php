<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kotak masuk notifikasi pribadi (2026-10-18) - milik pengguna yang login saja. */
class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = InboxMessage::where('user_id', $user->id)->latest('id');
        if ($request->boolean('unread')) {
            $q->whereNull('read_at');
        }

        return response()->json([
            'unread' => InboxMessage::where('user_id', $user->id)->whereNull('read_at')->count(),
            'messages' => $q->paginate(30),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread' => InboxMessage::where('user_id', $request->user()->id)->whereNull('read_at')->count()]);
    }

    public function read(Request $request, InboxMessage $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 404);
        $message->update(['read_at' => $message->read_at ?? now()]);

        return response()->json($message);
    }

    public function readAll(Request $request): JsonResponse
    {
        InboxMessage::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }
}
