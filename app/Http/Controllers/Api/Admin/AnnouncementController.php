<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementRequest;
use App\Http\Requests\Admin\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:administrator' on its routes.
 */
class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Announcement::latest()->paginate(20));
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $announcement = Announcement::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('buat_pengumuman', Announcement::class, $announcement->id, $request->user()->id, $request->ip());

        return response()->json($announcement, 201);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $announcement->update($request->validated());

        AuditLog::record('ubah_pengumuman', Announcement::class, $announcement->id, $request->user()->id, $request->ip());

        return response()->json($announcement);
    }
}
