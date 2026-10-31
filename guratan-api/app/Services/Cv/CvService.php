<?php

namespace App\Services\Cv;

use App\Models\CandidateProfile;
use App\Models\CvField;
use App\Models\CvGroup;
use App\Models\CvValue;
use App\Models\HandwritingSample;
use App\Models\ReportValidation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * CV pengguna (klien/kandidat dan grafolog) dengan variabel yang diatur admin.
 * - applies_to: variabel memuat CV siapa (client | grafolog | all).
 * - is_hidden: hanya pemilik (isi) dan administrator (lihat semua) - tidak tampil ke pihak lain.
 * Saling-lihat: klien/kandidat & HR melihat CV grafolog yang memeriksa; grafolog melihat CV subjeknya.
 */
class CvService
{
    /** Relasi pembaca ke pemilik CV: self | admin | hr | examiner (grafolog pemeriksa) | subject (klien yang diperiksa) | null. */
    public function relation(User $viewer, User $subject): ?string
    {
        if ($viewer->id === $subject->id) {
            return 'self';
        }
        if ($viewer->isAdministrator()) {
            return 'admin';
        }
        if ($subject->isGrafolog()) {
            if ($viewer->role === 'user' && $this->examines($subject->id, [$viewer->id])) {
                return 'subject';
            }
            if ($viewer->isGrafolog() && $this->peers($viewer->id, $subject->id)) {
                return 'peer';
            }
            if ($viewer->isHr() && $viewer->company_id) {
                $candidateIds = CandidateProfile::where('company_id', $viewer->company_id)->pluck('user_id')->all();

                return $this->examines($subject->id, $candidateIds) ? 'subject' : 'public';
            }

            // Portofolio grafolog terverifikasi dapat dilihat klien (variabel tidak tersembunyi) untuk memilih.
            return $viewer->role === 'user' ? 'public' : null;
        }
        if ($viewer->isHr() && $viewer->company_id
            && CandidateProfile::where('user_id', $subject->id)->where('company_id', $viewer->company_id)->exists()) {
            return 'hr';
        }
        if ($viewer->isGrafolog() && $this->examines($viewer->id, [$subject->id])) {
            return 'examiner';
        }

        return null;
    }

    /** Dua grafolog saling terhubung lewat penugasan supervisi yang berjalan/selesai. */
    private function peers(int $a, int $b): bool
    {
        return ReportValidation::whereIn('status', [...ReportValidation::ACTIVE, ReportValidation::DONE])
            ->where(fn (Builder $q) => $q->where(fn ($w) => $w->where('requester_id', $a)->where('validator_id', $b))
                ->orWhere(fn ($w) => $w->where('requester_id', $b)->where('validator_id', $a)))->exists();
    }

    /** Apakah grafolog ini menangani (pembuat/ditugaskan) sampel salah satu dari pengguna tsb. */
    private function examines(int $grafologId, array $userIds): bool
    {
        return $userIds !== [] && HandwritingSample::whereIn('user_id', $userIds)
            ->where(fn (Builder $q) => $q->where('created_by', $grafologId)
                ->orWhereHas('assignment', fn ($a) => $a->where('grafolog_id', $grafologId)))->exists();
    }

    public function canEdit(?string $relation): bool
    {
        return in_array($relation, ['self', 'hr'], true);
    }

    /** Variabel tersembunyi hanya untuk pemilik & admin. */
    private function seesHidden(string $relation): bool
    {
        return in_array($relation, ['self', 'admin'], true);
    }

    /** Field aktif yang berlaku untuk jenis CV pemilik ini. */
    private function appliesTo(User $subject): array
    {
        return $subject->isGrafolog() ? ['all', 'grafolog'] : ['all', 'client'];
    }

