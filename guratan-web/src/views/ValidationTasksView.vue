<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatTanggal, STATUS_VALIDASI } from '@/lib/format'

/** Tugas supervisi saya (sebagai validator) + penugasan yang saya buat. */
const assigned = ref([])
const requested = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/validations')
    assigned.value = data.assigned
    requested.value = data.requested
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat tugas supervisi.'
  } finally {
    loading.value = false
  }
})

const isOverdue = (v) => v.status === 'menunggu' && new Date(v.due_at) < new Date()
</script>

<template>
  <div class="vt">
    <h1>Supervisi Laporan</h1>
    <p class="vt__hint">
      Grafolog saling memvalidasi laporan. Token dibayar pembuat laporan dan cair ke Anda setelah
      pembuat mengonfirmasi hasil. Atur bulan Anda bersedia disupervisi di
      <RouterLink to="/profil">Profil → Ketersediaan</RouterLink>.
    </p>
    <SkeletonLoader v-if="loading" variant="list" :rows="3" label="Memuat tugas..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else>
      <h2>Ditugaskan ke saya</h2>
      <p v-if="!assigned.length" class="vt__hint">Belum ada penugasan.</p>
      <ul class="vt__list">
        <li v-for="v in assigned" :key="v.id" :class="{ 'vt__item--late': isOverdue(v) }">
          <div>
            <strong>Laporan #{{ v.report_id }} · {{ v.report?.sample?.user?.name }}</strong>
            <span class="vt__meta"
              >Dari {{ v.requester.name }} · tenggat {{ formatTanggal(v.due_at, true) }} · token
              {{ v.fee }} · {{ isOverdue(v) ? 'LEWAT TENGGAT' : STATUS_VALIDASI[v.status] }}</span
            >
          </div>
          <RouterLink
            :to="`/reports/${v.report_id}`"
            class="btn"
            :class="{ 'btn--primary': v.status === 'menunggu' }"
          >
            {{ v.status === 'menunggu' ? 'Tinjau' : 'Lihat' }}
          </RouterLink>
        </li>
      </ul>

      <h2>Penugasan yang saya buat</h2>
      <p v-if="!requested.length" class="vt__hint">Belum ada.</p>
      <ul class="vt__list">
        <li v-for="v in requested" :key="v.id">
          <div>
            <strong>Laporan #{{ v.report_id }} → {{ v.validator.name }}</strong>
            <span class="vt__meta"
              >Tenggat {{ formatTanggal(v.due_at, true) }} · token {{ v.fee }} ·
              {{ STATUS_VALIDASI[v.status] }}</span
            >
          </div>
          <RouterLink
            :to="`/reports/${v.report_id}`"
            class="btn"
            :class="{ 'btn--primary': ['disetujui', 'perlu_revisi'].includes(v.status) }"
          >
            {{ ['disetujui', 'perlu_revisi'].includes(v.status) ? 'Konfirmasi' : 'Buka' }}
          </RouterLink>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.vt {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.vt h2 {
  font-size: 17px;
  margin: 10px 0 0;
}
.vt__hint,
.vt__meta {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.vt__meta {
  display: block;
}
.vt__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.vt__list li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.vt__item--late {
  border-color: var(--color-error) !important;
}
</style>
