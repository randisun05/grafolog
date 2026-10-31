<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\CsvStreamer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gated by 'role:administrator'. Nested di bawah Event, pola sama
 * CompanyContractController - daftar peserta 1 kegiatan tertentu, bukan
 * lintas-kegiatan. Data peserta dibaca lewat eager-load `user`, bukan
 * snapshot terpisah.
 */
class EventRegistrationController extends Controller
{
    public function index(Event $event): JsonResponse
    {
        $registrations = $event->registrations()
            ->where('status', 'registered')
            ->with('user:id,name,email,phone')
            ->orderBy('registered_at')
            ->get();

        return response()->json($registrations);
    }

    public function export(Request $request, Event $event): StreamedResponse
    {
        AuditLog::record('ekspor_peserta_kegiatan', Event::class, $event->id, $request->user()->id, $request->ip());

        $rows = $event->registrations()
            ->where('status', 'registered')
            ->with('user:id,name,email,phone')
            ->orderBy('registered_at')
            ->cursor()
            ->map(fn (EventRegistration $r) => [
                $r->user?->name ?? '',
                $r->user?->email ?? '',
                $r->user?->phone ?? '',
                $r->registered_at->format('Y-m-d H:i'),
            ]);

        return CsvStreamer::download(
            'peserta-'.$event->slug.'-'.now()->format('Y-m-d').'.csv',
            ['Nama', 'Email', 'Nomor WhatsApp', 'Terdaftar Pada'],
            $rows,
        );
    }
}
