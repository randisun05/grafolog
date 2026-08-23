<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated (not public like PricingController/ContentController) -
 * visibility depends on knowing the requesting user's role. Commerce
 * Fase F, extended 2026-08-23 with per-user persistent read state (a
 * notification-bell inbox, not just a session-local dismiss) - see
 * ROADMAP.md "Notifikasi/Pengumuman/Promo". Filtered in PHP via
 * Announcement::isVisibleTo() rather than a DB query - announcement
 * volume is expected to stay small, and this way there's exactly one
 * place deciding "is this visible," same principle as
 * DiscountCode::isValidFor().
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $visible = Announcement::query()
            ->latest()
            ->get()
            ->filter(fn (Announcement $a) => $a->isVisibleTo($user))
            ->values();

        $readIds = AnnouncementRead::where('user_id', $user->id)
            ->whereIn('announcement_id', $visible->pluck('id'))
            ->pluck('announcement_id')
            ->all();

        $data = $visible->map(fn (Announcement $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'created_at' => $a->created_at,
            'is_read' => in_array($a->id, $readIds, true),
        ]);

        return response()->json([
            'data' => $data,
            'unread_count' => $data->where('is_read', false)->count(),
        ]);
    }

    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->isVisibleTo($request->user()), 404);

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return response()->json(['message' => 'Ditandai sudah dibaca.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $visibleIds = Announcement::all()
            ->filter(fn (Announcement $a) => $a->isVisibleTo($user))
            ->pluck('id');

        foreach ($visibleIds as $id) {
            AnnouncementRead::updateOrCreate(
                ['announcement_id' => $id, 'user_id' => $user->id],
                ['read_at' => now()],
            );
        }

        return response()->json(['message' => 'Semua ditandai sudah dibaca.']);
    }
}
