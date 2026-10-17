<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  report: { type: Object, required: true },
  canReview: { type: Boolean, default: false },
  // Grafolog penanganan: memutuskan laporan draf - terbit mandiri / minta validasi (Fase D).
  canPublish: { type: Boolean, default: false },
})
const emit = defineEmits(['reviewed'])

const toast = useToast()
const note = ref('')
const submitting = ref(false)

const statusLabel = {
  draf: 'Draf — belum terlihat klien',
  menunggu_review: 'Menunggu validasi supervisor',
  perlu_revisi: 'Perlu revisi',
  disetujui: 'Terbit — terlihat oleh klien',
}
const isApproved = computed(() => props.report.review_status === 'disetujui')
const isDraft = computed(() => props.report.review_status === 'draf')
const fee = ref(null)

onMounted(async () => {
  if (!props.canPublish || !isDraft.value) return
  try {
    fee.value = (await api.get('/report-validation/fee')).data
  } catch {
    fee.value = null
  }
})

async function decideDraft(action) {
  submitting.value = true
  try {
    const { data } = await api.post(`/reports/${props.report.id}/${action}`)
    toast.push(
      action === 'publish'
        ? 'Laporan diterbitkan dan dikirim ke klien.'
        : 'Laporan diajukan ke supervisor untuk validasi.',
      'success',
    )
    emit('reviewed', data)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memproses laporan.')
  } finally {
    submitting.value = false
  }
}

async function review(decision) {
  submitting.value = true
  try {
    const { data } = await api.post(`/reports/${props.report.id}/review`, {
      decision,
      note: note.value || undefined,
    })
    toast.push(
      decision === 'approve'
        ? 'Laporan disetujui dan dikirim ke klien.'
        : 'Permintaan revisi dikirim ke grafolog.',
      'success',
    )
    note.value = ''
    emit('reviewed', data)
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(
      errors
        ? Object.values(errors).flat()[0]
        : (e.response?.data?.message ?? 'Gagal menyimpan review.'),
    )
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="review-panel" :class="`review-panel--${report.review_status}`">
    <p class="review-panel__status">
      <strong>Status:</strong> {{ statusLabel[report.review_status] ?? report.review_status }}
      <span v-if="isApproved">
        ·
        {{ report.validation === 'supervisor' ? 'divalidasi supervisor' : 'terbit mandiri' }}</span
      >
      <span v-if="report.reviewer"> · oleh {{ report.reviewer.name }}</span>
      <span v-if="report.validation_fee"> · biaya validasi {{ report.validation_fee }} token</span>
    </p>

    <div v-if="canPublish && isDraft" class="review-panel__actions">
      <p class="review-panel__hint">
        Periksa laporan, lalu pilih: terbitkan langsung (tanggung jawab Anda), atau minta supervisor
        memvalidasi lebih dulu. Validasi menambah kredibilitas laporan dan biayanya ditransfer ke
        supervisor peninjau.
      </p>
      <div class="review-panel__buttons">
        <button
          type="button"
          class="btn btn--primary"
          :disabled="submitting"
          @click="decideDraft('publish')"
        >
          Terbitkan langsung
        </button>
        <button
          type="button"
          class="btn"
          :disabled="submitting || (fee && fee.tokens > fee.balance)"
          @click="decideDraft('request-validation')"
        >
          Minta validasi supervisor{{ fee ? ` (${fee.tokens} token)` : '' }}
        </button>
      </div>
      <p v-if="fee" class="review-panel__hint">
        Saldo token Anda: {{ fee.balance }}
        <RouterLink v-if="fee.tokens > fee.balance" to="/token-saya">· Beli token</RouterLink>
      </p>
    </div>
    <p
      v-if="report.review_note && report.review_status === 'perlu_revisi'"
      class="review-panel__note"
    >
      <strong>Catatan supervisor:</strong> {{ report.review_note }}
    </p>

    <div
      v-if="canReview && ['menunggu_review', 'perlu_revisi'].includes(report.review_status)"
      class="review-panel__actions"
    >
      <textarea
        v-model="note"
        rows="2"
        placeholder="Catatan untuk grafolog (wajib bila minta revisi)"
      ></textarea>
      <div class="review-panel__buttons">
        <button
          type="button"
          class="btn btn--primary"
          :disabled="submitting"
          @click="review('approve')"
        >
          Setujui &amp; Rilis ke Klien
        </button>
        <button
          type="button"
          class="btn"
          :disabled="submitting || !note.trim()"
          @click="review('request_revision')"
        >
          Minta Revisi
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.review-panel {
  border: 1px solid var(--color-border);
  border-left: 4px solid var(--color-gold);
  border-radius: 6px;
  padding: 12px 14px;
  margin-bottom: 18px;
  font-size: 14px;
  background: var(--color-surface);
}
.review-panel__hint {
  margin: 0 0 8px;
  color: var(--color-text-soft);
  font-size: 13px;
}
.review-panel--draf {
  border-left-color: var(--color-border-hover);
}
.review-panel--disetujui {
  border-left-color: var(--color-success);
}
.review-panel--perlu_revisi {
  border-left-color: var(--color-error);
}
.review-panel__status,
.review-panel__note {
  margin: 0 0 6px;
}
.review-panel__actions textarea {
  width: 100%;
  margin: 6px 0 8px;
}
.review-panel__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
