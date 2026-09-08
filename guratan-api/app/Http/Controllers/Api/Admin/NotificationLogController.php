<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. Read-only monitoring untuk
 * NotificationLog - lihat migrasi `create_notification_logs_table` dan
 * `app/Services/NotificationDispatcher.php` untuk dari mana baris-baris
 * ini berasal. Pola sama persis AuditLogController (filter + paginate,
 * tidak ada store/update/destroy - append-only lewat dispatcher, bukan
 * lewat endpoint ini).
 */
class NotificationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = NotificationLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', (string) $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', (string) $request->input('to')))
            ->latest()
            ->paginate(25);

        return response()->json($logs);
    }
}
