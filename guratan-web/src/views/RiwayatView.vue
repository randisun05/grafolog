<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const reports = ref([])
const loading = ref(true)
const error = ref('')

const statusLabel = {
  generating: 'Diproses',
  completed: 'Selesai',
  failed: 'Gagal',
}

// Klien cuma pernah bisa membuka laporan begitu narasi_status='final' (lihat
// ReportController::show) - status breakdown internal ('completed') di sini
// cuma berarti perhitungan Sindrom/Aspek/Indikator sudah selesai, BUKAN
// berarti narasi yang mereka lihat sudah final. Kalau daftar ini memakai
// `status` biasa untuk klien, mereka bisa lihat badge "Selesai" lalu begitu
// diklik malah dapat error "laporan belum final" - status yang ditampilkan
// harus mencerminkan apa yang SUNGGUH bisa mereka akses.
function displayStatus(report) {
  if (!auth.isClient) return report.status
  return report.narasi_status === 'final' ? 'completed' : 'generating'
}

onMounted(async () => {
  try {
    const { data } = await api.get('/reports')
    reports.value = data.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat riwayat.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="riwayat">
    <h1>Riwayat Laporan</h1>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="reports.length === 0">Belum ada laporan.</p>
    <ul v-else class="riwayat__list">
      <li v-for="report in reports" :key="report.id" class="riwayat__item">
        <RouterLink :to="`/reports/${report.id}`">
          Laporan #{{ report.id }} - {{ report.tier }}
        </RouterLink>
        <span class="riwayat__status">{{ statusLabel[displayStatus(report)] ?? displayStatus(report) }}</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.riwayat__list {
  list-style: none;
  padding: 0;
}
.riwayat__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 4px 12px;
  padding: 14px 4px;
  border-bottom: 1px solid var(--color-border);
}
.riwayat__item a {
  text-decoration: none;
  color: var(--color-ink);
  font-weight: 500;
}
.riwayat__item a:hover {
  color: var(--color-seal);
}
.riwayat__status {
  color: var(--color-text-soft);
  font-size: 13px;
  white-space: nowrap;
}
</style>
