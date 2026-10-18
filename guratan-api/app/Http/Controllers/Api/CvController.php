<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Cv\CvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CV / data diri berbasis variabel yang diatur admin. Pembaca disaring per tag visibilitas. */
class CvController extends Controller
{
    public function __construct(private CvService $cv) {}

    public function mine(Request $request): JsonResponse
    {
        return $this->show($request, $request->user());
    }

    public function updateMine(Request $request): JsonResponse
    {
        return $this->update($request, $request->user());
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $relation = $this->cv->relation($request->user(), $user);
        abort_if($relation === null, 403, 'Anda tidak berhak melihat CV ini.');
        if ($relation !== 'self') {
            AuditLog::record('lihat_cv', User::class, $user->id, $request->user()->id, $request->ip());
        }

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name],
            'editable' => $this->cv->canEdit($relation),
            'groups' => $this->cv->render($user, $relation),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $relation = $this->cv->relation($request->user(), $user);
        abort_unless($this->cv->canEdit($relation), 403, 'Anda tidak berhak mengubah CV ini.');
        $this->cv->save($user, $relation, (array) $request->input('values', []));
        AuditLog::record('ubah_cv', User::class, $user->id, $request->user()->id, $request->ip());

        return $this->show($request, $user);
    }
}
