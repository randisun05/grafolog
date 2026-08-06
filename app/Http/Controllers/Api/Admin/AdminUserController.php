<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gated by the 'role:administrator' middleware on its routes (routes/api.php)
 * - no per-method abort_unless needed here, unlike the older isGrafolog()
 * checks elsewhere in the codebase.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function store(StoreStaffUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->validated('role'),
        ]);

        return response()->json($user->only(['id', 'name', 'email', 'role']), 201);
    }
}
