<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const loading = ref(true)
const temuanList = ref([])
const indikatorOptions = ref([])
const aspekOptions = ref([])
const sindromOptions = ref([])

const createForm = ref({ nama: '', teks_interpretasi: '', logika_gabung: 'OR' })
const createErrors = ref({})
const submitting = ref(false)

const expandedId = ref(null)
const editForm = ref({ nama: '', teks_interpretasi: '', logika_gabung: 'OR' })

const syaratForm = ref({ level: 'aspek', indikator_id: '', aspek_id: '', sindrom_id: '', kondisi: '' })
const syaratSubmitting = ref(false)

const kondisiOptions = {
  indikator: [
    { value: 'tercentang', label: 'Tercentang' },
    { value: 'tidak_tercentang', label: 'Tidak tercentang' },
  ],
  aspek: [
    { value: 'low', label: 'Rendah (low)' },
    { value: 'medium', label: 'Sedang (medium)' },
    { value: 'high', label: 'Tinggi (high)' },
    { value: 'very_high', label: 'Sangat Tinggi (very_high)' },
  ],
}
kondisiOptions.sindrom = kondisiOptions.aspek

async function load() {
  loading.value = true
  try {
    const [temuanRes, indikatorRes, aspekRes, sindromRes] = await Promise.all([
      api.get('/admin/knowledge/kombinasi'),
      api.get('/admin/knowledge/indikator-options'),
      api.get('/admin/knowledge/aspek'),
      api.get('/admin/knowledge/sindrom'),
    ])
    temuanList.value = temuanRes.data
    indikatorOptions.value = indikatorRes.data
    aspekOptions.value = aspekRes.data
    sindromOptions.value = sindromRes.data
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memuat Kombinasi Temuan.')
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function createTemuan() {
  submitting.value = true
  createErrors.value = {}
  try {
    const { data } = await api.post('/admin/knowledge/kombinasi', createForm.value)
    temuanList.value = [...temuanList.value, data].sort((a, b) => a.nama.localeCompare(b.nama))
    createForm.value = { nama: '', teks_interpretasi: '', logika_gabung: 'OR' }
    toast.push('Kombinasi temuan dibuat.', 'success')
  } catch (e) {
    createErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat kombinasi temuan.')
  } finally {
    submitting.value = false
  }
}

function startEdit(temuan) {
  expandedId.value = temuan.id
  editForm.value = { nama: temuan.nama, teks_interpretasi: temuan.teks_interpretasi, logika_gabung: temuan.logika_gabung }
  syaratForm.value = { level: 'aspek', indikator_id: '', aspek_id: '', sindrom_id: '', kondisi: '' }
}
function cancelEdit() {
  expandedId.value = null
}

async function saveEdit(temuan) {
  try {
    const { data } = await api.put(`/admin/knowledge/kombinasi/${temuan.id}`, editForm.value)
    temuanList.value = temuanList.value.map((t) => (t.id === temuan.id ? data : t))
    toast.push('Kombinasi temuan diperbarui.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan perubahan.')
  }
}

async function deleteTemuan(temuan) {
  if (!confirm(`Hapus kombinasi temuan "${temuan.nama}" beserta seluruh syaratnya?`)) return
  try {
    await api.delete(`/admin/knowledge/kombinasi/${temuan.id}`)
    temuanList.value = temuanList.value.filter((t) => t.id !== temuan.id)
    toast.push('Kombinasi temuan dihapus.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus.')
  }
}

async function addSyarat(temuan) {
  syaratSubmitting.value = true
  try {
    const payload = { level: syaratForm.value.level, kondisi: syaratForm.value.kondisi }
    payload[`${syaratForm.value.level}_id`] = syaratForm.value[`${syaratForm.value.level}_id`]

    const { data } = await api.post(`/admin/knowledge/kombinasi/${temuan.id}/syarat`, payload)
    temuanList.value = temuanList.value.map((t) => (t.id === temuan.id ? { ...t, syarat: [...t.syarat, data] } : t))
    syaratForm.value = { level: syaratForm.value.level, indikator_id: '', aspek_id: '', sindrom_id: '', kondisi: '' }
    toast.push('Syarat ditambahkan.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menambah syarat.')
  } finally {
    syaratSubmitting.value = false
  }
}

async function deleteSyarat(temuan, syarat) {
  try {
    await api.delete(`/admin/knowledge/kombinasi-syarat/${syarat.id}`)
    temuanList.value = temuanList.value.map((t) =>
      t.id === temuan.id ? { ...t, syarat: t.syarat.filter((s) => s.id !== syarat.id) } : t,
    )
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus syarat.')
  }
}

function formatSyarat(syarat) {
  const kondisiText = kondisiOptions[syarat.level]?.find((k) => k.value === syarat.kondisi)?.label ?? syarat.kondisi
  if (syarat.level === 'indikator') return `Indikator ${syarat.indikator?.kode} (${syarat.indikator?.nama}) — ${kondisiText}`
  if (syarat.level === 'aspek') return `Aspek ${syarat.aspek?.kode} (${syarat.aspek?.nama}) — ${kondisiText}`
  return `Sindrom ${syarat.sindrom?.kode_romawi} (${syarat.sindrom?.nama}) — ${kondisiText}`
}
</script>

<template>
  <div class="kombinasi-manager">
    <p class="kombinasi-manager__hint">
      Temuan dari KOMBINASI beberapa Indikator/Aspek/Sindrom sekaligus (bukan cuma 1) - hasilnya jadi 1 teks
      interpretasi baru yang ditempel ke laporan. Isi <code>teks_interpretasi</code> harus dari referensi grafolog
      profesional, bukan dikarang.
    </p>

    <form class="kombinasi-manager__form" @submit.prevent="createTemuan">
      <label>
        Nama
        <input v-model="createForm.nama" type="text" maxlength="150" placeholder="mis. Ambisi Tersembunyi" required />
      </label>
      <label>
        Logika Gabung Syarat
        <select v-model="createForm.logika_gabung">
          <option value="OR">OR (salah satu syarat cukup)</option>
          <option value="AND">AND (semua syarat harus terpenuhi)</option>
        </select>
      </label>
      <label class="kombinasi-manager__field">
        Teks Interpretasi
        <textarea v-model="createForm.teks_interpretasi" rows="3" required></textarea>
      </label>
      <p v-if="createErrors.nama" class="error">{{ createErrors.nama[0] }}</p>
      <p v-if="createErrors.teks_interpretasi" class="error">{{ createErrors.teks_interpretasi[0] }}</p>
      <button type="submit" class="btn btn--primary" :disabled="submitting">
        {{ submitting ? 'Membuat...' : 'Tambah Kombinasi Temuan' }}
      </button>
    </form>

    <p v-if="loading">Memuat...</p>
    <template v-else>
      <p v-if="temuanList.length === 0" class="kombinasi-manager__empty">Belum ada Kombinasi Temuan.</p>
      <div v-for="temuan in temuanList" :key="temuan.id" class="kombinasi-manager__item">
        <div class="kombinasi-manager__item-head" @click="expandedId === temuan.id ? cancelEdit() : startEdit(temuan)">
          <span class="kombinasi-manager__item-nama">{{ temuan.nama }}</span>
          <span class="kombinasi-manager__item-meta">{{ temuan.logika_gabung }} &middot; {{ temuan.syarat.length }} syarat</span>
          <span class="kombinasi-manager__actions" @click.stop>
            <button type="button" class="btn" @click="expandedId === temuan.id ? cancelEdit() : startEdit(temuan)">
              {{ expandedId === temuan.id ? 'Tutup' : 'Ubah' }}
            </button>
            <button type="button" class="btn btn--danger" @click="deleteTemuan(temuan)">Hapus</button>
          </span>
        </div>

        <div v-if="expandedId === temuan.id" class="kombinasi-manager__detail">
          <label>
            Nama
            <input v-model="editForm.nama" type="text" maxlength="150" />
          </label>
          <label>
            Logika Gabung Syarat
            <select v-model="editForm.logika_gabung">
              <option value="OR">OR (salah satu syarat cukup)</option>
              <option value="AND">AND (semua syarat harus terpenuhi)</option>
            </select>
          </label>
          <label class="kombinasi-manager__field">
            Teks Interpretasi
            <textarea v-model="editForm.teks_interpretasi" rows="3"></textarea>
          </label>
          <button type="button" class="btn btn--primary" @click="saveEdit(temuan)">Simpan</button>

          <h4>Syarat</h4>
          <ul v-if="temuan.syarat.length" class="kombinasi-manager__syarat-list">
            <li v-for="syarat in temuan.syarat" :key="syarat.id">
              {{ formatSyarat(syarat) }}
              <button type="button" class="kombinasi-manager__syarat-remove" @click="deleteSyarat(temuan, syarat)">✕</button>
            </li>
          </ul>
          <p v-else class="kombinasi-manager__empty">Belum ada syarat - kombinasi ini tidak akan pernah terdeteksi.</p>

          <div class="kombinasi-manager__syarat-form">
            <select v-model="syaratForm.level">
              <option value="indikator">Indikator</option>
              <option value="aspek">Aspek</option>
              <option value="sindrom">Sindrom</option>
            </select>

            <select v-if="syaratForm.level === 'indikator'" v-model="syaratForm.indikator_id">
              <option value="" disabled>Pilih Indikator</option>
              <option v-for="i in indikatorOptions" :key="i.id" :value="i.id">{{ i.kode }} — {{ i.nama }}</option>
            </select>
            <select v-else-if="syaratForm.level === 'aspek'" v-model="syaratForm.aspek_id">
              <option value="" disabled>Pilih Aspek</option>
              <option v-for="a in aspekOptions" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.nama }}</option>
            </select>
            <select v-else v-model="syaratForm.sindrom_id">
              <option value="" disabled>Pilih Sindrom</option>
              <option v-for="s in sindromOptions" :key="s.id" :value="s.id">{{ s.kode_romawi }} — {{ s.nama }}</option>
            </select>

            <select v-model="syaratForm.kondisi">
              <option value="" disabled>Kondisi</option>
              <option v-for="k in kondisiOptions[syaratForm.level]" :key="k.value" :value="k.value">{{ k.label }}</option>
            </select>

            <button type="button" class="btn" :disabled="syaratSubmitting || !syaratForm.kondisi" @click="addSyarat(temuan)">
              Tambah Syarat
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.kombinasi-manager__hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-bottom: 16px;
}
.kombinasi-manager__form {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 20px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
}
.kombinasi-manager__form label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}
.kombinasi-manager__field {
  flex-basis: 100%;
}
.kombinasi-manager__form textarea,
.kombinasi-manager__detail textarea {
  font-family: inherit;
  resize: vertical;
}
.kombinasi-manager__empty {
  color: var(--color-text-soft);
  font-size: 13px;
}
.kombinasi-manager__item {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  margin-bottom: 10px;
  overflow: hidden;
}
.kombinasi-manager__item-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 16px;
  padding: 10px 14px;
  cursor: pointer;
}
.kombinasi-manager__item-nama {
  font-weight: 600;
}
.kombinasi-manager__item-meta {
  font-size: 12px;
  color: var(--color-text-soft);
  flex: 1;
}
.kombinasi-manager__actions {
  display: flex;
  gap: 8px;
}
.kombinasi-manager__detail {
  padding: 14px;
  border-top: 1px solid var(--color-border);
  background: var(--color-background);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.kombinasi-manager__detail label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}
.kombinasi-manager__detail h4 {
  margin: 10px 0 0;
  font-size: 14px;
}
.kombinasi-manager__syarat-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.kombinasi-manager__syarat-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
  padding: 6px 10px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
}
.kombinasi-manager__syarat-remove {
  border: none;
  background: none;
  color: var(--color-seal);
  cursor: pointer;
  font-size: 13px;
}
.kombinasi-manager__syarat-form {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
</style>
