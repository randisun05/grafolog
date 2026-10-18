<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportValidation;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gated 'role:administrator'. Pengindeksan penugasan supervisi - admin tidak menerima token. */
class ValidationIndexController extends Controller
{
    public function index(Request $request, ReportValidationService $service): JsonResponse
    {
        $service->sweep();
        $status = $request->validate(['status' => ['nullable', 'in:'.implode(',', [...ReportValidation::ACTIVE, ReportValidation::DONE, ReportValidation::DECLINED, ReportValidation::EXPIRED])]])['status'] ?? null;

        return response()->json(
            ReportValidation::with(['requester:id,name', 'validator:id,name', 'report:id,tier,review_status'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)
        );
    }
}
