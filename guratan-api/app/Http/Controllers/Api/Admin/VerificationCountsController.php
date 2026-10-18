<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/** Gated 'role:administrator'. Jumlah pendaftar menunggu verifikasi - lencana menu "Verifikasi". */
class VerificationCountsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $grafolog = User::where('role', 'grafolog')->where('grafolog_status', 'pending')->count();
        $perusahaan = Company::where('status', Company::STATUS_PENDING)->count();

        return response()->json(['grafolog' => $grafolog, 'perusahaan' => $perusahaan, 'total' => $grafolog + $perusahaan]);
    }
}
