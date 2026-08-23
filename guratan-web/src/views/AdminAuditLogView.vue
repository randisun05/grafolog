<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const logs = ref([])
const meta = ref({ currentPage: 1, lastPage: 1, total: 0 })
const loading = ref(false)
const loadError = ref('')

const aksiFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')
let searchTimer = null

async function load(page = meta.value.currentPage) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/audit-logs', {
      params: {
        aksi: aksiFilter.value || undefined,
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
        page,
      },
    })
    logs.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page, total: data.total }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat log audit.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

function onDateChange() {
  load(1)
}

function goToPage(page) {
  if (page < 1 || page > meta.value.lastPage) return
  load(page)
}

function formatDate(iso) {
  return new Date(iso).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => load(1))
</script>

<template>
  <div class="admin-audit-log">
    <h1>Log Audit</h1>
    <p class="admin-audit-log__note">
      Riwayat perubahan sensitif di seluruh sistem (harga, diskon, konten, token, knowledge base,
      koreksi skor, akses laporan, akun staf, dst) — hanya baca, tidak bisa diedit atau dihapus.
    </p>

    <div class="admin-audit-log__filters">
      <label>
        Cari aksi
        <input v-model="aksiFilter" type="text" placeholder="mis. ubah_harga" @input="onSearchInput" />
      </label>
      <label>
        Dari tanggal
        <input v-model="fromFilter" type="date" @change="onDateChange" />
      </label>
      <label>
        Sampai tanggal
        <input v-model="toFilter" type="date" @change="onDateChange" />
      </label>
      <span class="admin-audit-log__result-count">{{ meta.total }} entri</span>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="logs.length === 0" class="admin-audit-log__empty">Tidak ada log yang cocok.</p>
    <table v-else class="admin-audit-log__table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Aksi</th>
          <th>Target</th>
          <th>Pelaku</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="log in logs" :key="log.id">
          <td class="admin-audit-log__time">{{ formatDate(log.created_at) }}</td>
          <td><code class="admin-audit-log__aksi">{{ log.aksi }}</code></td>
          <td class="admin-audit-log__target">{{ log.target_type.split('\\').pop() }} #{{ log.target_id ?? '-' }}</td>
          <td>{{ log.actor ? `${log.actor.name} (${log.actor.email})` : 'Sistem' }}</td>
          <td class="admin-audit-log__ip">{{ log.ip_address ?? '-' }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="meta.lastPage > 1" class="admin-audit-log__pagination">
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
.admin-audit-log {
  max-width: 1000px;
}
.admin-audit-log__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-audit-log__filters {
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
.admin-audit-log__filters label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
}
.admin-audit-log__result-count {
  margin-left: auto;
  font-size: 12.5px;
  color: var(--color-text-soft);
  align-self: center;
}
.admin-audit-log__empty {
  color: var(--color-text-soft);
}
.admin-audit-log__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-audit-log__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-audit-log__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-audit-log__time {
  white-space: nowrap;
  color: var(--color-text-soft);
}
.admin-audit-log__aksi {
  font-size: 12px;
  padding: 2px 6px;
  background: var(--color-paper-alt);
  border-radius: var(--radius-sm);
}
.admin-audit-log__target {
  color: var(--color-text-soft);
}
.admin-audit-log__ip {
  font-family: monospace;
  font-size: 12px;
  color: var(--color-text-soft);
}
.admin-audit-log__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  font-size: 13px;
}
</style>
