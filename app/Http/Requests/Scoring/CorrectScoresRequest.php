<?php

namespace App\Http\Requests\Scoring;

/**
 * Sama persis dengan SubmitScoresRequest (wajib 40 aspek lengkap) + 1 field
 * opsional `catatan` - alasan koreksi, disimpan di ReportRevision untuk
 * jejak audit ("kenapa laporan ini diubah setelah selesai").
 */
class CorrectScoresRequest extends SubmitScoresRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
