<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\HandwritingSample;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:hr,administrator' on its route. HR can only assign samples
 * they own (created_by - true for every HR-imported candidate, see
 * CandidateImportController); Administrator can assign any sample, matching
 * its cross-project oversight role.
 */
class AssignmentController extends Controller
{
    public function store(StoreAssignmentRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();

        if ($user->isHr()) {
            abort_unless($sample->created_by === $user->id, 403, 'Anda bukan HR yang menangani sample ini.');
        }

        abort_if($sample->tier === 'rapid', 422, 'Sample rapid tidak menggunakan form skor manual.');
        abort_if($sample->status === 'completed', 422, 'Sample ini sudah memiliki laporan selesai.');

        $grafolog = User::findOrFail($request->validated('grafolog_id'));
        abort_unless($grafolog->isGrafolog(), 422, 'User yang dipilih bukan grafolog.');

        $assignment = Assignment::updateOrCreate(
            ['sample_id' => $sample->id],
            ['grafolog_id' => $grafolog->id, 'assigned_by' => $user->id, 'status' => 'assigned']
        );

        return response()->json($assignment->load('grafolog:id,name,email'), 201);
    }
}
