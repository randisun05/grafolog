<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'auth:sanctum' on its routes - siapa pun yang login boleh
 * daftar kegiatan (bukan cuma role tertentu). Satu baris per pasangan
 * (event, user), `updateOrCreate` menangani daftar-ulang setelah batal.
 */
class EventRegistrationController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        abort_unless($event->status === 'published', 422, 'Kegiatan ini tidak menerima pendaftaran.');
        abort_if($event->spots_remaining === 0, 422, 'Kegiatan sudah penuh.');

        $registration = $event->registrations()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['status' => 'registered', 'registered_at' => now()],
        );

        AuditLog::record('daftar_kegiatan', Event::class, $event->id, $request->user()->id, $request->ip());

        return response()->json($registration, 201);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();
        $registration->update(['status' => 'cancelled']);

        AuditLog::record('batal_kegiatan', Event::class, $event->id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Pendaftaran dibatalkan.']);
    }

    public function mine(Request $request): JsonResponse
    {
        $registrations = EventRegistration::where('user_id', $request->user()->id)
            ->where('status', 'registered')
            ->with('event')
            ->get();

        return response()->json($registrations);
    }
}
