<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ImportCandidatesRequest;
use App\Models\AuditLog;
use App\Models\JobPosition;
use App\Models\User;
use App\Services\B2b\CandidateRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Gated by 'role:hr'. Sejak Fase C (2026-10-17) impor HANYA membuat kandidat + profil (opsional
 * ke satu posisi) - asesmen dipesan terpisah lewat B2bOrderController supaya ada pembayaran dan
 * HR memilih grafolog. Satu baris tidak valid menggagalkan seluruh impor.
 */
class CandidateImportController extends Controller
{
    public function __construct(private CandidateRegistrar $registrar) {}

    public function import(ImportCandidatesRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->company_id, 422, 'Akun HR ini belum terhubung ke company.');

        $positionId = $request->validated('job_position_id');
        abort_if(
            $positionId && ! JobPosition::where('id', $positionId)->where('company_id', $user->company_id)->exists(),
            422,
            'Posisi tidak ditemukan di perusahaan Anda.'
        );

        $rows = $this->parseCsv($request->file('file')->getRealPath());
        abort_if(
            $rows === null,
            422,
            'Format CSV tidak valid - baris pertama harus header dengan kolom "name" dan "email".'
        );

        [$candidates, $errors] = $this->validateRows($rows, $user);

        if ($errors !== []) {
            return response()->json(['message' => 'Import gagal, ada baris tidak valid.', 'errors' => $errors], 422);
        }

        if ($candidates === []) {
            return response()->json(['message' => 'CSV tidak berisi kandidat yang valid.'], 422);
        }

        $profiles = DB::transaction(fn () => collect($candidates)
            ->map(fn (array $row) => $this->registrar->register($row + ['job_position_id' => $positionId], $user)));

        AuditLog::record('impor_kandidat', JobPosition::class, $positionId, $user->id, $request->ip());

        return response()->json([
            'imported' => $profiles->count(),
            'candidates' => $profiles->map(fn ($p) => ['id' => $p->id, 'user_id' => $p->user_id, 'stage' => $p->stage]),
        ], 201);
    }

    /**
     * @return array{0: array<int, array{name: string, email: string, phone: ?string}>, 1: array<int, array{line: int, errors: array<string>}>}
     */
    private function validateRows(array $rows, User $hr): array
    {
        $candidates = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // 1-indexed + header row

            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
            ]);

            if ($validator->fails()) {
                $errors[] = ['line' => $line, 'errors' => $validator->errors()->all()];

                continue;
            }

            if ($rejection = $this->registrar->rejectionFor($row['email'], $hr)) {
                $errors[] = ['line' => $line, 'errors' => [$rejection]];

                continue;
            }

            $candidates[] = $row;
        }

        return [$candidates, $errors];
    }

    /**
     * Kolom wajib `name`,`email`; `phone` opsional.
     *
     * @return array<int, array{name: string, email: string, phone: ?string}>|null
     */
    private function parseCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return null;
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return null;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $nameIdx = array_search('name', $header, true);
        $emailIdx = array_search('email', $header, true);
        $phoneIdx = array_search('phone', $header, true);

        if ($nameIdx === false || $emailIdx === false) {
            fclose($handle);

            return null;
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === ['']) {
                continue;
            }

            $rows[] = [
                'name' => trim((string) ($line[$nameIdx] ?? '')),
                'email' => trim((string) ($line[$emailIdx] ?? '')),
                'phone' => $phoneIdx === false ? null : (trim((string) ($line[$phoneIdx] ?? '')) ?: null),
            ];
        }
        fclose($handle);

        return $rows;
    }
}
