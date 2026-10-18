<?php

namespace App\Services\B2b;

use App\Models\CandidateProfile;
use App\Models\User;

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

    /** Password sementara yang bisa dibacakan/ditulis HR (tanpa karakter ambigu), ada huruf & angka. */
    public static function tempPassword(): string
    {
        $letters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $pick = fn (string $set, int $n) => collect(range(1, $n))->map(fn () => $set[random_int(0, strlen($set) - 1)])->implode('');

        return str_shuffle($pick($letters, 6).$pick($digits, 3).$pick($letters, 1));
    }

    /**
     * Kandidat dibuat/ditautkan ke perusahaan HR. Akun BARU mendapat password sementara (wajib diganti
     * saat login pertama) yang dikembalikan sekali ke HR - tidak diemail. Akun yang sudah ada tidak diubah.
     *
     * @param  array{name:string, email:string, phone?:?string, education?:?string, experience_years?:?int, notes?:?string, job_position_id?:?int}  $data
     * @return array{0: CandidateProfile, 1: ?string} [profil, password sementara bila akun baru]
     */
    public function register(array $data, User $hr): array
    {
        $temp = null;
        $candidate = User::where('email', $data['email'])->first();

        if (! $candidate) {
            $temp = self::tempPassword();
            $candidate = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $temp,
                'must_change_password' => true,
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

        return [$profile, $temp];
    }
}
