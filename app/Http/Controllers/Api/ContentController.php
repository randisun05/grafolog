<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\JsonResponse;

/**
 * Public read - halaman landing dirender sebelum login. Commerce Fase E.
 */
class ContentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ContentBlock::pluck('value', 'key')
        );
    }
}
