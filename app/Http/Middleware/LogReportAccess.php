<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\PersonalityReport;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data psikologis di personality_reports sensitif - setiap akses baca
 * (bukan cuma tulis, yang sudah dicatat lewat PersonalityReportObserver)
 * wajib tercatat di audit_logs, terlepas dari hasil otorisasinya.
 */
class LogReportAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            $this->log($request, $response->getStatusCode());

            return $response;
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $this->log($request, $status);
            throw $e;
        }
    }

    private function log(Request $request, int $status): void
    {
        $report = $request->route('report');
        $reportId = $report instanceof PersonalityReport ? $report->id : (int) $report;

        AuditLog::record(
            $status < 400 ? 'lihat_laporan' : 'lihat_laporan_ditolak',
            PersonalityReport::class,
            $reportId,
            $request->user()?->id,
            $request->ip(),
        );
    }
}
