<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use App\Support\CsvStreamer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gated by 'role:administrator'. Rekap performa grafolog (laporan selesai +
 * rata-rata durasi pengerjaan) - pola agregasi sama dengan
 * Admin\CompanyController::index() (per-company), di sini per-grafolog,
 * lewat PersonalityReport::avgTurnaroundDaysFor(). Lihat guratan-api/CLAUDE.md.
 */
class GrafologRecapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $grafologs = $this->filteredQuery($request)->latest()->paginate(25);
        $grafologs->getCollection()->transform(fn (User $g) => $this->withStats($g));

        return response()->json($grafologs);
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLog::record('ekspor_rekap_grafolog', User::class, null, $request->user()->id, $request->ip());

        $rows = $this->filteredQuery($request)->cursor()->map(function (User $grafolog) {
            $grafolog = $this->withStats($grafolog);

            return [
                $grafolog->id,
                $grafolog->name,
                $grafolog->email,
                $grafolog->is_active ? 'aktif' : 'nonaktif',
                $grafolog->token_balance,
                $grafolog->completed_reports,
                $grafolog->avg_turnaround_days ?? '',
                $grafolog->created_at->format('Y-m-d H:i'),
            ];
        });

        return CsvStreamer::download(
            'rekap-grafolog-'.now()->format('Y-m-d').'.csv',
            ['ID', 'Nama', 'Email', 'Status', 'Sisa Token', 'Laporan Selesai', 'Rata-rata Durasi (hari)', 'Terdaftar'],
            $rows,
        );
    }

    private function withStats(User $grafolog): User
    {
        $sampleIds = HandwritingSample::where('created_by', $grafolog->id)->pluck('id');

        $grafolog->completed_reports = PersonalityReport::whereIn('sample_id', $sampleIds)
            ->where('status', 'completed')->count();
        $grafolog->avg_turnaround_days = PersonalityReport::avgTurnaroundDaysFor($sampleIds);

        return $grafolog;
    }

    private function filteredQuery(Request $request): Builder
    {
        return User::query()
            ->where('role', 'grafolog')
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', (string) $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', (string) $request->input('to')))
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($qq) => $qq->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('email', 'like', '%'.$request->input('search').'%')
            ));
    }
}
