<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/api'
import SindromAccordion from '@/components/scoring/SindromAccordion.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  sampleId: { type: [Number, String], required: true },
  aspekScores: { type: Array, required: true }, // report.aspek_scores (API pakai snake_case) - [{ aspek: {kode,nama}, skor, catatan_grafolog }]
})
const emit = defineEmits(['corrected'])

const toast = useToast()
const open = ref(false)
const loading = ref(true)
const sindromList = ref([])
const scores = ref({})
const catatan = ref('')
const submitting = ref(false)
const submitError = ref('')

const totalAspek = computed(() => sindromList.value.reduce((n, s) => n + s.aspek.length, 0))
const scoredCount = computed(() => Object.values(scores.value).filter((s) => Number.isInteger(s?.skor)).length)
const isComplete = computed(() => totalAspek.value > 0 && scoredCount.value === totalAspek.value)

onMounted(async () => {
  try {
    const { data } = await api.get('/sindrom')
    sindromList.value = data
  } finally {
    loading.value = false
  }
  for (const entry of props.aspekScores) {
    scores.value[entry.aspek.kode] = { skor: entry.skor, catatan_grafolog: entry.catatan_grafolog }
  }
})

function buildSkorPayload() {
  return Object.entries(scores.value)
    .filter(([, v]) => Number.isInteger(v?.skor))
    .map(([kode, v]) => ({ kode, skor: v.skor, catatan_grafolog: v.catatan_grafolog || null }))
}

async function submitCorrection() {
  if (!isComplete.value) return
  submitting.value = true
  submitError.value = ''
  try {
    const { data } = await api.post(`/samples/${props.sampleId}/scores/correct`, {
      skor: buildSkorPayload(),
      catatan: catatan.value || null,
    })
    toast.push('Skor berhasil dikoreksi, laporan sudah diperbarui.', 'success')
    open.value = false
    emit('corrected', data)
  } catch (e) {
    submitError.value = e.response?.data?.message ?? 'Gagal mengoreksi skor.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="report-correction">
    <button type="button" class="btn" @click="open = !open">
      {{ open ? 'Batal Koreksi' : 'Koreksi Skor' }}
    </button>

    <div v-if="open" class="report-correction__panel">
      <p class="report-correction__warning">
        ⚠ Ini mengubah laporan yang sudah selesai. Versi sebelumnya tetap tersimpan di Riwayat
        Perubahan di bawah, tapi laporan aktif yang dilihat klien akan langsung berubah begitu disimpan.
      </p>
      <p v-if="loading">Memuat form skor...</p>
      <template v-else>
        <SindromAccordion
          v-for="sindrom in sindromList"
          :key="sindrom.id"
          :sindrom="sindrom"
          :scores="scores"
          @update:scores="(v) => (scores = v)"
        />
        <label class="report-correction__catatan">
          Alasan koreksi (opsional, tersimpan di riwayat)
          <textarea v-model="catatan" placeholder="Mis. salah input skor Aspek 07 sebelumnya" />
        </label>
        <p v-if="submitError" class="error">{{ submitError }}</p>
        <button type="button" class="btn btn--primary" :disabled="!isComplete || submitting" @click="submitCorrection">
          {{ submitting ? 'Menyimpan...' : 'Simpan Koreksi & Generate Ulang' }}
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.report-correction {
  margin-bottom: 16px;
}
.report-correction__panel {
  margin-top: 12px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.report-correction__warning {
  background: var(--color-seal-soft);
  border: 1px solid var(--color-seal);
  color: var(--color-seal-dark);
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 13px;
  margin-bottom: 14px;
}
.report-correction__catatan {
  display: block;
  margin: 14px 0;
  font-size: 13px;
  color: var(--color-text-soft);
}
.report-correction__catatan textarea {
  display: block;
  width: 100%;
  min-height: 60px;
  margin-top: 4px;
  box-sizing: border-box;
}
</style>
