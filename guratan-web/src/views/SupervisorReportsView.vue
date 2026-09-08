<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const reports = ref([])
const meta = ref({ currentPage: 1, lastPage: 1, total: 0 })
const loading = ref(false)
const loadError = ref('')

const tierFilter = ref('')
const statusFilter = ref('')
const searchFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')
let searchTimer = null

const statusLabel = {
  generating: 'Diproses',
  completed: 'Selesai',
  failed: 'Gagal',
}

function currentFilters() {
  return {
    tier: tierFilter.value || undefined,
    status: statusFilter.value || undefined,
    search: searchFilter.value || undefined,
    from: fromFilter.value || undefined,
    to: toFilter.value || undefined,
  }
}

async function load(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/reports', { params: { ...currentFilters(), page } })
    reports.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page, total: data.total }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat laporan perusahaan.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

function onFilterChange() {
  load(1)
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

function goToPage(page) {
  if (page < 1 || page > meta.value.lastPage) return
  load(page)
}

function formatDate(iso) {
  if (!iso) return '-'
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => load(1))
</script>

<template>
  <div class="supervisor-reports">
    <h1>Laporan Perusahaan</h1>
    <p class="supervisor-reports__note">
      Seluruh laporan kandidat yang diimpor oleh akun HR di perusahaan Anda - insight reflektif untuk membantu
      keputusan SDM, bukan alat diagnosis final.
    </p>

    <div class="supervisor-reports__filters">
      <label>
        Tier
        <select v-model="tierFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option value="comprehensive">Comprehensive</option>
          <option value="master">Master</option>
        </select>
      </label>
      <label>
        Status
        <select v-model="statusFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option value="generating">Diproses</option>
          <option value="completed">Selesai</option>
          <option value="failed">Gagal</option>
        </select>
      </label>
      <label>
        Cari nama kandidat
        <input v-model="searchFilter" type="text" placeholder="mis. Budi" @input="onSearchInput" />
      </label>
      <label>
        Dari
        <input v-model="fromFilter" type="date" @change="onFilterChange" />
      </label>
      <label>
        Sampai
        <input v-model="toFilter" type="date" @change="onFilterChange" />
      </label>
      <span class="supervisor-reports__result-count">{{ meta.total }} laporan</span>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="reports.length === 0" class="supervisor-reports__empty">Belum ada laporan yang cocok.</p>
    <table v-else class="supervisor-reports__table">
      <thead>
        <tr>
          <th>Kandidat</th>
          <th>Tier</th>
          <th>Status</th>
          <th>Dibuat</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="report in reports" :key="report.id">
          <td>{{ report.sample?.user?.name ?? '-' }}</td>
          <td>{{ report.tier }}</td>
          <td>{{ statusLabel[report.status] ?? report.status }}</td>
          <td>{{ formatDate(report.generated_at ?? report.created_at) }}</td>
          <td>
            <RouterLink :to="`/reports/${report.id}`">Lihat</RouterLink>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="meta.lastPage > 1" class="supervisor-reports__pagination">
      <button type="button" class="btn" :disabled="meta.currentPage <= 1" @click="goToPage(meta.currentPage - 1)">
        &larr; Sebelumnya
      </button>
      <span>Halaman {{ meta.currentPage }} / {{ meta.lastPage }}</span>
      <button
        type="button"
        class="btn"
        :disabled="meta.currentPage >= meta.lastPage"
        @click="goToPage(meta.currentPage + 1)"
      >
        Berikutnya &rarr;
      </button>
    </div>
  </div>
</template>

<style scoped>
.supervisor-reports {
  max-width: 1000px;
}
.supervisor-reports__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.supervisor-reports__filters {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  gap: 14px;
  margin-bottom: 20px;
  padding: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.supervisor-reports__filters label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
}
.supervisor-reports__result-count {
  margin-left: auto;
  font-size: 12.5px;
  color: var(--color-text-soft);
  align-self: center;
}
.supervisor-reports__empty {
  color: var(--color-text-soft);
}
.supervisor-reports__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 13px;
}
.supervisor-reports__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.supervisor-reports__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
.supervisor-reports__table a {
  color: var(--color-seal);
  text-decoration: none;
  font-weight: 500;
}
.supervisor-reports__table a:hover {
  text-decoration: underline;
}
.supervisor-reports__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  font-size: 13px;
}
</style>
