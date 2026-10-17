<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceDelivery;
use App\Models\User;
use App\Services\Commerce\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Layanan pendukung (wawancara, konsultasi, konseling, intervensi) dari pesanan produk.
 * - index: grafolog melihat yang ditugaskan kepadanya; klien melihat miliknya; admin/supervisor semua.
 * - assign: admin (atau HR untuk pesanan B2B miliknya - Fase C) memilih grafolog terverifikasi.
 * - schedule / report: hanya grafolog yang ditugaskan.
 */
class ServiceDeliveryController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->validate(['status' => ['sometimes', Rule::in(ServiceDelivery::STATUS)]])['status'] ?? null;

        $q = ServiceDelivery::with(['component', 'user:id,name,email', 'assignee:id,name', 'order.product:id,kode,nama'])->latest();
        if ($user->isAdministrator() || $user->isSupervisor()) {
            // semua
        } elseif ($user->isGrafolog()) {
            $q->where('assigned_to', $user->id);
        } elseif ($user->isHr()) {
            $q->whereHas('order', fn ($o) => $o->where('created_by', $user->id)
                ->orWhereHas('batch', fn ($b) => $b->where('company_id', $user->company_id)));
        } else {
            $q->where('user_id', $user->id)->whereHas('order', fn ($o) => $o->whereNull('batch_id'));
        }
        if ($status) {
            $q->where('status', $status);
        }

        return response()->json($q->get());
    }

    public function show(Request $request, ServiceDelivery $delivery): JsonResponse
    {
        abort_unless($delivery->isViewableBy($request->user()), 403);

        return response()->json($delivery->load(['component', 'user:id,name,email', 'assignee:id,name', 'order.product:id,kode,nama']));
    }

    public function assign(Request $request, ServiceDelivery $delivery): JsonResponse
    {
        $user = $request->user();
        $batch = $delivery->order->batch;
        abort_unless($user->isAdministrator() || ($user->isHr() && ($batch
            ? $batch->company_id === $user->company_id
            : $delivery->order->created_by === $user->id)), 403);
        abort_if(in_array($delivery->status, ['selesai', 'dibatalkan'], true), 422, 'Layanan sudah ditutup.');
        $data = $request->validate(['grafolog_id' => ['required', 'integer', 'exists:users,id']]);
        $grafolog = User::findOrFail($data['grafolog_id']);
        abort_unless($grafolog->isGrafolog(), 422, 'Pengguna yang dipilih bukan grafolog terverifikasi.');

        $delivery->update(['assigned_to' => $grafolog->id, 'status' => $delivery->scheduled_at ? 'dijadwalkan' : 'ditugaskan']);
        AuditLog::record('tugaskan_layanan', ServiceDelivery::class, $delivery->id, $user->id, $request->ip());

        return response()->json($delivery->fresh(['assignee:id,name', 'component']));
    }

    public function schedule(Request $request, ServiceDelivery $delivery): JsonResponse
    {
        abort_unless($delivery->assigned_to === $request->user()->id, 403);
        abort_if(in_array($delivery->status, ['selesai', 'dibatalkan'], true), 422, 'Layanan sudah ditutup.');
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'meeting_url' => ['nullable', 'url:https', 'max:255'],
        ]);

        $delivery->update($data + ['status' => 'dijadwalkan']);
        AuditLog::record('jadwalkan_layanan', ServiceDelivery::class, $delivery->id, $request->user()->id, $request->ip());

        return response()->json($delivery->fresh(['component', 'user:id,name,email']));
    }

    /** Isi laporan sesuai report_fields komponen - semua field wajib. */
    public function report(Request $request, ServiceDelivery $delivery): JsonResponse
    {
        abort_unless($delivery->assigned_to === $request->user()->id, 403);
        abort_if($delivery->status === 'dibatalkan', 422, 'Layanan dibatalkan.');

        $fields = $delivery->component->report_fields ?? [];
        $rules = [];
        foreach ($fields as $f) {
            $rules["report.{$f['key']}"] = match ($f['type']) {
                'score' => ['required', 'integer', 'between:1,5'],
                'choice' => ['required', Rule::in($f['options'] ?? [])],
                default => ['required', 'string', 'max:5000'],
            };
        }
        $data = $request->validate(['report' => ['required', 'array']] + $rules);
        $report = collect($fields)->mapWithKeys(fn ($f) => [$f['key'] => $data['report'][$f['key']]])->all();

        $delivery->update(['report' => $report, 'report_submitted_at' => now(), 'status' => 'selesai']);
        $this->orders->refreshStatus($delivery->order);
        AuditLog::record('laporkan_layanan', ServiceDelivery::class, $delivery->id, $request->user()->id, $request->ip());

        return response()->json($delivery->fresh(['component', 'user:id,name,email']));
    }
}
