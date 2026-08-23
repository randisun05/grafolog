<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/lib/api'
import ReportDocument from '@/components/report/ReportDocument.vue'
import ReportCorrectionPanel from '@/components/report/ReportCorrectionPanel.vue'
import ReportRevisionHistory from '@/components/report/ReportRevisionHistory.vue'
import NarasiTerpaduPanel from '@/components/report/NarasiTerpaduPanel.vue'
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

// Laporan tersegmentasi per-Topik (B2B Fase 2, lihat guratan-api/CLAUDE.md
// "B2B Fase 2") - HR bisa lihat breakdown difilter ke Topik tertentu (mis.
// "Karier" saja) alih-alih breakdown penuh. Murni tampilan baca - tidak ada
// PATCH/edit di sini (ReportDocument dipasang editable=false), dan tidak
// mengubah data laporan yang tersimpan sama sekali.
const topikOptions = ref([])
const selectedTopikIds = ref([])
const segmentedData = ref(null)
const segmenLoading = ref(false)

async function loadTopikOptions() {
  try {
    const { data } = await api.get('/topik')
    topikOptions.value = data
  } catch {
    // Gagal muat daftar Topik bukan fatal - filter cuma tidak muncul,
    // breakdown penuh tetap tampil seperti biasa.
  }
}

watch(selectedTopikIds, async (ids) => {
  if (ids.length === 0) {
    segmentedData.value = null
    return
  }
  segmenLoading.value = true
  try {
    const params = new URLSearchParams()
    ids.forEach((id) => params.append('topik_ids[]', id))
    const { data } = await api.get(`/reports/${props.id}/segmen?${params.toString()}`)
    segmentedData.value = data
  } catch {
    toast.push('Gagal memuat laporan tersegmentasi.')
  } finally {
    segmenLoading.value = false
  }
})

onMounted(() => {
  if (auth.isHr) loadTopikOptions()
})

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

function onNarasiTerpaduUpdated(updatedReport) {
  report.value = {
    ...report.value,
    narasi_terpadu: updatedReport.narasi_terpadu,
    narasi_bahasa: updatedReport.narasi_bahasa,
    narasi_status: updatedReport.narasi_status,
    narasi_generation_error: updatedReport.narasi_generation_error,
  }
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

      <!-- Klien (subjek tes) HANYA melihat narasi terpadu - breakdown
           Sindrom/Aspek/Indikator tidak pernah dikirim ke akun ini sama
           sekali (backend tidak menyertakannya di respons), lihat
           ReportController::isClientViewer. -->
      <template v-if="auth.isClient">
        <div v-if="report.narasi_terpadu" class="report-view__narasi-klien">{{ report.narasi_terpadu }}</div>
        <p v-else>Laporan Anda belum tersedia.</p>
      </template>

      <template v-else>
        <NarasiTerpaduPanel
          v-if="canEdit"
          :report-id="report.id"
          :narasi-terpadu="report.narasi_terpadu"
          :narasi-bahasa="report.narasi_bahasa"
          :narasi-status="report.narasi_status"
          :narasi-generation-error="report.narasi_generation_error"
          @updated="onNarasiTerpaduUpdated"
        />

        <h2 class="report-view__internal-heading">Data Pengukuran (Internal)</h2>
        <p class="report-view__internal-hint">
          Rincian per-Sindrom/Aspek/Indikator ini bahan kerja/verifikasi grafolog, bukan yang dikirim ke klien.
        </p>

        <ReportCorrectionPanel
          v-if="canEdit && report.aspek_scores"
          :sample-id="report.sample_id"
          :aspek-scores="report.aspek_scores"
          @corrected="onCorrected"
        />

        <div v-if="auth.isHr && topikOptions.length > 0" class="report-view__segmen">
          <h3>Filter Segmen Topik</h3>
          <p class="report-view__segmen-hint">
            Pilih satu atau lebih topik untuk melihat laporan yang difilter (mis. cuma bagian Karier) —
            kosongkan pilihan untuk kembali ke breakdown penuh. Filter ini murni tampilan, tidak mengubah
            laporan yang tersimpan.
          </p>
          <div class="report-view__segmen-options">
            <label v-for="t in topikOptions" :key="t.id">
              <input v-model="selectedTopikIds" type="checkbox" :value="t.id" /> {{ t.nama }}
            </label>
          </div>
          <p v-if="segmenLoading" class="report-view__segmen-hint">Memuat...</p>
        </div>

        <ReportDocument
          v-if="report.data"
          :data="segmentedData ?? report.data"
          :editable="canEdit && !segmentedData"
          :report-id="report.id"
          @narasi-updated="onNarasiUpdated"
        />
        <p v-else>Laporan masih diproses.</p>

        <ReportRevisionHistory :report-id="report.id" />
      </template>
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
.report-view__narasi-klien {
  white-space: pre-line;
  line-height: 1.7;
  font-size: 15px;
  text-align: justify;
}
.report-view__internal-heading {
  margin-top: 32px;
  font-size: 17px;
}
.report-view__internal-hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin: 4px 0 16px;
}
.report-view__segmen {
  margin: 20px 0;
  padding: 14px 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.report-view__segmen h3 {
  font-size: 14px;
  margin-bottom: 4px;
}
.report-view__segmen-hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin: 0 0 10px;
}
.report-view__segmen-options {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 16px;
  font-size: 13px;
}
.report-view__segmen-options label {
  display: flex;
  align-items: center;
  gap: 5px;
}
.report-view__segmen-options input {
  width: auto;
  margin: 0;
}
</style>
