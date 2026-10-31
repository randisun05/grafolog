<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\CsvStreamer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gated by 'role:administrator'. Roster lintas-role (user/grafolog/
 * administrator/supervisor/hr) dengan filter + export CSV - beda dari
 * AdminUsersView.vue's tabel staf yang cuma CRUD, di sini murni pembacaan
 * terfilter untuk kebutuhan rekap. Lihat guratan-api/CLAUDE.md.
 */
class UserRecapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = $this->filteredQuery($request)->latest()->paginate(25);

        return response()->json($users);
    }

    /**
     * Export dicatat di audit log (beda dari index() yang cuma menampilkan
     * di layar) - export adalah ekstraksi data massal (email dkk), bukan
     * sekadar melihat tabel, konsisten dengan log.report_access yang juga
     * mencatat setiap PEMBACAAN laporan sensitif.
     */
    public function export(Request $request): StreamedResponse
    {
        AuditLog::record('ekspor_rekap_pengguna', User::class, null, $request->user()->id, $request->ip());

        $rows = $this->filteredQuery($request)->cursor()->map(fn (User $user) => [
            $user->id,
            $user->name,
            $user->email,
            $user->role,
            $user->company?->name ?? '',
            $user->is_active ? 'aktif' : 'nonaktif',
            $user->created_at->format('Y-m-d H:i'),
        ]);

        return CsvStreamer::download(
            'rekap-pengguna-'.now()->format('Y-m-d').'.csv',
            ['ID', 'Nama', 'Email', 'Role', 'Perusahaan', 'Status', 'Terdaftar'],
            $rows,
        );
    }

    private function filteredQuery(Request $request): Builder
    {
        return User::query()
            ->with('company:id,name')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', (string) $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', (string) $request->input('to')))
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($qq) => $qq->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('email', 'like', '%'.$request->input('search').'%')
            ));
    }
}
