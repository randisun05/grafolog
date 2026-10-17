<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import SampleImages from '@/components/shared/SampleImages.vue'
import {
  EDUCATION,
  STATUS_LAYANAN,
  STATUS_PESANAN,
  TAHAP_KANDIDAT,
  TIPE_LAYANAN,
} from '@/lib/format'

const props = defineProps({ positions: { type: Array, default: () => [] } })
const emit = defineEmits(['order'])
const toast = useToast()

const candidates = ref([])
const loading = ref(true)
const filter = ref({ position_id: '', stage: '' })
const selected = ref([])
const photosOpenFor = ref(null)
const panel = ref(null) // null | 'add' | 'import'

const blank = () => ({
  name: '',
  email: '',
  phone: '',
  job_position_id: '',
  education: '',
  experience_years: '',
  notes: '',
  consent_confirmed: false,
})
const form = ref(blank())
const formErrors = ref({})
const saving = ref(false)

const file = ref(null)
const importPosition = ref('')
const importConsent = ref(false)
const importErrors = ref([])
const importing = ref(false)

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filter.value).filter(([, v]) => v))
    candidates.value = (await api.get('/hr/candidates', { params })).data
    selected.value = selected.value.filter((id) => candidates.value.some((c) => c.id === id))
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memuat kandidat.')
  } finally {
    loading.value = false
  }
}

watch(filter, load, { deep: true })

const latest = (c) => c.orders?.[0] ?? null
const allSelected = computed(
  () => candidates.value.length > 0 && selected.value.length === candidates.value.length,
)
function toggleAll() {
  selected.value = allSelected.value ? [] : candidates.value.map((c) => c.id)
}

async function addCandidate() {
  saving.value = true
  formErrors.value = {}
  try {
    const f = form.value
    await api.post('/hr/candidates', {
      ...f,
      phone: f.phone || null,
      job_position_id: f.job_position_id || null,
      education: f.education || null,
      experience_years: f.experience_years === '' ? null : f.experience_years,
      notes: f.notes || null,
    })
    toast.push(`${f.name} ditambahkan.`, 'success')
    form.value = blank()
    panel.value = null
    await load()
  } catch (e) {
    formErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal menambah kandidat.')
  } finally {
    saving.value = false
  }
}

