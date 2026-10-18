<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CvField;
use App\Models\CvGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Gated 'role:administrator'. Atur kelompok & variabel CV (tipe data, wajib, tag visibilitas). */
class CvManagementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CvGroup::with('fields')->withCount('fields')->orderBy('sort_order')->orderBy('id')->get());
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $group = CvGroup::create($this->groupData($request) + ['sort_order' => (int) CvGroup::max('sort_order') + 1]);
        AuditLog::record('buat_cv_grup', CvGroup::class, $group->id, $request->user()->id, $request->ip());

        return response()->json($group, 201);
    }

    public function updateGroup(Request $request, CvGroup $group): JsonResponse
    {
        $group->update($this->groupData($request, true));
        AuditLog::record('ubah_cv_grup', CvGroup::class, $group->id, $request->user()->id, $request->ip());

        return response()->json($group);
    }

    public function destroyGroup(Request $request, CvGroup $group): JsonResponse
    {
        abort_if($group->fields()->whereHas('values')->exists(), 422, 'Grup sudah berisi data pengguna; nonaktifkan saja.');
        $group->delete();
        AuditLog::record('hapus_cv_grup', CvGroup::class, $group->id, $request->user()->id, $request->ip());

        return response()->json(null, 204);
    }

    public function storeField(Request $request): JsonResponse
    {
        $data = $this->fieldData($request);
        $data['key'] = $this->uniqueKey($data['label']);
        $data['sort_order'] = (int) CvField::where('cv_group_id', $data['cv_group_id'])->max('sort_order') + 1;
        $field = CvField::create($data);
        AuditLog::record('buat_cv_field', CvField::class, $field->id, $request->user()->id, $request->ip());

        return response()->json($field, 201);
    }

    public function updateField(Request $request, CvField $field): JsonResponse
    {
        $data = $this->fieldData($request, true);
        // Tipe tidak boleh berubah setelah ada data - nilai lama bisa tidak valid lagi.
        if (isset($data['type']) && $data['type'] !== $field->type && $field->values()->exists()) {
            abort(422, 'Tipe tidak dapat diubah karena sudah ada data terisi.');
        }
        $field->update($data);
        AuditLog::record('ubah_cv_field', CvField::class, $field->id, $request->user()->id, $request->ip());

        return response()->json($field);
    }

    /** Field yang sudah punya data hanya dinonaktifkan (data tetap tersimpan). */
    public function destroyField(Request $request, CvField $field): JsonResponse
    {
        if ($field->values()->exists()) {
            $field->update(['is_active' => false]);
        } else {
            $field->delete();
        }
        AuditLog::record('hapus_cv_field', CvField::class, $field->id, $request->user()->id, $request->ip());

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:group,field'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $model = $data['type'] === 'group' ? CvGroup::class : CvField::class;
        foreach ($data['ids'] as $i => $id) {
            $model::whereKey($id)->update(['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }

    private function groupData(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function fieldData(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'cv_group_id' => [$req, 'integer', 'exists:cv_groups,id'],
            'label' => [$req, 'string', 'max:150'],
            'type' => [$req, Rule::in(CvField::TYPES)],
            'options' => ['nullable', 'array', 'max:50'],
            'options.*' => ['string', 'max:100'],
            'help' => ['nullable', 'string', 'max:255'],
            'required' => ['sometimes', 'boolean'],
            'visible_to' => ['sometimes', Rule::in(CvField::VISIBILITY)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $type = $data['type'] ?? $request->route('field')?->type;
        if ($type === 'select' && empty($data['options'] ?? $request->route('field')?->options)) {
            abort(422, 'Tipe pilihan wajib punya opsi.');
        }
        if ($type !== 'select') {
            $data['options'] = null;
        }

        return $data;
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base;
        for ($i = 2; CvField::where('key', $key)->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }
}
