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
     * diisi terus - lihat MeasurementController::store().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pengukuran' => ['required', 'array', 'min:1'],
            'pengukuran.*.variable_id' => ['required', 'integer', 'distinct', 'exists:measurement_variable,id'],
            'pengukuran.*.nilai' => ['nullable', 'numeric'],
        ];
    }
}
