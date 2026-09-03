<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Support\CsvStreamer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gated by 'role:administrator'. Rekap pembelian laporan (Comprehensive/
 * Master) - daftar terfilter + export CSV atas Payment. Lihat
 * guratan-api/CLAUDE.md.
 */
class PaymentRecapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = $this->filteredQuery($request)->latest()->paginate(25);

        return response()->json($payments);
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLog::record('ekspor_rekap_pembayaran', Payment::class, null, $request->user()->id, $request->ip());

        $rows = $this->filteredQuery($request)->cursor()->map(fn (Payment $p) => [
            $p->id,
            $p->invoice_number,
            $p->sample?->user?->name ?? '',
            $p->sample?->user?->email ?? '',
            $p->sample?->tier ?? '',
            $p->base_amount,
            $p->discountCode?->code ?? '',
            $p->amount,
            $p->status,
            $p->paid_at?->format('Y-m-d H:i') ?? '',
            $p->created_at->format('Y-m-d H:i'),
        ]);

        return CsvStreamer::download(
            'rekap-pembelian-laporan-'.now()->format('Y-m-d').'.csv',
            ['ID', 'No. Invoice', 'Nama', 'Email', 'Tier', 'Subtotal', 'Kode Diskon', 'Total Dibayar', 'Status', 'Dibayar Pada', 'Dibuat'],
            $rows,
        );
    }

    private function filteredQuery(Request $request): Builder
    {
        return Payment::query()
            ->with(['sample.user:id,name,email', 'discountCode:id,code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', (string) $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', (string) $request->input('to')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('sample.user', fn ($qq) => $qq
                ->where('name', 'like', '%'.$request->input('search').'%')
                ->orWhere('email', 'like', '%'.$request->input('search').'%')));
    }
}
