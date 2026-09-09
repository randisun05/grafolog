<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGlossaryTermRequest;
use App\Http\Requests\Admin\UpdateGlossaryTermRequest;
use App\Models\AuditLog;
use App\Models\GlossaryTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by 'role:administrator'. Sama pola TriviaQuestionController -
 * tidak dipaginasi, menyertakan istilah nonaktif juga.
 */
class GlossaryTermController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(GlossaryTerm::latest()->get());
    }

    public function store(StoreGlossaryTermRequest $request): JsonResponse
    {
        $term = GlossaryTerm::create($request->validated());

        AuditLog::record('buat_istilah', GlossaryTerm::class, $term->id, $request->user()->id, $request->ip());

        return response()->json($term, 201);
    }

    public function update(UpdateGlossaryTermRequest $request, GlossaryTerm $glossaryTerm): JsonResponse
    {
        $glossaryTerm->update($request->validated());

        AuditLog::record('ubah_istilah', GlossaryTerm::class, $glossaryTerm->id, $request->user()->id, $request->ip());

        return response()->json($glossaryTerm);
    }

    public function destroy(Request $request, GlossaryTerm $glossaryTerm): JsonResponse
    {
        $id = $glossaryTerm->id;
        $glossaryTerm->delete();

        AuditLog::record('hapus_istilah', GlossaryTerm::class, $id, $request->user()->id, $request->ip());

        return response()->json(['message' => 'Istilah dihapus.']);
    }
}
