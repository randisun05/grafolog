<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const samples = ref([])
const loading = ref(true)
const loadError = ref('')

const grafologs = ref([])
const assigning = ref({}) // { [sampleId]: boolean }
const selectedGrafolog = ref({}) // { [sampleId]: grafologId }

const file = ref(null)
const projectName = ref('')
const tier = ref('')
const importing = ref(false)
const importErrors = ref([])

const products = ref([])

const statusLabel = {
  pending: 'Menunggu Skor',
  scored: 'Sedang Diproses',
  completed: 'Selesai',
}

async function loadSamples() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/samples')
    samples.value = data.data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat daftar kandidat.'
  } finally {
    loading.value = false
  }
}

async function loadGrafologs() {
  try {
    const { data } = await api.get('/grafologs')
    grafologs.value = data
  } catch {
    // Daftar grafolog gagal dimuat - form assign akan kosong, tidak fatal.
  }
}

async function loadProducts() {
  try {
    const { data } = await api.get('/products')
    products.value = data
    if (!tier.value && data.length) tier.value = data[0].code
  } catch {
    // Daftar produk gagal dimuat - dropdown tier akan kosong, tidak fatal.
  }
}

async function assignGrafolog(sample) {
  const grafologId = selectedGrafolog.value[sample.id]
  if (!grafologId) return
  assigning.value = { ...assigning.value, [sample.id]: true }
  try {
    await api.post(`/samples/${sample.id}/assignment`, { grafolog_id: grafologId })
    toast.push('Grafolog berhasil ditugaskan.', 'success')
    await loadSamples()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menugaskan grafolog.')
  } finally {
    assigning.value = { ...assigning.value, [sample.id]: false }
  }
}

function onFileChange(event) {
  file.value = event.target.files[0] ?? null
}

async function submitImport() {
  if (!file.value) return
  importing.value = true
  importErrors.value = []
  try {
    const formData = new FormData()
    formData.append('file', file.value)
    if (projectName.value) formData.append('project_name', projectName.value)
    formData.append('tier', tier.value)

    const { data } = await api.post('/hr/candidates/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    toast.push(`${data.samples.length} kandidat berhasil diimpor ke project "${data.project.name}".`, 'success')
    file.value = null
    projectName.value = ''
    await loadSamples()
  } catch (e) {
    if (e.response?.status === 422 && e.response.data.errors) {
      importErrors.value = e.response.data.errors
    }
    toast.push(e.response?.data?.message ?? 'Gagal mengimpor kandidat.')
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  loadSamples()
  loadGrafologs()
  loadProducts()
})
</script>

<template>
  <div class="hr-candidates">
    <h1>Kandidat</h1>
    <p class="hr-candidates__note">
      Impor daftar kandidat dari CSV (kolom wajib: <code>name</code>, <code>email</code>). Setiap
      kandidat otomatis dibuatkan sample - tugaskan grafolog langsung dari tabel di bawah.
    </p>

    <form class="hr-candidates__form" @submit.prevent="submitImport">
      <label>
        File CSV
        <input type="file" accept=".csv,text/csv" required @change="onFileChange" />
      </label>
      <label>
        Nama Project (opsional)
        <input v-model="projectName" type="text" placeholder="mis. Rekrutmen Finance Q3 2026" />
      </label>
      <label>
        Tier
        <select v-model="tier">
          <option v-for="product in products" :key="product.code" :value="product.code">{{ product.name }}</option>
        </select>
      </label>

      <ul v-if="importErrors.length" class="hr-candidates__errors">
        <li v-for="err in importErrors" :key="err.line">Baris {{ err.line }}: {{ err.errors.join(', ') }}</li>
      </ul>

      <button type="submit" class="btn btn--primary" :disabled="!file || importing">
        {{ importing ? 'Mengimpor...' : 'Impor Kandidat' }}
      </button>
    </form>

    <h2 class="hr-candidates__list-title">Semua Kandidat</h2>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="samples.length === 0" class="hr-candidates__empty">Belum ada kandidat diimpor.</p>
    <table v-else class="hr-candidates__table">
      <thead>
        <tr>
          <th>Kandidat</th>
          <th>Tier</th>
          <th>Status</th>
          <th>Grafolog</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in samples" :key="s.id">
          <td>
            <span class="hr-candidates__name">{{ s.user?.name }}</span>
            <span class="hr-candidates__email">{{ s.user?.email }}</span>
          </td>
          <td>{{ s.tier }}</td>
          <td>{{ statusLabel[s.status] ?? s.status }}</td>
          <td>
            <span v-if="s.assignment?.grafolog">{{ s.assignment.grafolog.name }}</span>
            <div v-else-if="s.status !== 'completed'" class="hr-candidates__assign">
              <select v-model="selectedGrafolog[s.id]">
                <option :value="undefined" disabled>Pilih grafolog</option>
                <option v-for="g in grafologs" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
              <button
                type="button"
                class="btn"
                :disabled="!selectedGrafolog[s.id] || assigning[s.id]"
                @click="assignGrafolog(s)"
              >
                {{ assigning[s.id] ? '...' : 'Tugaskan' }}
              </button>
            </div>
            <span v-else>–</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.hr-candidates {
  max-width: 760px;
}
.hr-candidates__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.hr-candidates__form {
  padding: 20px;
  margin-bottom: 32px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.hr-candidates__form label {
  display: block;
  margin-bottom: 14px;
  font-size: 14px;
  color: var(--color-text-soft);
}
.hr-candidates__errors {
  color: var(--color-error);
  font-size: 12.5px;
  margin: -6px 0 14px;
  padding-left: 18px;
}
.hr-candidates__list-title {
  font-size: 16px;
  margin-bottom: 10px;
}
.hr-candidates__empty {
  color: var(--color-text-soft);
}
.hr-candidates__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 14px;
}
.hr-candidates__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12.5px;
  border-bottom: 1px solid var(--color-border);
}
.hr-candidates__table td {
  padding: 10px;
  border-bottom: 1px solid var(--color-border);
}
.hr-candidates__name {
  display: block;
  font-weight: 500;
}
.hr-candidates__email {
  display: block;
  font-size: 12px;
  color: var(--color-text-soft);
}
.hr-candidates__assign {
  display: flex;
  gap: 6px;
  align-items: center;
}
.hr-candidates__assign select {
  margin-top: 0;
  width: auto;
  font-size: 13px;
}
</style>
