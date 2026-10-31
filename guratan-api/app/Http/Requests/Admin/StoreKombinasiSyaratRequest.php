<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreKombinasiSyaratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `kondisi` divalidasi penuh di withValidator() (bukan `in:...` di sini)
     * karena nilai yang diizinkan berbeda per `level` - lihat migrasi
     * kombinasi_syarat untuk kenapa Indikator cuma boolean sedangkan
     * Aspek/Sindrom pakai 4 bucket narasi_level yang sudah ada.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', 'string', 'in:indikator,aspek,sindrom'],
            'indikator_id' => ['nullable', 'integer', 'exists:indikator,id'],
            'aspek_id' => ['nullable', 'integer', 'exists:aspek,id'],
            'sindrom_id' => ['nullable', 'integer', 'exists:sindrom,id'],
            'kondisi' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $level = $data['level'] ?? null;

            $targetField = match ($level) {
                'indikator' => 'indikator_id',
                'aspek' => 'aspek_id',
                'sindrom' => 'sindrom_id',
                default => null,
            };
            if ($targetField === null) {
                return; // level sendiri sudah gagal validasi in:..., tidak perlu cek lebih lanjut
            }

            if (empty($data[$targetField])) {
                $validator->errors()->add($targetField, 'Wajib diisi untuk level ini.');
            }
            foreach (['indikator_id', 'aspek_id', 'sindrom_id'] as $field) {
                if ($field !== $targetField && ! empty($data[$field])) {
                    $validator->errors()->add($field, 'Tidak boleh diisi - level syarat ini bukan '.$field.'.');
                }
            }

            $kondisiValid = $level === 'indikator'
                ? in_array($data['kondisi'] ?? null, ['tercentang', 'tidak_tercentang'], true)
                : in_array($data['kondisi'] ?? null, ['low', 'medium', 'high', 'very_high'], true);
            if (! $kondisiValid) {
                $pilihan = $level === 'indikator' ? 'tercentang, tidak_tercentang' : 'low, medium, high, very_high';
                $validator->errors()->add('kondisi', "Untuk level {$level}, harus salah satu dari: {$pilihan}.");
            }
        });
    }
}
