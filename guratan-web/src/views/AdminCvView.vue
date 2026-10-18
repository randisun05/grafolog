<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

/** Manajemen CV: kelompok & variabel (tipe data, wajib, tag tampil). */
const toast = useToast()
const groups = ref([])
const loading = ref(true)
const newGroup = ref('')
const TYPES = {
  text: 'Teks pendek',
  textarea: 'Teks panjang',
  number: 'Angka',
  date: 'Tanggal',
  select: 'Pilihan',
  boolean: 'Ya/Tidak',
  email: 'Email',
  phone: 'Telepon',
}
const TAGS = { all: 'Semua', client: 'Klien/HR', grafolog: 'Grafolog' }
const blank = () => ({
  label: '',
  type: 'text',
  visible_to: 'all',
  required: false,
  optionsText: '',
  help: '',
})
const drafts = ref({})

async function load() {
  loading.value = true
  try {
    groups.value = (await api.get('/admin/cv')).data
    groups.value.forEach((g) => (drafts.value[g.id] ??= blank()))
  } finally {
    loading.value = false
  }
}

async function call(fn, okMsg) {
  try {
    await fn()
    if (okMsg) toast.push(okMsg)
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal.')
  }
}

const addGroup = () =>
  call(async () => {
    await api.post('/admin/cv/groups', { name: newGroup.value })
    newGroup.value = ''
  }, 'Grup dibuat.')

const toggleGroup = (g) =>
  call(() => api.put(`/admin/cv/groups/${g.id}`, { is_active: !g.is_active }))
const renameGroup = (g, name) =>
  name && name !== g.name && call(() => api.put(`/admin/cv/groups/${g.id}`, { name }), 'Disimpan.')
const removeGroup = (g) =>
  window.confirm(`Hapus grup "${g.name}"?`) &&
  call(() => api.delete(`/admin/cv/groups/${g.id}`), 'Grup dihapus.')

const addField = (g) =>
  call(async () => {
    const d = drafts.value[g.id]
    await api.post('/admin/cv/fields', {
      cv_group_id: g.id,
      label: d.label,
      type: d.type,
      visible_to: d.visible_to,
      required: d.required,
      help: d.help || null,
      options:
        d.type === 'select'
          ? d.optionsText
              .split(',')
              .map((s) => s.trim())
              .filter(Boolean)
          : null,
    })
    drafts.value[g.id] = blank()
  }, 'Variabel ditambah.')

const patchField = (f, patch) => call(() => api.put(`/admin/cv/fields/${f.id}`, patch))
const removeField = (f) =>
  window.confirm(`Hapus "${f.label}"? Jika sudah ada data, hanya dinonaktifkan.`) &&
  call(() => api.delete(`/admin/cv/fields/${f.id}`), 'Selesai.')

function move(list, i, d, type) {
  const ids = list.map((x) => x.id)
  const j = i + d
  if (j < 0 || j >= ids.length) return
  ;[ids[i], ids[j]] = [ids[j], ids[i]]
  call(() => api.post('/admin/cv/reorder', { type, ids }))
}
onMounted(load)
</script>

