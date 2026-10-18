<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:administrator'. Penelusuran log aktivitas (lihat config/activity.php). */
class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'in:request,login,login_gagal,logout'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json(
            ActivityLog::with('user:id,name,email,role')
                ->when($f['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
                ->when($f['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                ->when($f['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
                ->when($f['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
                ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                    ->where('path', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))))
                ->latest('id')->paginate(50)
        );
    }
}
