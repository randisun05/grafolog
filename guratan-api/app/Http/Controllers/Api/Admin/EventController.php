<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:administrator'. Beda dari Api\EventController (publik,
 * cuma status=published) - controller ini menampilkan SEMUA status
 * termasuk draft/cancelled, untuk keperluan admin. Pola sama persis
 * Admin\ArticleController (slug sekali saat dibuat, tidak berubah lagi).
 */
class EventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = Event::query()->latest('starts_at')->paginate(20);

        return response()->json($events);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = Event::generateUniqueSlug($data['title']);
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('event-covers', 'public');
        }

        $event = Event::create($data);

        AuditLog::record('buat_kegiatan', Event::class, $event->id, $request->user()->id, $request->ip());

        return response()->json($event, 201);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('event-covers', 'public');
        }

        $event->update($data);

        AuditLog::record('ubah_kegiatan', Event::class, $event->id, $request->user()->id, $request->ip());

        return response()->json($event);
    }
}
