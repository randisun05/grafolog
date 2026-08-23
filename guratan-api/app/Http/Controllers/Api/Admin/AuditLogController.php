<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator' on its routes. Read-only - AuditLog::record()
 * is called from ~45 sites across the app (pricing/discount/content/token/KB
 * changes, score corrections, report access, ...), but until this controller
 * existed there was NO way to read any of it back except a manual DB query.
 * See ROADMAP.md "Kesiapan Publikasi" for why this was flagged as a real gap.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($request->filled('aksi'), fn ($q) => $q->where('aksi', 'like', '%'.$request->input('aksi').'%'))
            ->when($request->filled('actor_user_id'), fn ($q) => $q->where('actor_user_id', $request->integer('actor_user_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', (string) $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', (string) $request->input('to')))
            ->latest()
            ->paginate(25);

        return response()->json($logs);
    }
}
