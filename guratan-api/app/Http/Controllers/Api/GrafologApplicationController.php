<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreGrafologApplicationRequest;
use App\Models\AuditLog;
use App\Models\GrafologApplication;
use Illuminate\Http\JsonResponse;

class GrafologApplicationController extends Controller
{
    /**
     * Publik, tanpa auth - lihat migrasi `create_grafolog_applications_table`
     * untuk konteks penuh. Sengaja TIDAK menerbitkan token Sanctum atau
     * langsung membuat akun `users` (beda dari AuthController::register) -
     * status tetap `pending` sampai administrator approve lewat
     * Admin\GrafologApplicationController::approve().
     */
    public function store(StoreGrafologApplicationRequest $request): JsonResponse
    {
        $path = $request->file('document')->store('grafolog-applications', 'local');

        $application = GrafologApplication::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'phone' => $request->validated('phone'),
            'catatan' => $request->validated('catatan'),
            'document_path' => $path,
            'document_original_name' => $request->file('document')->getClientOriginalName(),
        ]);

        // Actor null - ini pengajuan publik, belum ada user staf yang login.
        AuditLog::record('ajukan_akun_grafolog', GrafologApplication::class, $application->id, null, $request->ip());

        return response()->json([
            'message' => 'Pengajuan Anda sudah kami terima. Tim kami akan meninjau biodata dan bukti profesi Anda - setelah disetujui administrator, Anda bisa masuk memakai email dan kata sandi yang baru saja didaftarkan.',
        ], 201);
    }
}
