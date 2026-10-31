<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrafologEarning;
use App\Models\GrafologPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:grafolog'. Pendapatan (komisi) milik sendiri. */
class EarningController extends Controller
{
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
            'payouts' => GrafologPayout::where('grafolog_id', $id)->latest('id')->limit(20)->get(['id', 'total', 'reference', 'note', 'paid_at']),
        ]);
    }
}
