<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { TAHAP_KANDIDAT } from '@/lib/format'

const props = defineProps({ positions: { type: Array, default: () => [] } })
const toast = useToast()

const positionId = ref('')
const data = ref(null)
const loading = ref(false)
const showAll = ref(false)

// Analisis gabungan AI per kandidat - hanya untuk yang laporannya sudah disetujui.
const aiBusy = ref(null)
const aiOpen = ref(null)
async function runAi(c) {
  aiBusy.value = c.id
  try {
    const { data: res } = await api.post(`/hr/analysis/${c.id}/ai`)
    c.ai = { summary: res.summary, source: res.source, created_at: res.created_at }
    aiOpen.value = c.id
    toast.push(
      res.cached ? 'Data belum berubah - memakai analisis tersimpan.' : 'Analisis selesai.',
      'success',
    )
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membuat analisis.')
  } finally {
    aiBusy.value = null
  }
}

async function load() {
  loading.value = true
  try {
    data.value = (
      await api.get('/hr/analysis', {
        params: positionId.value ? { position_id: positionId.value } : {},
      })
    ).data
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memuat analisis.')
  } finally {
    loading.value = false
  }
}
watch(positionId, load)

const hasKey = computed(() => data.value?.aspek.some((a) => a.is_key))
const columns = computed(() => {
  if (!data.value) return []
  if (showAll.value || !hasKey.value) return data.value.aspek
  return data.value.aspek.filter((a) => a.is_key)
})
const scoreFields = computed(() =>
  (data.value?.interview_fields ?? []).filter((f) => f.type === 'score'),
)
const choiceFields = computed(() =>
  (data.value?.interview_fields ?? []).filter((f) => f.type === 'choice'),
)

// Shading netral = intensitas skor, BUKAN baik/buruk (sebagian sindrom berpolaritas terbalik).
const shade = (v, max) =>
  v === null || v === undefined ? {} : { background: `rgba(120, 110, 100, ${(v / max) * 0.45})` }

const aspekName = (a) => a.nama_id || a.nama

onMounted(load)
</script>

