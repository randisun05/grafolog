<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatTanggal } from '@/lib/format'

/** Log aktivitas ringan (metadata permintaan + login/logout). Retensi diatur di config/activity.php. */
const filters = ref({ q: '', event: '', from: '', to: '' })
const rows = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v))
    const { data } = await api.get('/admin/activity-logs', {
      params: { ...params, page: page.value },
    })
    rows.value = data.data
    lastPage.value = data.last_page
  } finally {
    loading.value = false
  }
}
function search() {
  page.value = 1
  load()
}
function go(d) {
  page.value += d
  load()
}
onMounted(load)
</script>

<template>
  <div class="al">
    <h1>Log Aktivitas</h1>
    <p class="al__hint">
      Hanya metadata (siapa, kapan, endpoint, status) — tanpa isi permintaan. Disimpan terbatas
      sesuai kebijakan retensi.
    </p>
    <form class="al__filters" @submit.prevent="search">
      <input v-model="filters.q" placeholder="Cari nama / email / path" aria-label="Cari" />
      <select v-model="filters.event" aria-label="Jenis">
        <option value="">Semua jenis</option>
        <option value="request">request</option>
        <option value="login">login</option>
        <option value="login_gagal">login_gagal</option>
        <option value="logout">logout</option>
      </select>
      <input v-model="filters.from" type="date" aria-label="Dari tanggal" />
      <input v-model="filters.to" type="date" aria-label="Sampai tanggal" />
      <button class="btn">Cari</button>
    </form>
    <SkeletonLoader v-if="loading" variant="table" :columns="6" label="Memuat..." />
    <p v-else-if="!rows.length" class="al__hint">Tidak ada data.</p>
    <div v-else class="al__scroll">
      <table class="al__table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Pengguna</th>
            <th>Jenis</th>
            <th>Aksi</th>
            <th>Status</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id">
            <td>{{ formatTanggal(r.created_at, true) }}</td>
            <td>{{ r.user ? `${r.user.name} (${r.user.role})` : '—' }}</td>
            <td>{{ r.event }}</td>
            <td>
              <code>{{ r.method }} {{ r.path }}</code>
            </td>
            <td>{{ r.status }}</td>
            <td>{{ r.ip }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="lastPage > 1" class="al__pager">
      <button type="button" class="btn" :disabled="page <= 1" @click="go(-1)">‹</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button type="button" class="btn" :disabled="page >= lastPage" @click="go(1)">›</button>
    </div>
  </div>
</template>

<style scoped>
.al {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.al__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.al__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.al__filters input,
.al__filters select {
  width: auto;
}
.al__scroll {
  overflow-x: auto;
}
.al__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.al__table th,
.al__table td {
  text-align: left;
  padding: 6px 10px;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}
.al__pager {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
}
</style>
