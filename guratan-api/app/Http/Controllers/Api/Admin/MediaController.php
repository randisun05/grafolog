<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Upload gambar generik ke disk publik, dipakai oleh RichTextEditor.vue
 * (Artikel DAN Kegiatan) untuk menyisipkan gambar inline di body - beda
 * dari upload cover Artikel/Event yang bundel jadi 1 request dengan
 * field lain. Endpoint ini sengaja berdiri sendiri supaya dipakai ulang
 * kapan pun admin butuh "upload gambar, dapat URL publik balik" tanpa
 * terikat ke 1 entity tertentu.
 */
class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('image')->store('uploads', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }
}
