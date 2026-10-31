<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GrafologBankAccount;
use App\Models\GrafologEarning;
use App\Models\GrafologPayout;
use App\Models\Product;
use App\Models\ProductCommission;
use App\Models\User;
use App\Services\Commerce\EarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gated 'role:administrator'. Komisi grafolog: ringkasan, pencairan, dan override per produk per grafolog. */
class EarningAdminController extends Controller
{
    public function __construct(private EarningService $earnings) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['status' => ['nullable', 'in:diperoleh,dibayar,dibatalkan'], 'grafolog_id' => ['nullable', 'integer']]);
        $q = GrafologEarning::query()->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['grafolog_id'] ?? null, fn ($q, $id) => $q->where('grafolog_id', $id));

        $perGrafolog = GrafologEarning::selectRaw("grafolog_id, sum(case when status = 'diperoleh' then amount else 0 end) as menunggu, sum(case when status = 'dibayar' then amount else 0 end) as dibayar")
            ->groupBy('grafolog_id')->with('grafolog:id,name')->get()
            ->map(fn ($r) => ['grafolog_id' => $r->grafolog_id, 'name' => $r->grafolog?->name, 'bank' => ($b = GrafologBankAccount::where('user_id', $r->grafolog_id)->first()) ? $b->bank_name.' '.$b->masked() : null, 'menunggu' => (int) $r->menunggu, 'dibayar' => (int) $r->dibayar]);

        return response()->json([
            'per_grafolog' => $perGrafolog->sortByDesc('menunggu')->values(),
            'earnings' => $q->with('grafolog:id,name', 'product:id,nama')->latest('id')->limit(200)->get()->map(fn ($e) => [
                'id' => $e->id, 'grafolog' => $e->grafolog?->name, 'grafolog_id' => $e->grafolog_id, 'order_id' => $e->order_id, 'product' => $e->product?->nama,
                'base_amount' => $e->base_amount, 'amount' => $e->amount, 'status' => $e->status, 'note' => $e->note, 'earned_at' => $e->earned_at,
            ]),
        ]);
    }

    public function payout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grafolog_id' => ['required', 'integer', 'exists:users,id'], 'earning_ids' => ['nullable', 'array', 'min:1'], 'earning_ids.*' => ['integer'],
            'reference' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $payout = $this->earnings->payout(User::findOrFail($data['grafolog_id']), $data['earning_ids'] ?? null, $request->user(), $data['reference'] ?? null, $data['note'] ?? null);
        AuditLog::record('cairkan_komisi', GrafologPayout::class, $payout->id, $request->user()->id, $request->ip());

        return response()->json($payout, 201);
    }

    /** Rekening lengkap hanya untuk mencatat pencairan; setiap pembukaan tercatat audit. */
    public function bankAccount(Request $request, User $grafolog): JsonResponse
    {
        $b = GrafologBankAccount::where('user_id', $grafolog->id)->first();
        abort_unless($b, 404, 'Grafolog belum mengisi rekening.');
        AuditLog::record('lihat_rekening_grafolog', GrafologBankAccount::class, $b->id, $request->user()->id, $request->ip());

        return response()->json(['bank_name' => $b->bank_name, 'account_number' => $b->account_number, 'account_holder' => $b->account_holder]);
    }

    public function commissions(Product $product): JsonResponse
    {
        return response()->json([
            'default' => ['type' => $product->commission_type, 'value' => $product->commission_value, 'token_cost' => $product->token_cost],
            'overrides' => ProductCommission::where('product_id', $product->id)->with('grafolog:id,name')->get()
                ->map(fn ($c) => ['grafolog_id' => $c->grafolog_id, 'grafolog' => $c->grafolog?->name, 'type' => $c->commission_type, 'value' => $c->commission_value]),
        ]);
    }

    public function setOverride(Request $request, Product $product, User $grafolog): JsonResponse
    {
        abort_unless($grafolog->role === 'grafolog', 422, 'Bukan grafolog.');
        $data = $request->validate(['type' => ['required', Rule::in(Product::COMMISSION_TYPES)], 'value' => ['required', 'integer', 'min:0', $request->input('type') === 'percent' ? 'max:100' : 'max:100000000']]);
        $row = ProductCommission::updateOrCreate(['product_id' => $product->id, 'grafolog_id' => $grafolog->id], ['commission_type' => $data['type'], 'commission_value' => $data['value']]);
        AuditLog::record('ubah_komisi_grafolog_produk', ProductCommission::class, $row->id, $request->user()->id, $request->ip());

        return response()->json($row);
    }

    public function removeOverride(Request $request, Product $product, User $grafolog): JsonResponse
    {
        ProductCommission::where('product_id', $product->id)->where('grafolog_id', $grafolog->id)->delete();
        AuditLog::record('hapus_komisi_grafolog_produk', Product::class, $product->id, $request->user()->id, $request->ip());

        return response()->json(null, 204);
    }
}
