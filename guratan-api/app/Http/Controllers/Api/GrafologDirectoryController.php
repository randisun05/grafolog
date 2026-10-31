<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrafologAvailability;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Direktori grafolog untuk klien memilih (portofolio lengkap lewat GET /cv/users/{id}). */
class GrafologDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'q' => ['nullable', 'string', 'max:60'], 'all' => ['nullable', 'boolean']]);
        $month = $f['month'] ?? now()->format('Y-m');
        $needle = mb_strtolower($f['q'] ?? '');

        $list = User::where('role', 'grafolog')->where(fn ($q) => $q->whereNull('grafolog_status')->orWhere('grafolog_status', 'verified'))
            ->withCount(['assignments as completed_assignments' => fn ($q) => $q->where('status', 'completed')])
            ->orderBy('name')->get(['id', 'name', 'city', 'bio', 'specializations', 'experience_years'])
            ->map(fn (User $g) => $g->toArray() + ['availability' => GrafologAvailability::forMonth($g, $month)])
            ->filter(fn ($g) => $request->boolean('all') || $g['availability']['bookable'])
            ->filter(fn ($g) => $needle === '' || str_contains(mb_strtolower($g['name'].' '.$g['city'].' '.implode(' ', (array) $g['specializations'])), $needle))
            ->values();

        return response()->json(['month' => $month, 'grafologs' => $list]);
    }
}
