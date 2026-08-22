<script setup>
import { ref, watch, onUnmounted } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  reportId: { type: [Number, String], required: true },
  narasiTerpadu: { type: String, default: null },
  narasiBahasa: { type: String, default: null },
  narasiStatus: { type: String, default: 'belum_dibuat' },
  narasiGenerationError: { type: String, default: null },
})
const emit = defineEmits(['updated'])

const toast = useToast()
const draft = ref(props.narasiTerpadu ?? '')
const bahasa = ref(props.narasiBahasa ?? 'id')
const requesting = ref(false) // POST /generate atau /narasi-terpadu sedang di-kirim
const saving = ref(false)
let pollTimer = null

watch(
  () => [props.narasiTerpadu, props.narasiBahasa],
  ([teks, b]) => {
    draft.value = teks ?? ''
    bahasa.value = b ?? 'id'
  },
)

// Job generate berjalan di background (GenerateNarasiTerpaduJob, lihat
// guratan-api/CLAUDE.md) - selama status 'generating', poll laporan tiap
// beberapa detik supaya UI otomatis update begitu job selesai, tanpa
// grafolog perlu reload manual.
watch(
  () => props.narasiStatus,
  (status) => (status === 'generating' ? startPolling() : stopPolling()),
  { immediate: true },
)
onUnmounted(stopPolling)

function startPolling() {
  if (pollTimer) return
  pollTimer = setInterval(async () => {
    const { data } = await api.get(`/reports/${props.reportId}`)
    emit('updated', data)
  }, 4000)
}

function stopPolling() {
  if (!pollTimer) return
  clearInterval(pollTimer)
  pollTimer = null
}

const statusLabel = {
  belum_dibuat: 'Belum dibuat',
  generating: 'Sedang di-generate...',
  draft: 'Draft (belum terlihat klien)',
  final: 'Final (terlihat klien)',
}

async function generateDraft(force = false) {
  requesting.value = true
  try {
    const { data } = await api.post(`/reports/${props.reportId}/narasi-terpadu/generate`, { bahasa: bahasa.value, force })
    emit('updated', data)
    toast.push('Draft narasi terpadu sedang diproses di background.', 'success')
  } catch (e) {
    if (e.response?.status === 409 && !force) {
      if (confirm('Data skor belum berubah sejak generate terakhir. Tetap generate ulang?')) {
        return generateDraft(true)
      }
      return
    }
    toast.push(e.response?.data?.message ?? 'Gagal membuat draft narasi terpadu.')
  } finally {
    requesting.value = false
  }
}

async function save(status) {
  saving.value = true
  try {
    const { data } = await api.patch(`/reports/${props.reportId}/narasi-terpadu`, {
      narasi_terpadu: draft.value,
      bahasa: bahasa.value,
      status,
    })
    emit('updated', data)
    toast.push(status === 'final' ? 'Laporan ditandai final, klien sekarang bisa melihatnya.' : 'Draft tersimpan.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan narasi terpadu.')
  } finally {
    saving.value = false
  }
}

function markFinal() {
  if (!draft.value.trim()) {
    toast.push('Narasi masih kosong, tidak bisa ditandai final.')
    return
  }
  if (!confirm('Tandai final? Klien akan langsung bisa melihat/mengunduh laporan ini.')) return
  save('final')
}
</script>

<template>
  <div class="narasi-terpadu">
    <div class="narasi-terpadu__header">
      <h2>Narasi Terpadu (Laporan Klien)</h2>
      <span class="narasi-terpadu__status" :class="`narasi-terpadu__status--${narasiStatus}`">
        {{ statusLabel[narasiStatus] ?? narasiStatus }}
      </span>
    </div>
    <p class="narasi-terpadu__hint">
      Ini yang dikirim ke klien - deskriptif, mengalir, bukan daftar per-aspek. Breakdown Sindrom/Aspek/Indikator di
      bawah tetap ada sebagai bahan pengecekan internal, tidak lagi dikirim ke klien.
    </p>
    <p v-if="narasiGenerationError" class="narasi-terpadu__error">
      Generate terakhir gagal: {{ narasiGenerationError }}
    </p>
    <p v-if="narasiStatus === 'generating'" class="narasi-terpadu__generating-hint">
      Draft sedang diproses AI di background (bisa 1-3 menit untuk laporan panjang) - halaman ini otomatis update
      begitu selesai, tidak perlu di-reload.
    </p>

    <div class="narasi-terpadu__controls">
      <label class="narasi-terpadu__bahasa">
        Bahasa
        <select v-model="bahasa" :disabled="narasiStatus === 'generating'">
          <option value="id">Bahasa Indonesia</option>
          <option value="en">English</option>
        </select>
      </label>
      <button type="button" class="btn" :disabled="requesting || narasiStatus === 'generating'" @click="generateDraft(false)">
        {{ narasiStatus === 'generating' ? 'Sedang diproses...' : 'Generate Draft AI' }}
      </button>
    </div>

    <textarea
      v-model="draft"
      class="narasi-terpadu__textarea"
      rows="14"
      :disabled="narasiStatus === 'generating'"
      placeholder="Belum ada narasi. Klik 'Generate Draft AI' atau tulis manual di sini."
    ></textarea>

    <div class="narasi-terpadu__actions">
      <button type="button" class="btn" :disabled="saving || narasiStatus === 'generating'" @click="save('draft')">
        {{ saving ? 'Menyimpan...' : 'Simpan sebagai Draft' }}
      </button>
      <button type="button" class="btn btn--primary" :disabled="saving || narasiStatus === 'generating'" @click="markFinal">
        Tandai Final
      </button>
    </div>
  </div>
</template>

<style scoped>
.narasi-terpadu {
  margin: 20px 0;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
}
.narasi-terpadu__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 16px;
}
.narasi-terpadu__header h2 {
  font-size: 17px;
  margin: 0;
}
.narasi-terpadu__status {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--color-background);
  color: var(--color-text-soft);
}
.narasi-terpadu__status--final {
  background: var(--color-sage);
  color: #fff;
}
.narasi-terpadu__status--draft {
  background: var(--color-gold, #c9a227);
  color: #fff;
}
.narasi-terpadu__status--generating {
  background: var(--color-seal);
  color: #fff;
}
.narasi-terpadu__hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin: 6px 0 14px;
}
.narasi-terpadu__error {
  font-size: 12.5px;
  color: var(--color-seal);
  margin: 0 0 10px;
}
.narasi-terpadu__generating-hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  font-style: italic;
  margin: 0 0 10px;
}
.narasi-terpadu__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}
.narasi-terpadu__bahasa {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}
.narasi-terpadu__textarea {
  width: 100%;
  font-family: inherit;
  font-size: 14px;
  line-height: 1.6;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  resize: vertical;
}
.narasi-terpadu__actions {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}
</style>
