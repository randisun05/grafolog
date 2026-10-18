<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GrafologAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Grafolog mengatur ketersediaannya per bulan (bulan ini s.d. 5 bulan ke depan). */
class GrafologAvailabilityController extends Controller
{
    public const MONTHS_AHEAD = 5;

    public static function months(): array
    {
        return collect(range(0, self::MONTHS_AHEAD))->map(fn ($i) => now()->startOfMonth()->addMonths($i)->format('Y-m'))->all();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(collect(self::months())->map(fn ($m) => GrafologAvailability::forMonth($user, $m))->values());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', Rule::in(self::months())],
            'status' => ['required', Rule::in(GrafologAvailability::STATUSES)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $user = $request->user();

        GrafologAvailability::updateOrCreate(
            ['user_id' => $user->id, 'month' => $data['month']],
            ['status' => $data['status'], 'capacity' => $data['capacity'] ?? null, 'note' => $data['note'] ?? null],
        );
        AuditLog::record('atur_ketersediaan', GrafologAvailability::class, $user->id, $user->id, $request->ip());

        return response()->json(GrafologAvailability::forMonth($user, $data['month']));
    }
}
