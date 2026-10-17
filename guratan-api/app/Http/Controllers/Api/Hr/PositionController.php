<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePositionRequest;
use App\Models\AuditLog;
use App\Models\JobPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:hr'. Posisi lowongan perusahaan HR. */
class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            JobPosition::where('company_id', $request->user()->company_id)
                ->withCount('candidates')
                ->orderByDesc('is_active')->latest()
                ->get()
        );
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $user = $request->user();
        $position = JobPosition::create($request->validated() + [
            'company_id' => $user->company_id,
            'created_by' => $user->id,
        ]);
        AuditLog::record('buat_posisi', JobPosition::class, $position->id, $user->id, $request->ip());

        return response()->json($position, 201);
    }

    public function update(StorePositionRequest $request, JobPosition $position): JsonResponse
    {
        abort_unless($position->company_id === $request->user()->company_id, 404);
        $position->update($request->validated());
        AuditLog::record('ubah_posisi', JobPosition::class, $position->id, $request->user()->id, $request->ip());

        return response()->json($position);
    }
}
