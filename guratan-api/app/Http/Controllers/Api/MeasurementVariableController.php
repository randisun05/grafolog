<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeasurementVariable;
use Illuminate\Http\JsonResponse;

class MeasurementVariableController extends Controller
{
    /**
     * KM-G: daftar variabel ukur + kategorinya untuk merender Measurement
     * Worksheet grafolog. Baca-saja, siapa pun yang sudah login boleh -
     * beda dari Api\Admin\MeasurementVariableController yang CRUD dan
     * role:administrator saja, sama seperti SindromController vs
     * Api\Admin\SindromController.
     */
    public function index(): JsonResponse
    {
        $variables = MeasurementVariable::with('kategori')->orderBy('nama')->get();

        return response()->json($variables);
    }
}
