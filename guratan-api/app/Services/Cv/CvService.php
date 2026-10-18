<?php

namespace App\Services\Cv;

use App\Models\CandidateProfile;
use App\Models\CvField;
use App\Models\CvGroup;
use App\Models\CvValue;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/** Membaca/menulis CV sebuah pengguna dengan penyaringan visibilitas per peran pembaca. */
class CvService
{
    /** Peran pembaca terhadap pemilik CV: self | admin | hr | grafolog | null (tak berhak). */
    public function relation(User $viewer, User $subject): ?string
    {
        if ($viewer->id === $subject->id) {
            return 'self';
        }
        if ($viewer->isAdministrator()) {
            return 'admin';
        }
        if ($viewer->isHr() && $viewer->company_id
            && CandidateProfile::where('user_id', $subject->id)->where('company_id', $viewer->company_id)->exists()) {
            return 'hr';
        }
        if ($viewer->isGrafolog() && $subject->samples()
            ->where(fn ($q) => $q->where('created_by', $viewer->id)
                ->orWhereHas('assignment', fn ($a) => $a->where('grafolog_id', $viewer->id)))->exists()) {
            return 'grafolog';
        }

        return null;
    }

    public function canEdit(?string $relation): bool
    {
        return in_array($relation, ['self', 'hr'], true);
    }

    /** Tag yang boleh dilihat per relasi. */
    private function allowedTags(string $relation): ?array
    {
        return match ($relation) {
            'self', 'admin' => null,
            'hr' => ['all', 'client'],
            'grafolog' => ['all', 'grafolog'],
        };
    }

    /** @return array<int, array> grup + field + nilai (field aktif saja; grup kosong dibuang). */
    public function render(User $subject, string $relation, bool $onlyActive = true): array
    {
        $tags = $this->allowedTags($relation);
        $values = CvValue::where('user_id', $subject->id)->pluck('value', 'cv_field_id');

        return CvGroup::with(['fields' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (CvGroup $g) => [
                'id' => $g->id, 'name' => $g->name, 'description' => $g->description,
                'fields' => $g->fields
                    ->filter(fn (CvField $f) => $tags === null || in_array($f->visible_to, $tags, true))
                    ->map(fn (CvField $f) => [
                        'id' => $f->id, 'key' => $f->key, 'label' => $f->label, 'type' => $f->type,
                        'options' => $f->options, 'help' => $f->help, 'required' => $f->required,
                        'visible_to' => $f->visible_to, 'value' => $this->cast($f, $values[$f->id] ?? null),
                    ])->values()->all(),
            ])->filter(fn ($g) => $g['fields'])->values()->all();
    }

    /** @param  array<string, mixed>  $input  key => nilai. Hanya key yang dikirim yang diubah. */
    public function save(User $subject, array $input): void
    {
        $fields = CvField::where('is_active', true)->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->get()->keyBy('key');
        $fields = $fields->all();
        $input = array_intersect_key($input, $fields);
        $used = array_intersect_key($fields, $input);
        $data = Validator::make(
            $input,
            array_map(fn (CvField $f) => $f->rules(), $used),
            [], array_map(fn (CvField $f) => $f->label, $used),
        )->validate();

        foreach ($data as $key => $value) {
            $field = $fields[$key];
            $stored = $value === null || $value === '' ? null : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
            CvValue::updateOrCreate(['user_id' => $subject->id, 'cv_field_id' => $field->id], ['value' => $stored]);
        }
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
