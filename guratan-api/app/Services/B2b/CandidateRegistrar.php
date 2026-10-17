<?php

namespace App\Services\B2b;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Membuat/menautkan kandidat ke perusahaan HR (dipakai form tunggal & impor CSV). Kandidat =
 * User role user + CandidateProfile per perusahaan. Akun yang sudah ada tidak pernah diubah
 * nama/password-nya.
 */
class CandidateRegistrar
{
    /** Pesan error bila email ini tidak boleh dijadikan kandidat perusahaan HR, null bila boleh. */
    public function rejectionFor(string $email, User $hr): ?string
    {
        $existing = User::where('email', $email)->first();

        if ($existing && $existing->role !== 'user') {
            return "Email {$email} sudah dipakai akun staf, tidak bisa jadi kandidat.";
        }

        if ($existing && $existing->company_id !== null && (int) $existing->company_id !== (int) $hr->company_id) {
            return "Email {$email} sudah terdaftar sebagai kandidat company lain.";
        }

        return null;
    }

    /** @param  array{name:string, email:string, phone?:?string, education?:?string, experience_years?:?int, notes?:?string, job_position_id?:?int}  $data */
    public function register(array $data, User $hr): CandidateProfile
    {
        $candidate = User::where('email', $data['email'])->first();

        if (! $candidate) {
            $candidate = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Str::random(32),
                'role' => 'user',
                'acquisition_source' => 'b2b',
                'company_id' => $hr->company_id,
                'consented_at' => now(),
                'consent_source' => 'hr',
                'consent_version' => User::CONSENT_VERSION,
            ]);
        } elseif ($candidate->company_id === null) {
            $candidate->update(['company_id' => $hr->company_id]);
        }

        $profile = CandidateProfile::firstOrNew(['user_id' => $candidate->id, 'company_id' => $hr->company_id]);
        $profile->fill(array_filter([
            'job_position_id' => $data['job_position_id'] ?? null,
            'education' => $data['education'] ?? null,
            'experience_years' => $data['experience_years'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));
        $profile->created_by ??= $hr->id;
        $profile->save();

        return $profile;
    }
}
