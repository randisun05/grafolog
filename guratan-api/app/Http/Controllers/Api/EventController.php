<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Publik (tanpa login), sama pola Api\ArticleController - cuma
 * status=published, draft/cancelled tidak pernah terlihat lewat jalur
 * ini. show() SENGAJA tidak menyertakan status pendaftaran user login -
 * frontend yang sudah login memanggil Api\EventRegistrationController::
 * mine() terpisah lalu cocokkan event id di client (menghindari
 * kerumitan optional-auth di rute publik).
 */
class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Event::query()
            ->where('status', 'published')
            ->when($request->input('when') === 'upcoming', fn ($q) => $q->where('starts_at', '>=', now()))
            ->when($request->input('when') === 'past', fn ($q) => $q->where('starts_at', '<', now()))
            ->orderBy('starts_at')
            ->paginate(9);

        return response()->json($events);
    }

    public function show(string $slug): JsonResponse
    {
        $event = Event::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($event);
    }
}
