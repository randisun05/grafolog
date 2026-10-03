<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HandwritingSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pesanan Masuk: sample yang dipesan klien sendiri (Project.source=client).
 * Admin memilih grafolog lewat POST /api/samples/{id}/assignment yang
 * sudah ada. Filter `status`: unassigned (lunas, belum ada grafolog),
 * unpaid, in_progress, completed, atau all.
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:unassigned,unpaid,in_progress,completed,all']]);
        $status = $request->query('status', 'unassigned');

        $paid = fn ($q) => $q->where('status', 'paid');

        $orders = HandwritingSample::query()
            ->whereHas('project', fn ($q) => $q->where('source', 'client'))
            ->when($status === 'unassigned', fn ($q) => $q->whereHas('payments', $paid)->doesntHave('assignment')->where('status', '!=', 'completed'))
            ->when($status === 'unpaid', fn ($q) => $q->whereDoesntHave('payments', $paid))
            ->when($status === 'in_progress', fn ($q) => $q->has('assignment')->where('status', '!=', 'completed'))
            ->when($status === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->with(['user:id,name,email', 'assignment.grafolog:id,name,email'])
            ->withCount('images')
            ->withExists(['payments as is_paid' => $paid])
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }
}
