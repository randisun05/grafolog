<script setup>
import { ref, computed } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  report: { type: Object, required: true },
  canReview: { type: Boolean, default: false },
})
const emit = defineEmits(['reviewed'])

const toast = useToast()
const note = ref('')
const submitting = ref(false)

const statusLabel = {
  menunggu_review: 'Menunggu review supervisor',
  perlu_revisi: 'Perlu revisi',
  disetujui: 'Disetujui — terlihat oleh klien',
}
const isApproved = computed(() => props.report.review_status === 'disetujui')

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
      <strong>Status QA:</strong> {{ statusLabel[report.review_status] ?? report.review_status }}
      <span v-if="report.reviewer"> · oleh {{ report.reviewer.name }}</span>
    </p>
    <p
      v-if="report.review_note && report.review_status === 'perlu_revisi'"
      class="review-panel__note"
    >
      <strong>Catatan supervisor:</strong> {{ report.review_note }}
    </p>

    <div v-if="canReview && !isApproved" class="review-panel__actions">
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
