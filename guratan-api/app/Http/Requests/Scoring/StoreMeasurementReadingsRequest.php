<?php

namespace App\Http\Requests\Scoring;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementReadingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Boleh sebagian (measurement worksheet biasanya diisi bertahap, bukan
     * 37 variabel sekaligus) - beda dari SubmitScoresRequest yang wajib
     * lengkap, karena ini cuma input mentah, belum jadi laporan. `nilai`
     * boleh null - itu sinyal "hapus hasil ukur ini" (dipakai saat grafolog
     * mengosongkan field yang sudah pernah tersimpan), bukan berarti wajib
     * diisi terus - lihat MeasurementController::store(). `nilai_min`/
     * `nilai_max` (2026-08-17): rentang terbesar/terkecil yang diamati,
     * dipakai rule irregularity bertipe range (selisih maks-min) - opsional,
     * independen dari `nilai` (boleh isi salah satu atau keduanya).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pengukuran' => ['required', 'array', 'min:1'],
            'pengukuran.*.variable_id' => ['required', 'integer', 'distinct', 'exists:measurement_variable,id'],
            'pengukuran.*.nilai' => ['nullable', 'numeric'],
            'pengukuran.*.nilai_min' => ['nullable', 'numeric'],
            'pengukuran.*.nilai_max' => ['nullable', 'numeric', 'gte:pengukuran.*.nilai_min'],
        ];
    }
}