    /** @return array<int, array> grup + field + nilai (grup tanpa field dibuang). */
    public function render(User $subject, string $relation): array
    {
        $applies = $this->appliesTo($subject);
        $hiddenOk = $this->seesHidden($relation);
        $values = CvValue::where('user_id', $subject->id)->pluck('value', 'cv_field_id');

        return CvGroup::with(['fields' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (CvGroup $g) => [
                'id' => $g->id, 'name' => $g->name, 'description' => $g->description,
                'fields' => $g->fields
                    ->filter(fn (CvField $f) => in_array($f->applies_to, $applies, true) && ($hiddenOk || ! $f->is_hidden))
                    ->map(fn (CvField $f) => [
                        'id' => $f->id, 'key' => $f->key, 'label' => $f->label, 'type' => $f->type,
                        'options' => $f->options, 'help' => $f->help, 'required' => $f->required,
                        'is_hidden' => $f->is_hidden, 'value' => $f->isFile() ? $this->fileMeta($subject, $f, $values[$f->id] ?? null) : $this->cast($f, $values[$f->id] ?? null),
                    ])->values()->all(),
            ])->filter(fn ($g) => $g['fields'])->values()->all();
    }

    /** @param  array<string, mixed>  $input  key => nilai. Hanya key yang dikirim & boleh diubah yang diproses. */
    public function save(User $subject, string $relation, array $input): void
    {
        $applies = $this->appliesTo($subject);
        $hiddenOk = $this->seesHidden($relation);
        $fields = CvField::where('is_active', true)->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->get()
            ->filter(fn (CvField $f) => ! $f->isFile() && in_array($f->applies_to, $applies, true) && ($hiddenOk || ! $f->is_hidden))
            ->keyBy('key')->all();
        $input = array_intersect_key($input, $fields);
        $used = array_intersect_key($fields, $input);
        $data = Validator::make(
            $input,
            array_map(fn (CvField $f) => $f->rules(), $used),
            [], array_map(fn (CvField $f) => $f->label, $used),
        )->validate();

        foreach ($data as $key => $value) {
            $stored = $value === null || $value === '' ? null : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
            CvValue::updateOrCreate(['user_id' => $subject->id, 'cv_field_id' => $fields[$key]->id], ['value' => $stored]);
        }
    }

    /** Jumlah variabel WAJIB (untuk jenis CV pemilik ini, termasuk yang tersembunyi) yang belum terisi. */
    public function missingRequired(User $subject): int
    {
        $values = CvValue::where('user_id', $subject->id)->pluck('value', 'cv_field_id');
        $applies = $this->appliesTo($subject);

        return CvField::where('is_active', true)->where('required', true)->whereIn('applies_to', $applies)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))->get()
            ->filter(fn (CvField $f) => blank($values[$f->id] ?? null))->count();
    }

    /** Field berkas yang boleh diakses relasi ini (404 bila tidak berlaku/tersembunyi). */
    public function fileField(User $subject, string $relation, string $key): CvField
    {
        $field = CvField::where('key', $key)->where('is_active', true)->whereIn('type', ['photo', 'file'])->firstOrFail();
        abort_unless(in_array($field->applies_to, $this->appliesTo($subject), true), 404);
        abort_if($field->is_hidden && ! $this->seesHidden($relation), 404);

        return $field;
    }

    public function storeFile(User $subject, CvField $field, UploadedFile $file): void
    {
        $this->removeFile($subject, $field);
        $path = $file->store("cv/{$subject->id}", 'local');
        CvValue::updateOrCreate(['user_id' => $subject->id, 'cv_field_id' => $field->id], ['value' => json_encode([
            'path' => $path, 'name' => mb_substr($file->getClientOriginalName(), 0, 150), 'mime' => $file->getMimeType(),
        ])]);
    }

    public function removeFile(User $subject, CvField $field): void
    {
        $v = CvValue::where('user_id', $subject->id)->where('cv_field_id', $field->id)->first();
        $meta = $v?->value ? json_decode($v->value, true) : null;
        if ($meta['path'] ?? null) {
            Storage::disk('local')->delete($meta['path']);
        }
        $v?->delete();
    }

    /** @return array{name:string, mime:string, path:string}|null */
    public function fileData(User $subject, CvField $field): ?array
    {
        $raw = CvValue::where('user_id', $subject->id)->where('cv_field_id', $field->id)->value('value');

        return $raw ? json_decode($raw, true) : null;
    }

    private function fileMeta(User $subject, CvField $f, ?string $raw): ?array
    {
        $meta = $raw ? json_decode($raw, true) : null;

        return $meta ? ['name' => $meta['name'], 'url' => "/cv/users/{$subject->id}/files/{$f->key}"] : null;
    }

    private function cast(CvField $f, ?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($f->type) {
            'number' => $raw + 0,
            'boolean' => $raw === '1',
            default => $raw,
        };
    }
}
