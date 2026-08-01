<?php

namespace App\Observers;

use App\Jobs\SendReportCompletedNotification;
use App\Models\AuditLog;
use App\Models\PersonalityReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PersonalityReportObserver
{
    /**
     * Handle the PersonalityReport "created" event.
     */
    public function created(PersonalityReport $personalityReport): void
    {
        $this->log('buat_laporan', $personalityReport);
    }

    /**
     * Handle the PersonalityReport "updated" event.
     */
    public function updated(PersonalityReport $personalityReport): void
    {
        if ($personalityReport->wasChanged('status') && $personalityReport->status === 'completed') {
            $this->log('laporan_selesai', $personalityReport);
            SendReportCompletedNotification::dispatch($personalityReport);
        }
    }

    /**
     * Handle the PersonalityReport "deleted" event.
     */
    public function deleted(PersonalityReport $personalityReport): void
    {
        $this->log('hapus_laporan', $personalityReport);
    }

    private function log(string $aksi, PersonalityReport $personalityReport): void
    {
        AuditLog::record(
            $aksi,
            PersonalityReport::class,
            $personalityReport->id,
            Auth::id(),
            Request::ip(),
        );
    }
}
