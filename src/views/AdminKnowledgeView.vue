<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const tabs = [
  { key: 'sindrom', label: 'Sindrom' },
  { key: 'aspek', label: 'Aspek' },
  { key: 'variabel', label: 'Variabel Ukur' },
  { key: 'band', label: 'Band Skor' },
]
const activeTab = ref('sindrom')

const loading = ref(true)
const loadError = ref('')

// --- Sindrom -----------------------------------------------------------
const sindromList = ref([])
const sindromForm = ref({ kode_romawi: '', nama: '', polaritas_inferred: 'HIJAU', catatan_polaritas: '' })
const sindromErrors = ref({})
const sindromSubmitting = ref(false)
const sindromEditingId = ref(null)
const sindromEditForm = ref({})

function startEditSindrom(s) {
  sindromEditingId.value = s.id
  sindromEditForm.value = { kode_romawi: s.kode_romawi, nama: s.nama, polaritas_inferred: s.polaritas_inferred, catatan_polaritas: s.catatan_polaritas ?? '' }
}
function cancelEditSindrom() {
  sindromEditingId.value = null
}
async function createSindrom() {
  sindromSubmitting.value = true
  sindromErrors.value = {}
  try {
    await api.post('/admin/knowledge/sindrom', sindromForm.value)
    toast.push('Sindrom berhasil dibuat.', 'success')
    sindromForm.value = { kode_romawi: '', nama: '', polaritas_inferred: 'HIJAU', catatan_polaritas: '' }
    await loadSindrom()
  } catch (e) {
    sindromErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat Sindrom.')
  } finally {
    sindromSubmitting.value = false
  }
}
async function saveSindrom(id) {
  try {
    await api.put(`/admin/knowledge/sindrom/${id}`, sindromEditForm.value)
    toast.push('Sindrom berhasil diubah.', 'success')
    sindromEditingId.value = null
    await loadSindrom()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah Sindrom.')
  }
}
async function deleteSindrom(s) {
  if (s.aspek_count > 0) {
    toast.push(`Tidak bisa menghapus - masih punya ${s.aspek_count} Aspek terkait.`)
    return
  }
  if (!confirm(`Hapus Sindrom "${s.nama}"?`)) return
  try {
    await api.delete(`/admin/knowledge/sindrom/${s.id}`)
    toast.push('Sindrom dihapus.', 'success')
    await loadSindrom()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus Sindrom.')
  }
}
async function loadSindrom() {
  const { data } = await api.get('/admin/knowledge/sindrom')
  sindromList.value = data
}

// --- Aspek ---------------------------------------------------------------
const emptyAspekForm = () => ({
  kode: '',
  sindrom_id: '',
  nama: '',
  keterangan_umum: '',
  narasi_very_high: '',
  narasi_high: '',
  narasi_medium: '',
  narasi_low: '',
})
const aspekList = ref([])
const aspekForm = ref({ kode: '', sindrom_id: '', nama: '' })
const aspekErrors = ref({})
const aspekSubmitting = ref(false)
const expandedAspekId = ref(null)
const aspekEditForm = ref(emptyAspekForm())
const aspekSaving = ref(false)

