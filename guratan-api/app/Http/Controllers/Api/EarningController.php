<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GrafologBankAccount;
use App\Models\GrafologEarning;
use App\Models\GrafologPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:grafolog'. Pendapatan (komisi) milik sendiri. */
class EarningController extends Controller
{
    public function bank(Request $request): JsonResponse
    {
        $b = GrafologBankAccount::where('user_id', $request->user()->id)->first();

        return response()->json($b ? ['bank_name' => $b->bank_name, 'account_holder' => $b->account_holder, 'account_number' => $b->account_number] : null);
    }

    public function saveBank(Request $request): JsonResponse
    {
        $d = $request->validate([
            'bank_name' => ['required', 'string', 'max:80'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{5,30}$/'],
            'account_holder' => ['required', 'string', 'max:120'],
        ], ['account_number.regex' => 'Nomor rekening hanya angka (5-30 digit).']);
        GrafologBankAccount::updateOrCreate(['user_id' => $request->user()->id], $d);
        AuditLog::record('ubah_rekening_pencairan', GrafologBankAccount::class, $request->user()->id, $request->user()->id, $request->ip());

        return $this->bank($request);
    }

    public function index(Request $request): JsonResponse
    {
        $id = $request->user()->id;
        $sum = fn (string $status) => (int) GrafologEarning::where('grafolog_id', $id)->where('status', $status)->sum('amount');

        return response()->json([
            'totals' => ['diperoleh' => $sum(GrafologEarning::EARNED), 'dibayar' => $sum(GrafologEarning::PAID)],
            'earnings' => GrafologEarning::where('grafolog_id', $id)->with('product:id,nama')->latest('id')->limit(100)->get()
                ->map(fn ($e) => ['id' => $e->id, 'order_id' => $e->order_id, 'product' => $e->product?->nama, 'base_amount' => $e->base_amount,
                    'rule' => $e->commission_type === 'fixed' ? 'Rp '.number_format($e->commission_value, 0, ',', '.') : $e->commission_value.'%',
                    'amount' => $e->amount, 'status' => $e->status, 'note' => $e->note, 'earned_at' => $e->earned_at]),
            'bank' => ($b = GrafologBankAccount::where('user_id', $id)->first()) ? ['bank_name' => $b->bank_name, 'masked' => $b->masked()] : null,
            'payouts' => GrafologPayout::where('grafolog_id', $id)->latest('id')->limit(20)->get(['id', 'total', 'reference', 'note', 'paid_at']),
        ]);
    }
}
