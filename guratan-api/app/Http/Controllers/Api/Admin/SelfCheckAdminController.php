<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SelfCheckOption;
use App\Models\SelfCheckQuestion;
use App\Models\SelfCheckResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:administrator'. Kelola pertanyaan cek tulisan + pemetaan ke Indikator KB, lihat lead. */
class SelfCheckAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            SelfCheckQuestion::orderBy('sort')
                ->with(['options.indikator:id,aspek_id,nama', 'options.indikator.aspek:id,kode,nama,nama_id'])
                ->get()
        );
    }

    public function storeQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:255'],
            'petunjuk' => ['nullable', 'string', 'max:1000'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.indikator_id' => ['nullable', 'integer', 'exists:indikator,id'],
        ]);

        $question = SelfCheckQuestion::create($data);
        foreach ($data['options'] as $i => $o) {
            $question->options()->create($o + ['sort' => $i + 1]);
        }
        AuditLog::record('buat_pertanyaan_cek_tulisan', SelfCheckQuestion::class, $question->id, $request->user()->id, $request->ip());

        return response()->json($question->load('options'), 201);
    }

    public function updateQuestion(Request $request, SelfCheckQuestion $question): JsonResponse
    {
        $question->update($request->validate([
            'pertanyaan' => ['sometimes', 'string', 'max:255'],
            'petunjuk' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]));
        AuditLog::record('ubah_pertanyaan_cek_tulisan', SelfCheckQuestion::class, $question->id, $request->user()->id, $request->ip());

        return response()->json($question);
    }

    public function updateOption(Request $request, SelfCheckOption $option): JsonResponse
    {
        $option->update($request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'indikator_id' => ['sometimes', 'nullable', 'integer', 'exists:indikator,id'],
        ]));
        AuditLog::record('ubah_opsi_cek_tulisan', SelfCheckOption::class, $option->id, $request->user()->id, $request->ip());

        return response()->json($option->load('indikator.aspek:id,kode,nama,nama_id'));
    }

    /** Lead = hasil yang meninggalkan email (dengan persetujuan) atau dibuat pengguna login. */
    public function results(): JsonResponse
    {
        return response()->json([
            'total' => SelfCheckResult::count(),
            'leads' => SelfCheckResult::whereNotNull('email')->with('user:id,name,role')
                ->latest()->limit(200)->get(['id', 'uuid', 'user_id', 'email', 'consented_at', 'aspek_ids', 'created_at']),
        ]);
    }
}
