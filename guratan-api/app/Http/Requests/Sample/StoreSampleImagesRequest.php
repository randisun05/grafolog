<?php

namespace App\Http\Requests\Sample;

use App\Models\SampleImage;
use App\Support\PdfScan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Unggah scan tulisan tangan = PDF hasil scan (2026-10-18), bukan foto: ukuran halaman harus
 * A4/F4 supaya skala pengukuran grafolog (mm) tetap. Isi PDF diperiksa (PdfScan), bukan hanya ekstensi.
 */
class StoreSampleImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'images.*.max' => 'Ukuran file maksimal 30 MB.',
            'images.*.mimes' => 'Unggah PDF hasil scan (bukan foto).',
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => [
                'required', 'file', 'mimes:pdf', 'max:'.SampleImage::MAX_KB,
                function (string $attribute, $file, Closure $fail) {
                    $scan = $file instanceof UploadedFile ? PdfScan::inspect($file->getRealPath()) : null;
                    if (! $scan || ! $scan['valid']) {
                        $fail('File bukan PDF yang valid.');

                        return;
                    }
                    if ($scan['pages'] < 1) {
                        $fail('PDF tidak berisi halaman.');

                        return;
                    }
                    // Ukuran terbaca tetapi bukan A4/F4 -> hampir pasti foto/crop; skala tidak bisa dipercaya.
                    if ($scan['width_mm'] !== null && $scan['paper'] === null) {
                        $fail("Ukuran halaman {$scan['width_mm']}×{$scan['height_mm']} mm bukan A4/F4. Scan kertas utuh tanpa zoom/crop (300 dpi).");
                    }
                },
            ],
        ];
    }
}
