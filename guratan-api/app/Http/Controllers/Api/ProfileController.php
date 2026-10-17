<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update(collect($data)->except('company')->all());
        if (isset($data['company']) && $user->company) {
            $user->company->update($data['company']);
        }
        AuditLog::record('ubah_profil', User::class, $user->id, $user->id, $request->ip());

        return response()->json($this->payload($user->fresh()));
    }

    private function payload(User $user): array
    {
        return ['user' => $user->company_id ? $user->load('company') : $user];
    }
}
