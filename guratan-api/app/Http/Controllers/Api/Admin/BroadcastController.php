<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:administrator'. Pesan massal ke kotak masuk (opsional + email) per role. */
class BroadcastController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Broadcast::latest('id')->limit(50)->get());
    }

    public function store(Request $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:user,grafolog,hr,administrator'],
            'send_email' => ['sometimes', 'boolean'],
            'is_critical' => ['sometimes', 'boolean'],
        ]);
        $broadcast = Broadcast::create([
            'title' => $data['title'], 'body' => $data['body'],
            'target_roles' => ($data['target_roles'] ?? []) ?: null, 'send_email' => ($data['send_email'] ?? false) || ($data['is_critical'] ?? false), 'is_critical' => $data['is_critical'] ?? false,
            'created_by' => $request->user()->id,
        ]);
        $count = $notifier->broadcast($broadcast);
        AuditLog::record('kirim_broadcast', Broadcast::class, $broadcast->id, $request->user()->id, $request->ip());

        return response()->json($broadcast->fresh()->toArray() + ['recipients' => $count], 201);
    }
}
