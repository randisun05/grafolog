<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateContentBlockRequest;
use App\Models\AuditLog;
use App\Models\ContentBlock;
use Illuminate\Http\JsonResponse;

/**
 * Gated by 'role:administrator' on its routes. Only ContentBlock::
 * EDITABLE_KEYS can be written - this is a fixed-field CMS by design
 * (business decision 2026-08-06), not a free-form key-value store an
 * admin could use to inject arbitrary content.
 */
class ContentBlockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ContentBlock::all());
    }

    public function update(UpdateContentBlockRequest $request, string $key): JsonResponse
    {
        abort_unless(in_array($key, ContentBlock::EDITABLE_KEYS, true), 404);

        $block = ContentBlock::updateOrCreate(
            ['key' => $key],
            ['value' => $request->validated('value'), 'updated_by' => $request->user()->id]
        );

        AuditLog::record('ubah_konten', ContentBlock::class, $block->id, $request->user()->id, $request->ip());

        return response()->json($block);
    }
}
