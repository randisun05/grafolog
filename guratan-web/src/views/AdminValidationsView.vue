<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatTanggal, STATUS_VALIDASI } from '@/lib/format'

/** Indeks penugasan supervisi antar-grafolog. Admin hanya memantau - tidak menerima token. */
const rows = ref([])
const status = ref('')
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/validations', {
      params: status.value ? { status: status.value } : {},
    })
    rows.value = data.data
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="av">
    <h1>Penugasan Supervisi</h1>
    <p class="av__hint">
      Grafolog menugaskan grafolog lain untuk memvalidasi laporannya. Halaman ini hanya indeks
      penugasan; token dipindahkan antar-grafolog, bukan ke admin.
    </p>
    <select v-model="status" aria-label="Filter status" @change="load">
      <option value="">Semua status</option>
      <option v-for="(label, key) in STATUS_VALIDASI" :key="key" :value="key">{{ label }}</option>
    </select>
    <SkeletonLoader v-if="loading" variant="table" :columns="6" label="Memuat..." />
    <p v-else-if="!rows.length" class="av__hint">Belum ada penugasan.</p>
    <div v-else class="table-scroll">
      <table class="av__table">
        <thead>
          <tr>
            <th>Laporan</th>
            <th>Pembuat</th>
            <th>Validator</th>
            <th>Token</th>
            <th>Tenggat</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in rows" :key="v.id">
            <td>
              <RouterLink :to="`/reports/${v.report_id}`">#{{ v.report_id }}</RouterLink>
            </td>
            <td>{{ v.requester.name }}</td>
            <td>{{ v.validator.name }}</td>
            <td>{{ v.fee }}</td>
            <td>{{ formatTanggal(v.due_at) }}</td>
            <td>{{ STATUS_VALIDASI[v.status] }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.av {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.av select {
  width: auto;
  align-self: flex-start;
}
.av__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.av__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.av__table th,
.av__table td {
  text-align: left;
  padding: 8px 10px;
  border-bottom: 1px solid var(--color-border);
}
.av__table th {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
</style>
