<?php

namespace App\Http\Requests\Sample;

use App\Models\SampleImage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreSampleImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function messages(): array
    {
        return [
            'images.*.max' => 'Ukuran file maksimal 30 MB.',
            'images.*.mimes' => 'Format harus JPG, PNG, atau WEBP (hasil scan).',
        ];
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:5'],
            // HEIC dihapus (2026-10-17): resolusinya tidak bisa dibaca getimagesize() dan
            // browser tidak bisa menampilkannya. Minta hasil scan JPG/PNG.
            'images.*' => [
                'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.SampleImage::MAX_KB,
                function (string $attribute, $file, Closure $fail) {
                    $size = $file instanceof UploadedFile ? @getimagesize($file->getRealPath()) : false;
                    if (! $size) {
                        $fail('File bukan gambar yang valid.');

                        return;
                    }
                    [$long, $short] = [max($size[0], $size[1]), min($size[0], $size[1])];
                    if ($long < SampleImage::MIN_LONG_SIDE || $short < SampleImage::MIN_SHORT_SIDE) {
                        $fail("Resolusi {$size[0]}×{$size[1]} px terlalu kecil. Minimal 1920×1080 (FHD) - gunakan hasil scan agar skala tetap dan jelas.");
                    }
                },
            ],
        ];
    }
}
