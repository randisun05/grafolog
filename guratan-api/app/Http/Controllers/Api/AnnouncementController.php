<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated (not public like PricingController/ContentController) -
 * visibility depends on knowing the requesting user's role. Commerce
 * Fase F. Filtered in PHP via Announcement::isVisibleTo() rather than a
 * DB query - announcement volume is expected to stay small, and this way
 * there's exactly one place deciding "is this visible," same principle
 * as DiscountCode::isValidFor().
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $visible = Announcement::all()
            ->filter(fn (Announcement $a) => $a->isVisibleTo($user))
            ->values();

        return response()->json($visible);
    }
}
