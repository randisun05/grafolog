<?php

namespace App\Http\Requests\Sample;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpsertConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
            'meeting_url' => ['required', 'url:https', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:dijadwalkan,selesai,dibatalkan'],
        ];
    }
}
