<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import ReportDocument from '@/components/report/ReportDocument.vue'

const props = defineProps({
  sampleId: { type: [String, Number], required: true },
})

const sample = ref(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get(`/samples/${props.sampleId}`)
    sample.value = data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat hasil.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="hasil-rapid">
    <LoadingSpinner v-if="loading" label="Memuat hasil..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else>
      <div class="hasil-rapid__disclaimer">
        Ini adalah hasil <strong>Rapid</strong> dengan skor placeholder (belum berbasis analisis
        gambar tulisan tangan sungguhan). Untuk hasil yang valid dan mendalam, gunakan sesi
        Comprehensive/Master bersama grafolog kami.
      </div>

      <div v-if="sample.reports?.[0]">
        <ReportDocument :data="sample.reports[0].data" />
        <RouterLink :to="`/reports/${sample.reports[0].id}`" class="btn btn--primary">
          Lihat Laporan Lengkap &amp; Unduh PDF
        </RouterLink>
      </div>
      <p v-else>Laporan belum tersedia.</p>
    </template>
  </div>
</template>

<style scoped>
.hasil-rapid {
  max-width: 100%;
  overflow-x: hidden;
}
.hasil-rapid__disclaimer {
  background: var(--color-seal-soft);
  border: 1px solid var(--color-seal);
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--color-seal-dark);
  margin-bottom: 20px;
}
.btn {
  display: inline-block;
  margin-top: 16px;
}
</style>
