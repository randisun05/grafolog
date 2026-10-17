<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/lib/api'
import ReportDocument from '@/components/report/ReportDocument.vue'
import ReportCorrectionPanel from '@/components/report/ReportCorrectionPanel.vue'
import ReportRevisionHistory from '@/components/report/ReportRevisionHistory.vue'
import ReportReviewPanel from '@/components/report/ReportReviewPanel.vue'
import ConsultationPanel from '@/components/report/ConsultationPanel.vue'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
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
const downloading = ref('')
const bahasa = ref('id')
const BAHASA = [
  { value: 'id', label: 'Bahasa Indonesia' },
  { value: 'en', label: 'English' },
]

// Grafolog boleh koreksi/edit laporan sample yang dia tangani (pemilik atau
// ditugaskan) - pengecekan sungguhan tetap di backend (isScorableBy), ini
// cuma sinyal UI supaya tombolnya tidak muncul ke grafolog lain yang pasti
// akan ditolak 403 kalau mencoba.
const canEdit = computed(() => auth.isGrafolog && report.value)
const canReview = computed(() => auth.isSupervisor || auth.isAdministrator)
const isStaff = computed(() => canEdit.value || canReview.value)

function onReviewed(updated) {
  report.value = { ...report.value, ...updated }
}

function onConsultationSaved(consultation) {
  report.value = { ...report.value, sample: { ...report.value.sample, consultation } }
}

async function load() {
  try {
    const { data } = await api.get(`/reports/${props.id}`, { params: { lang: bahasa.value } })
    report.value = data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat laporan.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function switchBahasa(value) {
  if (value === bahasa.value) return
  bahasa.value = value
  await load()
}

async function downloadPdf(lang) {
  downloading.value = lang
  try {
    const response = await api.get(`/reports/${props.id}/pdf`, {
      params: { lang },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download =
      lang === 'en'
        ? `graphology-report-${props.id}-en.pdf`
        : `laporan-grafologi-${props.id}-id.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.push('Gagal mengunduh PDF.')
  } finally {
    downloading.value = ''
  }
}

// Respons koreksi berisi data mentah (bukan per bahasa) - muat ulang dalam bahasa aktif.
async function onCorrected() {
  await load()
}

function onNarasiUpdated(updatedReport) {
  report.value = {
    ...report.value,
    data: updatedReport.data,
    review_status: updatedReport.review_status,
  }
}
</script>

<template>
  <div class="report-view">
    <SkeletonLoader v-if="loading" variant="report" :rows="3" label="Memuat laporan..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else>
      <div class="report-view__header">
        <h1>Laporan #{{ report.id }} ({{ report.tier }})</h1>
        <div class="report-view__downloads">
          <button type="button" class="btn" :disabled="!!downloading" @click="downloadPdf('id')">
            {{ downloading === 'id' ? 'Menyiapkan PDF...' : 'Unduh PDF (Indonesia)' }}
          </button>
          <button type="button" class="btn" :disabled="!!downloading" @click="downloadPdf('en')">
            {{ downloading === 'en' ? 'Preparing PDF...' : 'Download PDF (English)' }}
          </button>
        </div>
      </div>

      <div class="report-view__lang" role="group" aria-label="Bahasa laporan">
        <button
          v-for="b in BAHASA"
          :key="b.value"
          type="button"
          class="report-view__lang-btn"
          :class="{ 'report-view__lang-btn--active': bahasa === b.value }"
          :aria-pressed="bahasa === b.value"
          @click="switchBahasa(b.value)"
        >
          {{ b.label }}
        </button>
      </div>

      <p v-if="bahasa === 'en'" class="report-view__disclaimer">
        This report is a <strong>reflective insight tool</strong> based on graphology, not a
        psychological or clinical diagnosis. Graphology lacks the scientific grounding of standard
        psychometrics — do not use this report as the sole basis for important decisions
        (recruitment, medical, legal, financial).
      </p>
      <p v-else class="report-view__disclaimer">
        Laporan ini adalah <strong>alat insight reflektif</strong> berbasis grafologi, bukan
        diagnosis psikologis atau klinis. Grafologi tidak memiliki dasar ilmiah sekuat psikometri
        standar — jangan jadikan laporan ini satu-satunya dasar keputusan penting (rekrutmen, medis,
        hukum, finansial). Lihat <RouterLink to="/syarat">Syarat Layanan</RouterLink>.
      </p>

      <ReportReviewPanel
        v-if="isStaff"
        :report="report"
        :can-review="canReview"
        @reviewed="onReviewed"
      />

      <ConsultationPanel
        v-if="report.tier === 'master'"
        :sample-id="report.sample_id"
        :consultation="report.sample?.consultation ?? null"
        :editable="canEdit"
        @saved="onConsultationSaved"
      />

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
        :bahasa="bahasa"
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
.report-view__downloads {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.report-view__lang {
  display: inline-flex;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  overflow: hidden;
  margin: 0 0 14px;
}
.report-view__lang-btn {
  border: none;
  background: var(--color-surface);
  color: var(--color-text-soft);
  padding: 6px 14px;
  font-size: 13px;
  cursor: pointer;
  font-family: inherit;
}
.report-view__lang-btn--active {
  background: var(--color-ink);
  color: var(--color-paper);
}
.report-view__disclaimer {
  background: var(--color-gold-soft);
  border-left: 3px solid var(--color-gold);
  padding: 10px 14px;
  font-size: 0.875rem;
  margin: 0 0 20px;
  border-radius: 4px;
}
.report-view__header h1 {
  font-size: 22px;
}
</style>