<template>
  <div class="acv">
    <h1>Manajemen CV</h1>
    <p class="acv__hint">
      Atur variabel yang muncul di CV pengguna. <strong>Tag tampil</strong> menentukan siapa selain
      pemilik yang bisa membaca isiannya: <em>Semua</em>, <em>Klien/HR</em> (grafolog tidak
      melihat), atau <em>Grafolog</em> (HR tidak melihat). Pemilik selalu bisa mengisi semuanya.
    </p>
    <form class="acv__new" @submit.prevent="addGroup">
      <input v-model="newGroup" placeholder="Nama grup baru (mis. Pasangan)" required />
      <button class="btn">Tambah grup</button>
    </form>
    <SkeletonLoader v-if="loading" variant="form" :rows="3" label="Memuat..." />
    <section v-for="(g, gi) in groups" v-else :key="g.id" class="acv__group">
      <header>
        <input
          :value="g.name"
          :aria-label="`Nama grup ${g.name}`"
          @change="renameGroup(g, $event.target.value)"
        />
        <button
          type="button"
          class="btn"
          :disabled="gi === 0"
          @click="move(groups, gi, -1, 'group')"
        >
          ↑
        </button>
        <button
          type="button"
          class="btn"
          :disabled="gi === groups.length - 1"
          @click="move(groups, gi, 1, 'group')"
        >
          ↓
        </button>
        <label class="acv__chk"
          ><input type="checkbox" :checked="g.is_active" @change="toggleGroup(g)" /> Aktif</label
        >
        <button type="button" class="btn" @click="removeGroup(g)">Hapus</button>
      </header>
      <table v-if="g.fields.length" class="acv__table">
        <thead>
          <tr>
            <th>Variabel</th>
            <th>Tipe</th>
            <th>Tampil</th>
            <th>Wajib</th>
            <th>Aktif</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="(f, fi) in g.fields" :key="f.id" :class="{ acv__off: !f.is_active }">
            <td>
              {{ f.label }}
              <small v-if="f.options" class="acv__hint">({{ f.options.join(', ') }})</small>
            </td>
            <td>{{ TYPES[f.type] }}</td>
            <td>
              <select
                :value="f.visible_to"
                :aria-label="`Tag ${f.label}`"
                @change="patchField(f, { visible_to: $event.target.value })"
              >
                <option v-for="(l, k) in TAGS" :key="k" :value="k">{{ l }}</option>
              </select>
            </td>
            <td>
              <input
                type="checkbox"
                :checked="f.required"
                :aria-label="`Wajib ${f.label}`"
                @change="patchField(f, { required: $event.target.checked })"
              />
            </td>
            <td>
              <input
                type="checkbox"
                :checked="f.is_active"
                :aria-label="`Aktif ${f.label}`"
                @change="patchField(f, { is_active: $event.target.checked })"
              />
            </td>
            <td class="acv__ops">
              <button
                type="button"
                class="btn"
                :disabled="fi === 0"
                @click="move(g.fields, fi, -1, 'field')"
              >
                ↑
              </button>
              <button
                type="button"
                class="btn"
                :disabled="fi === g.fields.length - 1"
                @click="move(g.fields, fi, 1, 'field')"
              >
                ↓
              </button>
              <button type="button" class="btn" @click="removeField(f)">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
      <form v-if="drafts[g.id]" class="acv__add" @submit.prevent="addField(g)">
        <input v-model="drafts[g.id].label" placeholder="Nama variabel" required />
        <select v-model="drafts[g.id].type" aria-label="Tipe data">
          <option v-for="(l, k) in TYPES" :key="k" :value="k">{{ l }}</option>
        </select>
        <input
          v-if="drafts[g.id].type === 'select'"
          v-model="drafts[g.id].optionsText"
          placeholder="Opsi, pisahkan koma"
          required
        />
        <select v-model="drafts[g.id].visible_to" aria-label="Tag tampil">
          <option v-for="(l, k) in TAGS" :key="k" :value="k">{{ l }}</option>
        </select>
        <label class="acv__chk"
          ><input v-model="drafts[g.id].required" type="checkbox" /> Wajib</label
        >
        <button class="btn btn--primary">Tambah variabel</button>
      </form>
    </section>
  </div>
</template>

<style scoped>
.acv {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.acv__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.acv__new,
.acv__add,
.acv__group header {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.acv__new input,
.acv__add input:not([type='checkbox']),
.acv__add select,
.acv__group header input:not([type='checkbox']) {
  width: auto;
}
.acv__group {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 14px;
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.acv__chk {
  display: inline-flex;
  gap: 4px;
  align-items: center;
  font-size: 13px;
}
.acv__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.acv__table th,
.acv__table td {
  text-align: left;
  padding: 6px 8px;
  border-bottom: 1px solid var(--color-border);
}
.acv__off {
  opacity: 0.55;
}
.acv__ops {
  white-space: nowrap;
}
</style>
