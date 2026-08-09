<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/lib/api'
import ReportDocument from '@/components/report/ReportDocument.vue'
import ReportCorrectionPanel from '@/components/report/ReportCorrectionPanel.vue'
import ReportRevisionHistory from '@/components/report/ReportRevisionHistory.vue'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'

const toast = useToast()
const auth = useAuthStore()

const props = defineProps({
  id: { type: [String, Number], required: true },
})

const report = ref(null)
const loading = ref(true)
const error = ref('')
const downloading = ref(false)

// Grafolog boleh koreksi/edit laporan sample yang dia tangani (pemilik atau
// ditugaskan) - pengecekan sungguhan tetap di backend (isScorableBy), ini
// cuma sinyal UI supaya tombolnya tidak muncul ke grafolog lain yang pasti
// akan ditolak 403 kalau mencoba.
const canEdit = computed(() => auth.isGrafolog && report.value)

async function load() {
  try {
    const { data } = await api.get(`/reports/${props.id}`)
    report.value = data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat laporan.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function downloadPdf() {
  downloading.value = true
  try {
    const response = await api.get(`/reports/${props.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `laporan-${props.id}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.push('Gagal mengunduh PDF.')
  } finally {
    downloading.value = false
  }
}

function onCorrected(updatedReport) {
  report.value = { ...report.value, ...updatedReport }
}

function onNarasiUpdated(updatedReport) {
  report.value = { ...report.value, data: updatedReport.data }
}
</script>

<template>
  <div class="report-view">
    <LoadingSpinner v-if="loading" label="Memuat laporan..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else>
      <div class="report-view__header">
        <h1>Laporan #{{ report.id }} ({{ report.tier }})</h1>
        <button type="button" class="btn" :disabled="downloading" @click="downloadPdf">
          {{ downloading ? 'Menyiapkan PDF...' : 'Unduh PDF' }}
        </button>
      </div>

      <ReportCorrectionPanel
        v-if="canEdit && report.aspek_scores"
        :sample-id="report.sample_id"
        :aspek-scores="report.aspek_scores"
        @corrected="onCorrected"
      />

      <ReportDocument
        v-if="report.data"
        :data="report.data"
        :editable="canEdit"
        :report-id="report.id"
        @narasi-updated="onNarasiUpdated"
      />
      <p v-else>Laporan masih diproses.</p>

      <ReportRevisionHistory :report-id="report.id" />
    </template>
  </div>
</template>

<style scoped>
.report-view {
  max-width: 100%;
}
.report-view__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px 16px;
  margin-bottom: 20px;
}
.report-view__header h1 {
  font-size: 22px;
}
</style>
