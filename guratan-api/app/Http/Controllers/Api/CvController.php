<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Cv\CvService;
use App\Support\PdfScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CV / data diri berbasis variabel yang diatur admin. Pembaca disaring per tag visibilitas. */
class CvController extends Controller
{
    public function __construct(private CvService $cv) {}

    public function mine(Request $request): JsonResponse
    {
        return $this->show($request, $request->user());
    }

    public function fileStoreMine(Request $request, string $key): JsonResponse
    {
        return $this->fileStore($request, $request->user(), $key);
    }

    public function fileDestroyMine(Request $request, string $key): JsonResponse
    {
        return $this->fileDestroy($request, $request->user(), $key);
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

    public function fileShow(Request $request, User $user, string $key): StreamedResponse
    {
        $relation = $this->cv->relation($request->user(), $user);
        abort_if($relation === null, 403);
        $field = $this->cv->fileField($user, $relation, $key);
        $meta = $this->cv->fileData($user, $field);
        abort_if($meta === null, 404);
        if ($relation !== 'self') {
            AuditLog::record('lihat_cv_berkas', User::class, $user->id, $request->user()->id, $request->ip());
        }

        return Storage::disk('local')->response($meta['path'], $meta['name'], [
            'Content-Type' => $meta['mime'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function fileStore(Request $request, User $user, string $key): JsonResponse
    {
        $relation = $this->cv->relation($request->user(), $user);
        abort_unless($this->cv->canEdit($relation), 403, 'Anda tidak berhak mengubah CV ini.');
        $field = $this->cv->fileField($user, $relation, $key);
        $request->validate(['file' => $field->type === 'photo'
            ? ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=100,min_height=100']
            : ['required', 'file', 'mimes:pdf,docx', 'max:5120', function ($a, $file, $fail) {
                if ($file->getClientOriginalExtension() === 'pdf' && (PdfScan::inspect($file->getRealPath())['unsafe'] ?? false)) {
                    $fail('PDF berisi skrip/lampiran aktif dan ditolak.');
                }
            }],
        ], ['file.max' => 'Ukuran berkas terlalu besar.', 'file.mimes' => 'Format berkas tidak didukung.']);
        $this->cv->storeFile($user, $field, $request->file('file'));
        AuditLog::record('unggah_cv_berkas', User::class, $user->id, $request->user()->id, $request->ip());

        return $this->show($request, $user);
    }

    public function fileDestroy(Request $request, User $user, string $key): JsonResponse
    {
        $relation = $this->cv->relation($request->user(), $user);
        abort_unless($this->cv->canEdit($relation), 403);
        $this->cv->removeFile($user, $this->cv->fileField($user, $relation, $key));

        return $this->show($request, $user);
    }
}