function startEditAspek(a) {
  expandedAspekId.value = a.id
  aspekEditForm.value = {
    kode: a.kode,
    sindrom_id: a.sindrom_id,
    nama: a.nama,
    keterangan_umum: a.keterangan_umum ?? '',
    narasi_very_high: a.narasi_very_high ?? '',
    narasi_high: a.narasi_high ?? '',
    narasi_medium: a.narasi_medium ?? '',
    narasi_low: a.narasi_low ?? '',
  }
}
function cancelEditAspek() {
  expandedAspekId.value = null
}
async function createAspek() {
  aspekSubmitting.value = true
  aspekErrors.value = {}
  try {
    await api.post('/admin/knowledge/aspek', aspekForm.value)
    toast.push('Aspek berhasil dibuat.', 'success')
    aspekForm.value = { kode: '', sindrom_id: '', nama: '' }
    await loadAspek()
  } catch (e) {
    aspekErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat Aspek.')
  } finally {
    aspekSubmitting.value = false
  }
}
async function saveAspek(id) {
  aspekSaving.value = true
  try {
    await api.put(`/admin/knowledge/aspek/${id}`, aspekEditForm.value)
    toast.push('Aspek berhasil diubah.', 'success')
    expandedAspekId.value = null
    await loadAspek()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah Aspek.')
  } finally {
    aspekSaving.value = false
  }
}
async function deleteAspek(a) {
  if (a.indikator_count > 0) {
    toast.push(`Tidak bisa menghapus - masih punya ${a.indikator_count} Indikator terkait.`)
    return
  }
  if (!confirm(`Hapus Aspek "${a.nama}"?`)) return
  try {
    await api.delete(`/admin/knowledge/aspek/${a.id}`)
    toast.push('Aspek dihapus.', 'success')
    await loadAspek()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus Aspek.')
  }
}
async function loadAspek() {
  const { data } = await api.get('/admin/knowledge/aspek')
  aspekList.value = data
}

// --- Measurement Variable + Category ------------------------------------
const variableList = ref([])
const variableForm = ref({ kode: '', axis: 'vertical', nama: '' })
const variableErrors = ref({})
const variableSubmitting = ref(false)
const variableEditingId = ref(null)
const variableEditForm = ref({})
const expandedVariableId = ref(null)
const categoryForm = ref({ kategori: '', rentang: '', unit: '', urutan: 1 })
const categoryErrors = ref({})
const categoryEditingId = ref(null)
const categoryEditForm = ref({})

function startEditVariable(v) {
  variableEditingId.value = v.id
  variableEditForm.value = { kode: v.kode, axis: v.axis, nama: v.nama }
}
function cancelEditVariable() {
  variableEditingId.value = null
}
async function createVariable() {
  variableSubmitting.value = true
  variableErrors.value = {}
  try {
    await api.post('/admin/knowledge/measurement-variables', variableForm.value)
    toast.push('Variabel ukur berhasil dibuat.', 'success')
    variableForm.value = { kode: '', axis: 'vertical', nama: '' }
    await loadVariables()
  } catch (e) {
    variableErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat variabel ukur.')
  } finally {
    variableSubmitting.value = false
  }
}
async function saveVariable(id) {
  try {
    await api.put(`/admin/knowledge/measurement-variables/${id}`, variableEditForm.value)
    toast.push('Variabel ukur berhasil diubah.', 'success')
    variableEditingId.value = null
    await loadVariables()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah variabel ukur.')
  }
}
async function deleteVariable(v) {
  if (!confirm(`Hapus variabel "${v.nama}"? Semua ${v.kategori.length} kategorinya ikut terhapus.`)) return
  try {
    await api.delete(`/admin/knowledge/measurement-variables/${v.id}`)
    toast.push('Variabel ukur dihapus.', 'success')
    await loadVariables()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus variabel ukur.')
  }
}
function toggleExpandVariable(v) {
  expandedVariableId.value = expandedVariableId.value === v.id ? null : v.id
  categoryForm.value = { kategori: '', rentang: '', unit: '', urutan: (v.kategori?.length ?? 0) + 1 }
}
async function createCategory(variable) {
  categoryErrors.value = {}
  try {
    await api.post(`/admin/knowledge/measurement-variables/${variable.id}/categories`, categoryForm.value)
    toast.push('Kategori berhasil ditambahkan.', 'success')
    categoryForm.value = { kategori: '', rentang: '', unit: '', urutan: 1 }
    await loadVariables()
  } catch (e) {
    categoryErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal menambahkan kategori.')
  }
}
function startEditCategory(c) {
  categoryEditingId.value = c.id
  categoryEditForm.value = { kategori: c.kategori, rentang: c.rentang ?? '', unit: c.unit ?? '', urutan: c.urutan }
}
function cancelEditCategory() {
  categoryEditingId.value = null
}
async function saveCategory(id) {
  try {
    await api.put(`/admin/knowledge/measurement-categories/${id}`, categoryEditForm.value)
    toast.push('Kategori berhasil diubah.', 'success')
    categoryEditingId.value = null
    await loadVariables()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah kategori.')
  }
}
async function deleteCategory(c) {
  if (!confirm(`Hapus kategori "${c.kategori}"?`)) return
  try {
    await api.delete(`/admin/knowledge/measurement-categories/${c.id}`)
    toast.push('Kategori dihapus.', 'success')
    await loadVariables()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus kategori.')
  }
}
async function loadVariables() {
  const { data } = await api.get('/admin/knowledge/measurement-variables')
  variableList.value = data
}

