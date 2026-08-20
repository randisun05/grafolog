<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ImportCandidatesRequest;
use App\Models\HandwritingSample;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Gated by 'role:hr' on its route (routes/api.php). "Candidate" is deliberately
 * not a new table - it's a regular User (role: user) with company_id set, so
 * everything downstream (Project, HandwritingSample.user_id, ReportController,
 * ReportView) works completely unchanged. See guratan-api/CLAUDE.md.
 */
class CandidateImportController extends Controller
{
    public function import(ImportCandidatesRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->company_id, 422, 'Akun HR ini belum terhubung ke company.');

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

        $tier = $request->validated('tier') ?? 'comprehensive';

        $result = DB::transaction(function () use ($user, $candidates, $request, $tier) {
            $project = Project::create([
                'name' => $request->validated('project_name'),
                'source' => 'hr',
                'created_by' => $user->id,
            ]);

            $samples = collect($candidates)->map(function (array $row) use ($project, $user, $tier) {
                $candidate = $this->findOrCreateCandidate($row, $user);

                return HandwritingSample::create([
                    'project_id' => $project->id,
                    'user_id' => $candidate->id,
                    'created_by' => $user->id,
                    'tier' => $tier,
                    'status' => 'pending',
                ]);
            });

            return ['project' => $project, 'samples' => $samples];
        });

        return response()->json([
            'project' => $result['project'],
            'samples' => $result['samples']->map->only(['id', 'user_id', 'tier', 'status']),
        ], 201);
    }

    private function findOrCreateCandidate(array $row, User $hr): User
    {
        $candidate = User::where('email', $row['email'])->first();

        if (! $candidate) {
            return User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => Str::random(32),
                'role' => 'user',
                'company_id' => $hr->company_id,
            ]);
        }

        // Existing self-registered client not yet tied to a company - attach
        // them. Never touch name/password of an existing account here.
        if ($candidate->company_id === null) {
            $candidate->update(['company_id' => $hr->company_id]);
        }

        return $candidate;
    }

    /**
     * @return array{0: array<int, array{name: string, email: string}>, 1: array<int, array{line: int, errors: array<string>}>}
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
            ]);

            if ($validator->fails()) {
                $errors[] = ['line' => $line, 'errors' => $validator->errors()->all()];

                continue;
            }

            $existing = User::where('email', $row['email'])->first();

            if ($existing && $existing->role !== 'user') {
                $errors[] = ['line' => $line, 'errors' => ["Email {$row['email']} sudah dipakai akun staf, tidak bisa jadi kandidat."]];

                continue;
            }

            if ($existing && $existing->company_id !== null && (int) $existing->company_id !== (int) $hr->company_id) {
                $errors[] = ['line' => $line, 'errors' => ["Email {$row['email']} sudah terdaftar sebagai kandidat company lain."]];

                continue;
            }

            $candidates[] = $row;
        }

        return [$candidates, $errors];
    }

    /**
     * @return array<int, array{name: string, email: string}>|null
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
            ];
        }
        fclose($handle);

        return $rows;
    }
}
