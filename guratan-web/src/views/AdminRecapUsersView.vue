<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import { downloadBlob } from '@/lib/downloadBlob'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const users = ref([])
const companies = ref([])
const meta = ref({ currentPage: 1, lastPage: 1, total: 0 })
const loading = ref(false)
const loadError = ref('')
const exporting = ref(false)

const roleFilter = ref('')
const isActiveFilter = ref('')
const companyFilter = ref('')
const searchFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')
let searchTimer = null

function currentFilters() {
  return {
    role: roleFilter.value || undefined,
    is_active: isActiveFilter.value === '' ? undefined : isActiveFilter.value,
    company_id: companyFilter.value || undefined,
    search: searchFilter.value || undefined,
    from: fromFilter.value || undefined,
    to: toFilter.value || undefined,
  }
}

async function load(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/recap/users', { params: { ...currentFilters(), page } })
    users.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page, total: data.total }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat rekap pengguna.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

async function loadCompanies() {
  try {
    const { data } = await api.get('/admin/companies')
    companies.value = data.data
  } catch {
    // Dropdown filter perusahaan tetap kosong kalau gagal - tidak fatal.
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

async function exportCsv() {
  exporting.value = true
  try {
    const response = await api.get('/admin/recap/users/export', {
      params: currentFilters(),
      responseType: 'blob',
    })
    downloadBlob(response.data, `rekap-pengguna-${new Date().toISOString().slice(0, 10)}.csv`)
  } catch {
    toast.push('Gagal mengekspor CSV.')
  } finally {
    exporting.value = false
  }
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => {
  load(1)
  loadCompanies()
})
</script>

<template>
  <div class="admin-recap">
    <h1>Rekap Pengguna</h1>
    <p class="admin-recap__note">
      Daftar seluruh akun (klien, grafolog, HR, staf) lintas-role, bisa difilter dan diekspor ke CSV.
    </p>

    <div class="admin-recap__filters">
      <label>
        Role
        <select v-model="roleFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option value="user">Klien</option>
          <option value="grafolog">Grafolog</option>
          <option value="hr">HR</option>
          <option value="administrator">Administrator</option>
          <option value="supervisor">Supervisor</option>
        </select>
      </label>
      <label>
        Status
        <select v-model="isActiveFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option value="1">Aktif</option>
          <option value="0">Nonaktif</option>
        </select>
      </label>
      <label>
        Perusahaan
        <select v-model="companyFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label>
        Cari nama/email
        <input v-model="searchFilter" type="text" placeholder="mis. budi@" @input="onSearchInput" />
      </label>
      <label>
        Terdaftar dari
        <input v-model="fromFilter" type="date" @change="onFilterChange" />
      </label>
      <label>
        Sampai
        <input v-model="toFilter" type="date" @change="onFilterChange" />
      </label>
      <span class="admin-recap__result-count">{{ meta.total }} pengguna</span>
      <button type="button" class="btn" :disabled="exporting" @click="exportCsv">
        {{ exporting ? 'Mengekspor...' : 'Export CSV' }}
      </button>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="users.length === 0" class="admin-recap__empty">Tidak ada pengguna yang cocok.</p>
    <table v-else class="admin-recap__table">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Role</th>
          <th>Perusahaan</th>
          <th>Status</th>
          <th>Terdaftar</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td>{{ user.name }}</td>
          <td>{{ user.email }}</td>
          <td>{{ user.role }}</td>
          <td>{{ user.company?.name ?? '-' }}</td>
          <td>{{ user.is_active ? 'Aktif' : 'Nonaktif' }}</td>
          <td>{{ formatDate(user.created_at) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="meta.lastPage > 1" class="admin-recap__pagination">
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
.admin-recap {
  max-width: 1000px;
}
.admin-recap__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-recap__filters {
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
.admin-recap__filters label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
}
.admin-recap__result-count {
  margin-left: auto;
  font-size: 12.5px;
  color: var(--color-text-soft);
  align-self: center;
}
.admin-recap__empty {
  color: var(--color-text-soft);
}
.admin-recap__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-recap__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-recap__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-recap__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  font-size: 13px;
}
</style>