// --- Scoring Rule Band ---------------------------------------------------
const bandList = ref([])
const bandForm = ref({ polaritas: 'HIJAU', label: '', rentang_skor: '' })
const bandErrors = ref({})
const bandSubmitting = ref(false)
const bandEditingId = ref(null)
const bandEditForm = ref({})

function startEditBand(b) {
  bandEditingId.value = b.id
  bandEditForm.value = { polaritas: b.polaritas, label: b.label, rentang_skor: b.rentang_skor }
}
function cancelEditBand() {
  bandEditingId.value = null
}
async function createBand() {
  bandSubmitting.value = true
  bandErrors.value = {}
  try {
    await api.post('/admin/knowledge/scoring-rule-bands', bandForm.value)
    toast.push('Band skor berhasil dibuat.', 'success')
    bandForm.value = { polaritas: 'HIJAU', label: '', rentang_skor: '' }
    await loadBands()
  } catch (e) {
    bandErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat band skor.')
  } finally {
    bandSubmitting.value = false
  }
}
async function saveBand(id) {
  try {
    await api.put(`/admin/knowledge/scoring-rule-bands/${id}`, bandEditForm.value)
    toast.push('Band skor berhasil diubah.', 'success')
    bandEditingId.value = null
    await loadBands()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah band skor.')
  }
}
async function deleteBand(b) {
  if (!confirm(`Hapus band "${b.label}"?`)) return
  try {
    await api.delete(`/admin/knowledge/scoring-rule-bands/${b.id}`)
    toast.push('Band skor dihapus.', 'success')
    await loadBands()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus band skor.')
  }
}
async function loadBands() {
  const { data } = await api.get('/admin/knowledge/scoring-rule-bands')
  bandList.value = data
}

