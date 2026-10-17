<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sample\StoreSampleImagesRequest;
use App\Models\AuditLog;
use App\Models\HandwritingSample;
use App\Models\SampleImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto tulisan tangan per sample - data sensitif, jadi disimpan di disk
 * privat dan setiap akses (lihat/unggah/hapus) dicatat di audit_logs.
 */
class SampleImageController extends Controller
{
    public function index(Request $request, HandwritingSample $sample): JsonResponse
    {
        abort_unless($sample->isViewableBy($request->user()), 403);

        return response()->json($sample->images()->latest()->get());
    }

    public function store(StoreSampleImagesRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($sample->acceptsImagesFrom($user), 403, 'Anda tidak dapat menambah foto untuk sample ini.');

        $files = $request->file('images');
        abort_if(
            $sample->images()->count() + count($files) > SampleImage::MAX_PER_SAMPLE,
            422,
            'Maksimal '.SampleImage::MAX_PER_SAMPLE.' foto per sample.'
        );

        $images = DB::transaction(fn () => collect($files)->map(function ($file) use ($sample, $user) {
            $image = $sample->images()->create([
                'path' => $file->store("samples/{$sample->id}", SampleImage::DISK),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);
            AuditLog::record('unggah_foto_tulisan', SampleImage::class, $image->id, $user->id, request()->ip());

            return $image;
        }));

        return response()->json($images, 201);
    }

    public function show(Request $request, HandwritingSample $sample, SampleImage $image): StreamedResponse
    {
        abort_unless($image->sample_id === $sample->id, 404);
        abort_unless($sample->isViewableBy($request->user()), 403);

        AuditLog::record('lihat_foto_tulisan', SampleImage::class, $image->id, $request->user()->id, $request->ip());

        return Storage::disk(SampleImage::DISK)->response($image->path, null, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, HandwritingSample $sample, SampleImage $image): JsonResponse
    {
        abort_unless($image->sample_id === $sample->id, 404);
        abort_unless($image->uploaded_by === $request->user()->id && $sample->status !== 'completed', 403);

        Storage::disk(SampleImage::DISK)->delete($image->path);
        $image->delete();
        AuditLog::record('hapus_foto_tulisan', SampleImage::class, $image->id, $request->user()->id, $request->ip());

        return response()->json(null, 204);
    }
}