<template>
  <div class="hra">
    <div class="hra__toolbar">
      <select v-model="positionId" aria-label="Posisi">
        <option value="">Semua kandidat</option>
        <option v-for="p in props.positions" :key="p.id" :value="p.id">{{ p.title }}</option>
      </select>
      <label v-if="hasKey" class="hra__toggle">
        <input v-model="showAll" type="checkbox" /> Tampilkan semua 40 aspek
      </label>
    </div>

    <p v-if="data" class="hra__disclaimer">{{ data.disclaimer }}</p>

    <SkeletonLoader
      v-if="loading && !data"
      variant="table"
      :columns="6"
      label="Memuat analisis..."
    />
    <p v-else-if="data && !data.candidates.length" class="hra__hint">
      Belum ada kandidat di posisi ini.
    </p>
    <div v-else-if="data" class="hra__wrap">
      <table class="hra__table">
        <thead>
          <tr>
            <th class="hra__sticky">Kandidat</th>
            <th
              v-for="a in columns"
              :key="a.id"
              :class="{ hra__key: a.is_key }"
              :title="`${a.sindrom?.kode_romawi}. ${a.sindrom?.nama_id || a.sindrom?.nama}`"
            >
              {{ aspekName(a) }}
            </th>
            <th v-for="f in scoreFields" :key="f.key" class="hra__interview">
              {{ f.label }}
            </th>
            <th v-for="f in choiceFields" :key="f.key" class="hra__interview">{{ f.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in data.candidates" :key="c.id">
            <td class="hra__sticky">
              <span class="hra__name">{{ c.name }}</span>
              <span class="hra__sub"
                >{{ TAHAP_KANDIDAT[c.stage] }} · {{ c.education || '-' }} ·
                {{ c.experience_years ?? '-' }} th</span
              >
              <RouterLink v-if="c.report_id" :to="`/reports/${c.report_id}`" class="hra__sub"
                >Buka laporan</RouterLink
              >
              <span v-else class="hra__sub">Laporan belum tersedia</span>
            </td>
            <td
              v-for="a in columns"
              :key="a.id"
              class="hra__cell"
              :style="shade(c.scores[a.id], 10)"
            >
              {{ c.scores[a.id] ?? '·' }}
            </td>
            <td
              v-for="f in scoreFields"
              :key="f.key"
              class="hra__cell"
              :style="shade(c.interview?.[f.key], 5)"
            >
              {{ c.interview?.[f.key] ?? '·' }}
            </td>
            <td v-for="f in choiceFields" :key="f.key" class="hra__choice">
              {{ c.interview?.[f.key] ?? '-' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <section v-if="data?.candidates.some((c) => c.report_id)" class="hra__ai">
      <h3>Analisis gabungan (AI)</h3>
      <p class="hra__hint">
        Merangkum skor grafologi + hasil wawancara terhadap posisi. Data dikirim ke penyedia AI
        <strong>tanpa nama, email, atau telepon</strong>; AI hanya merangkum data tersebut dan tidak
        memutuskan lolos/tidaknya kandidat. Hasil disimpan - data yang sama tidak memanggil AI lagi.
      </p>
      <ul>
        <li v-for="c in data.candidates.filter((x) => x.report_id)" :key="c.id">
          <div class="hra__ai-head">
            <strong>{{ c.name }}</strong>
            <span v-if="c.ai" class="hra__sub">{{
              c.ai.source === 'ai' ? 'ringkasan AI' : 'ringkasan otomatis (bukan AI)'
            }}</span>
            <button
              v-if="c.ai"
              type="button"
              class="btn"
              @click="aiOpen = aiOpen === c.id ? null : c.id"
            >
              {{ aiOpen === c.id ? 'Tutup' : 'Lihat' }}
            </button>
            <button
              type="button"
              class="btn btn--primary"
              :disabled="aiBusy === c.id"
              @click="runAi(c)"
            >
              {{ aiBusy === c.id ? 'Menganalisis...' : c.ai ? 'Perbarui' : 'Buat analisis' }}
            </button>
          </div>
          <pre v-if="aiOpen === c.id && c.ai" class="hra__ai-text">{{ c.ai.summary }}</pre>
        </li>
      </ul>
    </section>
    <p v-if="data" class="hra__hint">
      Skor aspek 1–10 dari laporan grafologi yang sudah terbit/disetujui; skor wawancara 1–5. Warna
      menunjukkan intensitas, bukan baik/buruk - beberapa sindrom (mis. Stress Vulnerability,
      Problem Areas) justru bermakna sebaliknya. Baca narasi di laporan sebelum menyimpulkan.
    </p>
  </div>
</template>

<style scoped>
.hra {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.hra__toolbar {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}
.hra__toolbar select {
  width: auto;
  margin: 0;
}
.hra__toggle {
  display: flex;
  gap: 6px;
  align-items: center;
  font-size: 13px;
}
.hra__toggle input {
  width: auto;
}
.hra__disclaimer {
  margin: 0;
  padding: 10px 12px;
  border-left: 3px solid var(--color-gold);
  background: var(--color-gold-soft);
  font-size: 13px;
  border-radius: var(--radius-sm);
}
.hra__hint,
.hra__sub {
  color: var(--color-text-soft);
  font-size: 12px;
  margin: 0;
}
.hra__sub {
  display: block;
}
.hra__wrap {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.hra__table {
  border-collapse: collapse;
  font-size: 13px;
  min-width: 100%;
}
.hra__table th {
  padding: 8px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--color-text-soft);
  border-bottom: 1px solid var(--color-border);
  text-align: center;
  min-width: 72px;
  vertical-align: bottom;
}
.hra__table td {
  padding: 8px;
  border-bottom: 1px solid var(--color-border);
}
.hra__key {
  color: var(--color-ink) !important;
  background: var(--color-gold-soft);
}
.hra__interview {
  border-left: 1px dashed var(--color-border);
}
.hra__sticky {
  position: sticky;
  left: 0;
  background: var(--color-surface);
  text-align: left !important;
  min-width: 170px;
  z-index: 1;
}
.hra__name {
  font-weight: 600;
}
.hra__cell {
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.hra__choice {
  font-size: 12px;
  white-space: nowrap;
}
.hra__ai {
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.hra__ai h3 {
  margin: 0;
  font-size: 15px;
}
.hra__ai ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.hra__ai-head {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}
.hra__ai-head .btn:first-of-type {
  margin-left: auto;
}
.hra__ai-text {
  white-space: pre-wrap;
  font-family: inherit;
  font-size: 13.5px;
  line-height: 1.6;
  background: var(--color-paper);
  padding: 12px;
  border-radius: var(--radius-sm);
  margin: 6px 0 0;
}
</style>
