<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\CandidateProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah (POST) atau ubah (PUT) kandidat. Nama/email hanya saat tambah. */
class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $create = $this->isMethod('post');

        return [
            'name' => $create ? ['required', 'string', 'max:255'] : ['prohibited'],
            'email' => $create ? ['required', 'email', 'max:255'] : ['prohibited'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'job_position_id' => ['sometimes', 'nullable', 'integer',
                Rule::exists('job_positions', 'id')->where('company_id', $this->user()->company_id)],
            'education' => ['sometimes', 'nullable', 'in:'.implode(',', UpdateProfileRequest::EDUCATION)],
            'experience_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'stage' => $create ? ['prohibited'] : ['sometimes', 'in:'.implode(',', CandidateProfile::STAGES)],
            'consent_confirmed' => $create ? ['accepted'] : ['prohibited'],
        ];
    }
}
