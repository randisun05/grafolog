<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sample\StoreSampleRequest;
use App\Models\HandwritingSample;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SampleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $samples = HandwritingSample::query()
            ->where('user_id', $user->id)
            ->orWhere('created_by', $user->id)
            ->with('reports:id,sample_id,tier,status,generated_at')
            ->latest()
            ->paginate(20);

        return response()->json($samples);
    }

    public function store(StoreSampleRequest $request): JsonResponse
    {
        $user = $request->user();

        $project = Project::create([
            'source' => $user->isGrafolog() ? 'grafolog' : 'client',
            'created_by' => $user->id,
        ]);

        $sample = HandwritingSample::create([
            'project_id' => $project->id,
            'user_id' => $user->isGrafolog() ? $request->validated('client_user_id') : $user->id,
            'created_by' => $user->id,
            'tier' => $request->validated('tier'),
            'status' => 'pending',
        ]);

        return response()->json($sample->fresh('reports'), 201);
    }

    public function show(Request $request, HandwritingSample $sample): JsonResponse
    {
        $this->authorizeAccess($request, $sample);

        return response()->json($sample->load('reports'));
    }

    private function authorizeAccess(Request $request, HandwritingSample $sample): void
    {
        $user = $request->user();
        abort_unless($sample->user_id === $user->id || $sample->created_by === $user->id, 403);
    }
}