async function submitImport() {
  if (!file.value) return
  importing.value = true
  importErrors.value = []
  try {
    const fd = new FormData()
    fd.append('file', file.value)
    if (importPosition.value) fd.append('job_position_id', importPosition.value)
    fd.append('consent_confirmed', importConsent.value ? '1' : '0')
    const { data } = await api.post('/hr/candidates/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    toast.push(`${data.imported} kandidat diimpor.`, 'success')
    file.value = null
    panel.value = null
    await load()
  } catch (e) {
    if (e.response?.status === 422 && Array.isArray(e.response.data.errors)) {
      importErrors.value = e.response.data.errors
    }
    toast.push(e.response?.data?.message ?? 'Gagal mengimpor kandidat.')
  } finally {
    importing.value = false
  }
}

async function setStage(c, stage) {
  try {
    await api.put(`/hr/candidates/${c.id}`, { stage })
    c.stage = stage
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah tahap.')
  }
}

defineExpose({ load })
onMounted(load)
</script>

<template>
  <div class="hrc">
    <div class="hrc__toolbar">
      <select v-model="filter.position_id" aria-label="Filter posisi">
        <option value="">Semua posisi</option>
        <option v-for="p in props.positions" :key="p.id" :value="p.id">{{ p.title }}</option>
      </select>
      <select v-model="filter.stage" aria-label="Filter tahap">
        <option value="">Semua tahap</option>
        <option v-for="(label, key) in TAHAP_KANDIDAT" :key="key" :value="key">{{ label }}</option>
      </select>
      <span class="hrc__spacer"></span>
      <button type="button" class="btn" @click="panel = panel === 'add' ? null : 'add'">
        + Kandidat
      </button>
      <button type="button" class="btn" @click="panel = panel === 'import' ? null : 'import'">
        Impor CSV
      </button>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="!selected.length"
        @click="emit('order', selected)"
      >
        Pesan asesmen ({{ selected.length }})
      </button>
    </div>

    <form v-if="panel === 'add'" class="hrc__form" @submit.prevent="addCandidate">
      <div class="hrc__grid">
        <label
          >Nama <input v-model="form.name" type="text" required />
          <span v-if="formErrors.name" class="error">{{ formErrors.name[0] }}</span></label
        >
        <label
          >Email <input v-model="form.email" type="email" required />
          <span v-if="formErrors.email" class="error">{{ formErrors.email[0] }}</span></label
        >
        <label>Nomor HP <input v-model="form.phone" type="text" /></label>
        <label
          >Posisi
          <select v-model="form.job_position_id">
            <option value="">-</option>
            <option v-for="p in props.positions" :key="p.id" :value="p.id">{{ p.title }}</option>
          </select></label
        >
        <label
          >Pendidikan
          <select v-model="form.education">
            <option value="">-</option>
            <option v-for="e in EDUCATION" :key="e" :value="e">{{ e }}</option>
          </select></label
        >
        <label
          >Pengalaman (tahun)
          <input v-model.number="form.experience_years" type="number" min="0" max="60"
        /></label>
      </div>
      <label>Catatan <textarea v-model="form.notes" rows="2"></textarea></label>
      <label class="hrc__consent">
        <input v-model="form.consent_confirmed" type="checkbox" />
        Kandidat sudah memberi persetujuan tertulis atas pemrosesan tulisan tangan &amp; hasil
        analisisnya.
      </label>
      <button type="submit" class="btn btn--primary" :disabled="saving || !form.consent_confirmed">
        {{ saving ? 'Menyimpan...' : 'Tambah kandidat' }}
      </button>
    </form>

    <form v-if="panel === 'import'" class="hrc__form" @submit.prevent="submitImport">
      <p class="hrc__hint">
        Kolom wajib <code>name</code>, <code>email</code>; opsional <code>phone</code>. Satu baris
        tidak valid menggagalkan seluruh impor.
      </p>
      <div class="hrc__grid">
        <label
          >File CSV
          <input
            type="file"
            accept=".csv,text/csv"
            required
            @change="file = $event.target.files[0] ?? null"
        /></label>
        <label
          >Masukkan ke posisi
          <select v-model="importPosition">
            <option value="">-</option>
            <option v-for="p in props.positions" :key="p.id" :value="p.id">{{ p.title }}</option>
          </select></label
        >
      </div>
      <label class="hrc__consent">
        <input v-model="importConsent" type="checkbox" />
        Semua kandidat di file ini sudah memberi persetujuan tertulis.
      </label>
      <ul v-if="importErrors.length" class="hrc__errors">
        <li v-for="err in importErrors" :key="err.line">
          Baris {{ err.line }}: {{ err.errors.join(', ') }}
        </li>
      </ul>
      <button
        type="submit"
        class="btn btn--primary"
        :disabled="!file || !importConsent || importing"
      >
        {{ importing ? 'Mengimpor...' : 'Impor' }}
      </button>
    </form>

    <SkeletonLoader v-if="loading" variant="table" :columns="5" label="Memuat kandidat..." />
    <p v-else-if="!candidates.length" class="hrc__hint">Belum ada kandidat.</p>
    <div v-else class="hrc__table-wrap">
      <table class="hrc__table">
        <thead>
          <tr>
            <th>
              <input
                type="checkbox"
                :checked="allSelected"
                aria-label="Pilih semua"
                @change="toggleAll"
              />
            </th>
            <th>Kandidat</th>
            <th>Posisi</th>
            <th>Tahap</th>
            <th>Asesmen terakhir</th>
            <th>Scan &amp; hasil</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="c in candidates" :key="c.id">
            <tr>
              <td>
                <input
                  v-model="selected"
                  type="checkbox"
                  :value="c.id"
                  :aria-label="`Pilih ${c.user.name}`"
                />
              </td>
              <td>
                <span class="hrc__name">{{ c.user.name }}</span>
                <span class="hrc__sub">{{ c.user.email }}</span>
                <span v-if="c.education || c.experience_years !== null" class="hrc__sub"
                  >{{ c.education || '-' }} · {{ c.experience_years ?? '-' }} th</span
                >
              </td>
              <td>{{ c.position?.title ?? '-' }}</td>
              <td>
                <select
                  :value="c.stage"
                  :aria-label="`Tahap ${c.user.name}`"
                  @change="setStage(c, $event.target.value)"
                >
                  <option v-for="(label, key) in TAHAP_KANDIDAT" :key="key" :value="key">
                    {{ label }}
                  </option>
                </select>
              </td>
              <td>
                <template v-if="latest(c)">
                  <span class="hrc__name">{{ latest(c).product }}</span>
                  <span class="hrc__sub"
                    >{{ STATUS_PESANAN[latest(c).status] }} · {{ latest(c).grafolog ?? '-' }}</span
                  >
                  <span v-for="d in latest(c).deliveries" :key="d.id" class="hrc__sub"
                    >{{ TIPE_LAYANAN[d.tipe] ?? d.nama }}: {{ STATUS_LAYANAN[d.status] }}</span
                  >
                </template>
                <span v-else class="hrc__sub">Belum dipesan</span>
              </td>
              <td class="hrc__actions">
                <button
                  v-if="latest(c)?.sample_id && latest(c).status === 'diproses'"
                  type="button"
                  class="btn"
                  @click="photosOpenFor = photosOpenFor === c.id ? null : c.id"
                >
                  {{ latest(c).image_count }} scan
                </button>
                <RouterLink
                  v-if="latest(c)?.report_id"
                  :to="`/reports/${latest(c).report_id}`"
                  class="btn"
                  >Laporan</RouterLink
                >
              </td>
            </tr>
            <tr v-if="photosOpenFor === c.id">
              <td colspan="6">
                <SampleImages
                  :sample-id="latest(c).sample_id"
                  :can-upload="latest(c).sample_status !== 'completed'"
                  @changed="load"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.hrc {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.hrc__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.hrc__toolbar select {
  width: auto;
  margin: 0;
}
.hrc__spacer {
  flex: 1;
}
.hrc__form {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hrc__form label {
  font-size: 13px;
  color: var(--color-text-soft);
}
.hrc__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}
.hrc__form input[type='number'] {
  display: block;
  width: 100%;
  padding: 9px 10px;
  margin-top: 4px;
  box-sizing: border-box;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
}
.hrc__consent {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}
.hrc__consent input {
  width: auto;
  margin-top: 3px;
}
.hrc__form .btn {
  align-self: flex-start;
}
.hrc__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.hrc__errors {
  color: var(--color-error);
  font-size: 12.5px;
  margin: 0;
  padding-left: 18px;
}
.hrc__table-wrap {
  overflow-x: auto;
}
.hrc__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.hrc__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12.5px;
  border-bottom: 1px solid var(--color-border);
}
.hrc__table td {
  padding: 10px;
  border-bottom: 1px solid var(--color-border);
  vertical-align: top;
}
.hrc__table td select {
  margin: 0;
  width: auto;
  font-size: 13px;
  padding: 5px 8px;
}
.hrc__table input[type='checkbox'] {
  width: auto;
}
.hrc__name {
  display: block;
  font-weight: 500;
}
.hrc__sub {
  display: block;
  font-size: 12px;
  color: var(--color-text-soft);
}
.hrc__actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
</style>
