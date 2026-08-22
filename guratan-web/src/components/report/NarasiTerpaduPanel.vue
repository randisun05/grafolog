<script setup>
import { ref, watch } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  reportId: { type: [Number, String], required: true },
  narasiTerpadu: { type: String, default: null },
  narasiBahasa: { type: String, default: null },
  narasiStatus: { type: String, default: 'belum_dibuat' },
})
const emit = defineEmits(['updated'])

const toast = useToast()
const draft = ref(props.narasiTerpadu ?? '')
const bahasa = ref(props.narasiBahasa ?? 'id')
const generating = ref(false)
const saving = ref(false)

watch(
  () => [props.narasiTerpadu, props.narasiBahasa],
  ([teks, b]) => {
    draft.value = teks ?? ''
    bahasa.value = b ?? 'id'
  },
)

const statusLabel = {
  belum_dibuat: 'Belum dibuat',
  draft: 'Draft (belum terlihat klien)',
  final: 'Final (terlihat klien)',
}

async function generateDraft() {
  generating.value = true
  try {
    const { data } = await api.post(`/reports/${props.reportId}/narasi-terpadu/generate`, { bahasa: bahasa.value })
    emit('updated', data)
    toast.push('Draft narasi terpadu berhasil dibuat.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membuat draft narasi terpadu.')
  } finally {
    generating.value = false
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

    <div class="narasi-terpadu__controls">
      <label class="narasi-terpadu__bahasa">
        Bahasa
        <select v-model="bahasa">
          <option value="id">Bahasa Indonesia</option>
          <option value="en">English</option>
        </select>
      </label>
      <button type="button" class="btn" :disabled="generating" @click="generateDraft">
        {{ generating ? 'Membuat draft...' : 'Generate Draft AI' }}
      </button>
    </div>

    <textarea
      v-model="draft"
      class="narasi-terpadu__textarea"
      rows="14"
      placeholder="Belum ada narasi. Klik 'Generate Draft AI' atau tulis manual di sini."
    ></textarea>

    <div class="narasi-terpadu__actions">
      <button type="button" class="btn" :disabled="saving" @click="save('draft')">
        {{ saving ? 'Menyimpan...' : 'Simpan sebagai Draft' }}
      </button>
      <button type="button" class="btn btn--primary" :disabled="saving" @click="markFinal">Tandai Final</button>
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
.narasi-terpadu__hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin: 6px 0 14px;
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
