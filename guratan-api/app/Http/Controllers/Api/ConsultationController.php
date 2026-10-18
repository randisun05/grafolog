<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sample\UpsertConsultationRequest;
use App\Mail\ConsultationScheduledMail;
use App\Models\AuditLog;
use App\Models\HandwritingSample;
use App\Models\SampleConsultation;
use App\Services\Notifications\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ConsultationController extends Controller
{
    public function upsert(UpsertConsultationRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isGrafolog() && $sample->isScorableBy($user), 403, 'Hanya grafolog yang menangani sample ini.');
        abort_unless($sample->tier === 'master', 422, 'Sesi konsultasi hanya tersedia untuk tier Master.');

        $consultation = SampleConsultation::updateOrCreate(
            ['sample_id' => $sample->id],
            [...$request->validated(), 'scheduled_by' => $user->id],
        );

        AuditLog::record('jadwalkan_konsultasi', SampleConsultation::class, $consultation->id, $user->id, $request->ip());

        $scheduleChanged = $consultation->wasRecentlyCreated || $consultation->wasChanged(['scheduled_at', 'meeting_url']);
        if ($scheduleChanged && $consultation->status === 'dijadwalkan' && $sample->user) {
            Mail::to($sample->user->email)->queue(new ConsultationScheduledMail($consultation));
            app(Notifier::class)->send($sample->user, 'jadwal_layanan', 'Sesi konsultasi dijadwalkan', 'Jadwal konsultasi hasil Anda sudah ditetapkan - lihat di halaman laporan.', "/reports/{$sample->reports()->latest('id')->value('id')}", mail: false);
        }

        return response()->json($consultation, $consultation->wasRecentlyCreated ? 201 : 200);
    }
}
