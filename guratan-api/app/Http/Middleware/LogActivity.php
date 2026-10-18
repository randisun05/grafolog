<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pencatat aktivitas ringan (lihat config/activity.php). Berjalan di `terminate` (setelah respons
 * terkirim) dan tidak pernah mengganggu request: kegagalan menulis log hanya ditelan.
 */
class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('activity.enabled') || $request->isMethod('OPTIONS')) {
            return;
        }

        try {
            $this->record($request, $response);
        } catch (Throwable) {
            // Log aktivitas tidak boleh menjatuhkan layanan.
        }
    }

    private function record(Request $request, Response $response): void
    {
        $path = $request->path();
        $isRead = $request->isMethod('GET') || $request->isMethod('HEAD');

        if (($isRead && ! config('activity.log_reads')) || $request->is(...config('activity.skip', []))) {
            return;
        }

        $event = 'request';
        $userId = $request->user('sanctum')?->id;

        if ($request->is('api/auth/login')) {
            $event = $response->getStatusCode() < 300 ? 'login' : 'login_gagal';
            $userId = $event === 'login' ? (json_decode((string) $response->getContent(), true)['user']['id'] ?? null) : null;
        } elseif ($request->is('api/auth/logout')) {
            $event = 'logout';
        } elseif ($userId === null) {
            return; // permintaan anonim lain (mis. cek tulisan publik) tidak dicatat
        }

        ActivityLog::create([
            'user_id' => $userId,
            'event' => $event,
            'method' => $request->method(),
            'path' => mb_substr('/'.$path, 0, 255),
            'route' => mb_substr((string) $request->route()?->uri(), 0, 120),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 160),
        ]);
    }
}