onMounted(async () => {
  loading.value = true
  loadError.value = ''
  try {
    await Promise.all([loadSindrom(), loadAspek(), loadVariables(), loadBands()])
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat knowledge base.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="admin-km">
    <h1>Kelola Knowledge Base</h1>
    <p class="admin-km__note">
      Fondasi metodologi penilaian (KM-B/C) — Sindrom, Aspek + 4 level narasi, Variabel Ukur +
      kategorinya, dan Band Skor. Indikator dan aturan operator belum punya panel di sini (fase KM
      lanjutan).
    </p>

    <div class="admin-km__tabs">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="admin-km__tab"
        :class="{ 'admin-km__tab--active': activeTab === t.key }"
        @click="activeTab = t.key"
      >
        {{ t.label }}
      </button>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <template v-else>
      <!-- SINDROM -->
      <section v-if="activeTab === 'sindrom'" class="admin-km__panel">
        <form class="admin-km__form" @submit.prevent="createSindrom">
          <div class="admin-km__row">
            <label>
              Kode Romawi
              <input v-model="sindromForm.kode_romawi" type="text" maxlength="5" placeholder="IX" required />
            </label>
            <label>
              Polaritas
              <select v-model="sindromForm.polaritas_inferred">
                <option value="HIJAU">HIJAU</option>
                <option value="MERAH">MERAH</option>
              </select>
            </label>
          </div>
          <label>
            Nama
            <input v-model="sindromForm.nama" type="text" maxlength="100" required />
          </label>
          <label>
            Catatan Polaritas (opsional)
            <textarea v-model="sindromForm.catatan_polaritas" rows="2"></textarea>
          </label>
          <p v-if="sindromErrors.kode_romawi" class="error">{{ sindromErrors.kode_romawi[0] }}</p>
          <button type="submit" class="btn btn--primary" :disabled="sindromSubmitting">
            {{ sindromSubmitting ? 'Membuat...' : 'Tambah Sindrom' }}
          </button>
        </form>

        <table class="admin-km__table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama</th>
              <th>Polaritas</th>
              <th># Aspek</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in sindromList" :key="s.id">
              <template v-if="sindromEditingId === s.id">
                <td><input v-model="sindromEditForm.kode_romawi" type="text" maxlength="5" /></td>
                <td><input v-model="sindromEditForm.nama" type="text" maxlength="100" /></td>
                <td>
                  <select v-model="sindromEditForm.polaritas_inferred">
                    <option value="HIJAU">HIJAU</option>
                    <option value="MERAH">MERAH</option>
                  </select>
                </td>
                <td>{{ s.aspek_count }}</td>
                <td class="admin-km__actions">
                  <button type="button" class="btn btn--primary" @click="saveSindrom(s.id)">Simpan</button>
                  <button type="button" class="btn" @click="cancelEditSindrom">Batal</button>
                </td>
              </template>
              <template v-else>
                <td>{{ s.kode_romawi }}</td>
                <td>{{ s.nama }}</td>
                <td>
                  <span class="badge" :class="s.polaritas_inferred === 'HIJAU' ? 'badge--hijau' : 'badge--merah'">
                    {{ s.polaritas_inferred }}
                  </span>
                </td>
                <td>{{ s.aspek_count }}</td>
                <td class="admin-km__actions">
                  <button type="button" class="btn" @click="startEditSindrom(s)">Ubah</button>
                  <button type="button" class="btn btn--danger" @click="deleteSindrom(s)">Hapus</button>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- ASPEK -->
      <section v-if="activeTab === 'aspek'" class="admin-km__panel">
        <form class="admin-km__form" @submit.prevent="createAspek">
          <div class="admin-km__row">
            <label>
              Kode
              <input v-model="aspekForm.kode" type="text" maxlength="10" placeholder="41" required />
            </label>
            <label>
              Sindrom
              <select v-model="aspekForm.sindrom_id" required>
                <option value="" disabled>Pilih Sindrom</option>
                <option v-for="s in sindromList" :key="s.id" :value="s.id">{{ s.kode_romawi }} — {{ s.nama }}</option>
              </select>
            </label>
          </div>
          <label>
            Nama
            <input v-model="aspekForm.nama" type="text" maxlength="150" required />
          </label>
          <p v-if="aspekErrors.kode" class="error">{{ aspekErrors.kode[0] }}</p>
          <p v-if="aspekErrors.sindrom_id" class="error">{{ aspekErrors.sindrom_id[0] }}</p>
          <p class="admin-km__hint">Keterangan umum &amp; 4 level narasi diisi setelah dibuat, lewat "Ubah".</p>
          <button type="submit" class="btn btn--primary" :disabled="aspekSubmitting">
            {{ aspekSubmitting ? 'Membuat...' : 'Tambah Aspek' }}
          </button>
        </form>

        <div v-for="a in aspekList" :key="a.id" class="admin-km__variable">
          <div class="admin-km__variable-head" @click="expandedAspekId === a.id ? cancelEditAspek() : startEditAspek(a)">
            <span class="admin-km__variable-kode">{{ a.kode }}</span>
            <span class="admin-km__variable-nama">{{ a.nama }}</span>
            <span class="admin-km__variable-axis">{{ a.sindrom.kode_romawi }} — {{ a.sindrom.nama }}</span>
            <span class="admin-km__variable-count">{{ a.indikator_count }} indikator</span>
            <span class="admin-km__actions" @click.stop>
              <button type="button" class="btn" @click="expandedAspekId === a.id ? cancelEditAspek() : startEditAspek(a)">
                {{ expandedAspekId === a.id ? 'Tutup' : 'Ubah' }}
              </button>
              <button type="button" class="btn btn--danger" @click="deleteAspek(a)">Hapus</button>
            </span>
          </div>

          <div v-if="expandedAspekId === a.id" class="admin-km__categories">
            <div class="admin-km__row">
              <label>
                Kode
                <input v-model="aspekEditForm.kode" type="text" maxlength="10" />
              </label>
              <label>
                Sindrom
                <select v-model="aspekEditForm.sindrom_id">
                  <option v-for="s in sindromList" :key="s.id" :value="s.id">{{ s.kode_romawi }} — {{ s.nama }}</option>
                </select>
              </label>
            </div>
            <label class="admin-km__field">
              Nama
              <input v-model="aspekEditForm.nama" type="text" maxlength="150" />
            </label>
            <label class="admin-km__field">
              Keterangan Umum
              <textarea v-model="aspekEditForm.keterangan_umum" rows="2"></textarea>
            </label>
            <label class="admin-km__field">
              Narasi — Very High
              <textarea v-model="aspekEditForm.narasi_very_high" rows="3"></textarea>
            </label>
            <label class="admin-km__field">
              Narasi — High
              <textarea v-model="aspekEditForm.narasi_high" rows="3"></textarea>
            </label>
            <label class="admin-km__field">
              Narasi — Medium
              <textarea v-model="aspekEditForm.narasi_medium" rows="3"></textarea>
            </label>
            <label class="admin-km__field">
              Narasi — Low
              <textarea v-model="aspekEditForm.narasi_low" rows="3"></textarea>
            </label>
            <div class="admin-km__actions">
              <button type="button" class="btn btn--primary" :disabled="aspekSaving" @click="saveAspek(a.id)">
                {{ aspekSaving ? 'Menyimpan...' : 'Simpan' }}
              </button>
              <button type="button" class="btn" @click="cancelEditAspek">Batal</button>
            </div>
          </div>
        </div>
      </section>

      <!-- MEASUREMENT VARIABLE -->
      <section v-if="activeTab === 'variabel'" class="admin-km__panel">
        <form class="admin-km__form" @submit.prevent="createVariable">
          <div class="admin-km__row">
            <label>
              Kode
              <input v-model="variableForm.kode" type="text" maxlength="30" required />
            </label>
            <label>
              Axis
              <select v-model="variableForm.axis">
                <option value="vertical">vertical</option>
                <option value="horizontal">horizontal</option>
              </select>
            </label>
          </div>
          <label>
            Nama
            <input v-model="variableForm.nama" type="text" maxlength="150" required />
          </label>
          <p v-if="variableErrors.kode" class="error">{{ variableErrors.kode[0] }}</p>
          <button type="submit" class="btn btn--primary" :disabled="variableSubmitting">
            {{ variableSubmitting ? 'Membuat...' : 'Tambah Variabel' }}
          </button>
        </form>

        <div v-for="v in variableList" :key="v.id" class="admin-km__variable">
          <div class="admin-km__variable-head" @click="toggleExpandVariable(v)">
            <template v-if="variableEditingId === v.id">
              <input v-model="variableEditForm.kode" type="text" maxlength="30" @click.stop />
              <input v-model="variableEditForm.nama" type="text" maxlength="150" @click.stop />
              <select v-model="variableEditForm.axis" @click.stop>
                <option value="vertical">vertical</option>
                <option value="horizontal">horizontal</option>
              </select>
              <span class="admin-km__actions" @click.stop>
                <button type="button" class="btn btn--primary" @click="saveVariable(v.id)">Simpan</button>
                <button type="button" class="btn" @click="cancelEditVariable">Batal</button>
              </span>
            </template>
            <template v-else>
              <span class="admin-km__variable-kode">{{ v.kode }}</span>
              <span class="admin-km__variable-nama">{{ v.nama }}</span>
              <span class="admin-km__variable-axis">{{ v.axis }}</span>
              <span class="admin-km__variable-count">{{ v.kategori.length }} kategori</span>
              <span class="admin-km__actions" @click.stop>
                <button type="button" class="btn" @click="startEditVariable(v)">Ubah</button>
                <button type="button" class="btn btn--danger" @click="deleteVariable(v)">Hapus</button>
              </span>
            </template>
          </div>

          <div v-if="expandedVariableId === v.id" class="admin-km__categories">
            <table class="admin-km__table admin-km__table--nested">
              <thead>
                <tr>
                  <th>Urutan</th>
                  <th>Kategori</th>
                  <th>Rentang</th>
                  <th>Unit</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in v.kategori" :key="c.id">
                  <template v-if="categoryEditingId === c.id">
                    <td><input v-model.number="categoryEditForm.urutan" type="number" min="1" /></td>
                    <td><input v-model="categoryEditForm.kategori" type="text" maxlength="50" /></td>
                    <td><input v-model="categoryEditForm.rentang" type="text" maxlength="30" /></td>
                    <td><input v-model="categoryEditForm.unit" type="text" maxlength="20" /></td>
                    <td class="admin-km__actions">
                      <button type="button" class="btn btn--primary" @click="saveCategory(c.id)">Simpan</button>
                      <button type="button" class="btn" @click="cancelEditCategory">Batal</button>
                    </td>
                  </template>
                  <template v-else>
                    <td>{{ c.urutan }}</td>
                    <td>{{ c.kategori }}</td>
                    <td>{{ c.rentang ?? '-' }}</td>
                    <td>{{ c.unit ?? '-' }}</td>
                    <td class="admin-km__actions">
                      <button type="button" class="btn" @click="startEditCategory(c)">Ubah</button>
                      <button type="button" class="btn btn--danger" @click="deleteCategory(c)">Hapus</button>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>

            <form class="admin-km__category-form" @submit.prevent="createCategory(v)">
              <input v-model.number="categoryForm.urutan" type="number" min="1" placeholder="Urutan" required />
              <input v-model="categoryForm.kategori" type="text" maxlength="50" placeholder="Kategori (mis. extremely small)" required />
              <input v-model="categoryForm.rentang" type="text" maxlength="30" placeholder="Rentang (mis. 0-2)" />
              <input v-model="categoryForm.unit" type="text" maxlength="20" placeholder="Unit (mis. mm)" />
              <button type="submit" class="btn btn--primary">Tambah Kategori</button>
            </form>
            <p v-if="categoryErrors.urutan" class="error">{{ categoryErrors.urutan[0] }}</p>
          </div>
        </div>
      </section>

      <!-- SCORING RULE BAND -->
      <section v-if="activeTab === 'band'" class="admin-km__panel">
        <form class="admin-km__form" @submit.prevent="createBand">
          <div class="admin-km__row">
            <label>
              Polaritas
              <select v-model="bandForm.polaritas">
                <option value="HIJAU">HIJAU</option>
                <option value="MERAH">MERAH</option>
              </select>
            </label>
            <label>
              Rentang Skor
              <input v-model="bandForm.rentang_skor" type="text" maxlength="10" placeholder="1-3" required />
            </label>
          </div>
          <label>
            Label
            <input v-model="bandForm.label" type="text" maxlength="30" placeholder="Nilai Rendah" required />
          </label>
          <p v-if="bandErrors.label" class="error">{{ bandErrors.label[0] }}</p>
          <button type="submit" class="btn btn--primary" :disabled="bandSubmitting">
            {{ bandSubmitting ? 'Membuat...' : 'Tambah Band' }}
          </button>
        </form>

        <table class="admin-km__table">
          <thead>
            <tr>
              <th>Polaritas</th>
              <th>Label</th>
              <th>Rentang Skor</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in bandList" :key="b.id">
              <template v-if="bandEditingId === b.id">
                <td>
                  <select v-model="bandEditForm.polaritas">
                    <option value="HIJAU">HIJAU</option>
                    <option value="MERAH">MERAH</option>
                  </select>
                </td>
                <td><input v-model="bandEditForm.label" type="text" maxlength="30" /></td>
                <td><input v-model="bandEditForm.rentang_skor" type="text" maxlength="10" /></td>
                <td class="admin-km__actions">
                  <button type="button" class="btn btn--primary" @click="saveBand(b.id)">Simpan</button>
                  <button type="button" class="btn" @click="cancelEditBand">Batal</button>
                </td>
              </template>
              <template v-else>
                <td>
                  <span class="badge" :class="b.polaritas === 'HIJAU' ? 'badge--hijau' : 'badge--merah'">
                    {{ b.polaritas }}
                  </span>
                </td>
                <td>{{ b.label }}</td>
                <td>{{ b.rentang_skor }}</td>
                <td class="admin-km__actions">
                  <button type="button" class="btn" @click="startEditBand(b)">Ubah</button>
                  <button type="button" class="btn btn--danger" @click="deleteBand(b)">Hapus</button>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<style scoped>
.admin-km {
  max-width: 960px;
}
.admin-km__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-km__tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--color-border);
}
.admin-km__tab {
  padding: 8px 16px;
  border: none;
  background: none;
  color: var(--color-text-soft);
  font-size: 14px;
  cursor: pointer;
  border-bottom: 2px solid transparent;
}
.admin-km__tab--active {
  color: var(--color-seal);
  border-bottom-color: var(--color-seal);
  font-weight: 600;
}
.admin-km__form {
  padding: 18px;
  margin-bottom: 24px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-km__form label {
  display: block;
  margin-bottom: 12px;
  font-size: 14px;
  color: var(--color-text-soft);
}
.admin-km__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.admin-km__hint {
  font-size: 12px;
  color: var(--color-text-soft);
  margin: -6px 0 12px;
}
.admin-km__field {
  display: block;
  margin-bottom: 12px;
  font-size: 14px;
  color: var(--color-text-soft);
}
.admin-km__field textarea {
  width: 100%;
}
.admin-km__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.admin-km__table th {
  text-align: left;
  padding: 8px 10px;
  font-size: 11.5px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-soft);
  border-bottom: 1px solid var(--color-border);
}
.admin-km__table td {
  padding: 8px 10px;
  border-bottom: 1px solid var(--color-border);
  vertical-align: middle;
}
.admin-km__table td input,
.admin-km__table td select {
  margin: 0;
  padding: 4px 6px;
  font-size: 13px;
}
.admin-km__actions {
  display: flex;
  gap: 6px;
  white-space: nowrap;
}
.btn--danger {
  border-color: var(--color-seal);
  color: var(--color-seal);
}
.btn--danger:hover {
  background: var(--color-seal-soft);
}
.badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}
.badge--hijau {
  background: var(--color-sage-soft);
  color: var(--color-sage);
}
.badge--merah {
  background: var(--color-seal-soft);
  color: var(--color-seal);
}
.admin-km__variable {
  margin-bottom: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  overflow: hidden;
}
.admin-km__variable-head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 14px;
  cursor: pointer;
  font-size: 13.5px;
}
.admin-km__variable-head:hover {
  background: var(--color-paper-alt);
}
.admin-km__variable-kode {
  font-family: var(--font-heading);
  font-weight: 600;
  min-width: 32px;
}
.admin-km__variable-nama {
  flex: 1;
}
.admin-km__variable-axis,
.admin-km__variable-count {
  color: var(--color-text-soft);
  font-size: 12px;
}
.admin-km__categories {
  padding: 12px 14px 16px;
  border-top: 1px solid var(--color-border);
  background: var(--color-paper-alt);
}
.admin-km__table--nested {
  margin-bottom: 12px;
}
.admin-km__category-form {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.admin-km__category-form input {
  margin: 0;
  width: auto;
  flex: 1;
  min-width: 100px;
}
</style>
