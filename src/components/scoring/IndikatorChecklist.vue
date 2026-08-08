<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/api'

const props = defineProps({
  sampleId: { type: [Number, String], required: true },
})
const emit = defineEmits(['apply'])

const sindromList = ref([]) // GET /checklist response shape
const loading = ref(true)
const refreshing = ref(false)
const expandedSindrom = ref({}) // { [sindromId]: boolean } - open/close UI state, toggles freely
// Gerbang "sudah ditinjau" - terpisah dari expandedSindrom di atas karena
// itu boleh ditutup lagi setelah dibaca tanpa kehilangan progres. Bukan
// bukti sempurna grafolog benar-benar membaca tiap Indikator, tapi jauh
// lebih kuat daripada tidak ada gerbang sama sekali (bug ditemukan lewat
// review 2026-08-08: applyTally() dulu menulis skor floor=1 untuk SEMUA 40
// Aspek sekaligus tanpa syarat, jadi tombol submit bisa aktif walau
// grafolog baru buka 2 dari 8 Sindrom).
const reviewedSindrom = ref({}) // { [sindromId]: true } - set once, never unset

function toggleExpand(sindromId) {
  expandedSindrom.value[sindromId] = !expandedSindrom.value[sindromId]
  if (expandedSindrom.value[sindromId]) reviewedSindrom.value[sindromId] = true
}

const reviewedSindromCount = computed(() => Object.values(reviewedSindrom.value).filter(Boolean).length)
const allSindromReviewed = computed(() => sindromList.value.length > 0 && reviewedSindromCount.value >= sindromList.value.length)

async function load() {
  refreshing.value = true
  try {
    const { data } = await api.get(`/samples/${props.sampleId}/checklist`)
    sindromList.value = data.sindrom
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

onMounted(load)

const sumberLabel = { auto: 'Auto', cascade: 'Terkait', manual: 'Manual' }

async function toggleIndikator(indikator, checked) {
  const result = await postToggle(indikator.id, checked)
  if (result.requires_confirmation) {
    const daftar = result.cascade_candidates.map((c) => `${c.kode} - ${c.nama}`).join('\n')
    const ikutUncheck = window.confirm(
      `Indikator ini sebelumnya memicu ikut tercentangnya:\n\n${daftar}\n\nUncheck juga yang terkait?`,
    )
    // `confirmed: true` di sini WAJIB, terlepas dari ikutUncheck ya/tidak -
    // itu satu-satunya cara backend membedakan "grafolog sudah menjawab
    // dialognya" dari "belum pernah ditanya sama sekali" (bug ditemukan
    // lewat review 2026-08-08: sebelum ada flag ini, menolak cascade selalu
    // memicu ulang prompt yang sama karena keduanya sama-sama array kosong).
    await postToggle(indikator.id, checked, ikutUncheck ? result.cascade_candidates.map((c) => c.id) : [], true)
  }
  await load()
}

async function postToggle(indikatorId, checked, alsoUncheckCascaded = undefined, confirmed = false) {
  const { data } = await api.post(`/samples/${props.sampleId}/checklist/toggle`, {
    indikator_id: indikatorId,
    checked,
    ...(alsoUncheckCascaded ? { also_uncheck_cascaded: alsoUncheckCascaded } : {}),
    ...(confirmed ? { confirmed: true } : {}),
  })
  return data
}

function applyTally() {
  const skor = {}
  for (const sindrom of sindromList.value) {
    if (!reviewedSindrom.value[sindrom.id]) continue
    for (const aspek of sindrom.aspek) {
      skor[aspek.kode] = { skor: aspek.skor }
    }
  }
  emit('apply', skor)
}
</script>

<template>
  <div class="indikator-checklist">
    <div class="indikator-checklist__head">
      <h3>Checklist Indikator</h3>
      <button type="button" class="btn" :disabled="refreshing" @click="load">
        {{ refreshing ? 'Memuat...' : 'Muat Ulang' }}
      </button>
    </div>
    <p class="indikator-checklist__hint">
      Indikator bertanda <strong>Auto</strong>/<strong>Terkait</strong> tercentang otomatis dari hasil
      ukur/referensi silang - alasannya ditampilkan di bawah tiap Indikator. Indikator tanpa aturan
      (mayoritas) dicentang manual berdasar pengamatan langsung.
    </p>

    <p v-if="loading" class="indikator-checklist__note">Memuat checklist...</p>
    <template v-else>
      <div v-for="sindrom in sindromList" :key="sindrom.id" class="indikator-checklist__sindrom">
        <button
          type="button"
          class="indikator-checklist__toggle"
          @click="toggleExpand(sindrom.id)"
        >
          <span>{{ sindrom.kode_romawi }}. {{ sindrom.nama }}</span>
          <span v-if="reviewedSindrom[sindrom.id]" class="indikator-checklist__reviewed">✓ ditinjau</span>
        </button>
        <div v-show="expandedSindrom[sindrom.id]" class="indikator-checklist__body">
          <div v-for="aspek in sindrom.aspek" :key="aspek.id" class="indikator-checklist__aspek">
            <div class="indikator-checklist__aspek-head">
              <span>{{ aspek.kode }} - {{ aspek.nama }}</span>
              <span class="indikator-checklist__tally">{{ aspek.posisi_tercentang }}/{{ aspek.total_posisi }} posisi (skor {{ aspek.skor }})</span>
            </div>
            <label
              v-for="ind in aspek.indikator"
              :key="ind.id"
              class="indikator-checklist__row"
            >
              <input
                type="checkbox"
                :checked="ind.checked"
                @change="toggleIndikator(ind, $event.target.checked)"
              />
              <span class="indikator-checklist__row-main">
                <span class="indikator-checklist__kode">{{ ind.kode }}</span>
                {{ ind.nama }}
                <span v-if="ind.checked && ind.sumber !== 'manual'" class="indikator-checklist__badge">
                  {{ sumberLabel[ind.sumber] }}
                </span>
              </span>
              <span v-if="ind.keterangan_pemicu" class="indikator-checklist__reason">
                {{ ind.keterangan_pemicu }}
              </span>
            </label>
          </div>
        </div>
      </div>

      <div class="indikator-checklist__actions">
        <button type="button" class="btn btn--primary" :disabled="!allSindromReviewed" @click="applyTally">
          Terapkan Skor Checklist ke Form
        </button>
        <span v-if="!allSindromReviewed" class="indikator-checklist__actions-hint">
          Buka & tinjau semua Sindrom dulu ({{ reviewedSindromCount }}/{{ sindromList.length }}) sebelum menerapkan skor.
        </span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.indikator-checklist {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.indikator-checklist__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}
.indikator-checklist__head h3 {
  font-size: 15px;
}
.indikator-checklist__hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.indikator-checklist__note {
  color: var(--color-text-soft);
  font-size: 13px;
}
.indikator-checklist__sindrom {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  margin-bottom: 8px;
  overflow: hidden;
}
.indikator-checklist__toggle {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: var(--color-paper);
  border: none;
  cursor: pointer;
  font-weight: 600;
  font-family: var(--font-heading);
  color: var(--color-ink);
  text-align: left;
}
.indikator-checklist__reviewed {
  font-size: 11px;
  font-weight: normal;
  font-family: var(--font-body);
  color: var(--color-success);
}
.indikator-checklist__body {
  padding: 6px 14px 10px;
}
.indikator-checklist__aspek {
  margin: 10px 0;
}
.indikator-checklist__aspek-head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 6px;
}
.indikator-checklist__tally {
  font-weight: normal;
  color: var(--color-text-soft);
  font-size: 12px;
}
.indikator-checklist__row {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px 8px;
  padding: 4px 0;
  font-size: 13px;
}
.indikator-checklist__row-main {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 200px;
}
.indikator-checklist__kode {
  font-family: var(--font-heading);
  color: var(--color-text-soft);
  font-size: 11.5px;
}
.indikator-checklist__badge {
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--color-sage-soft);
  color: var(--color-ink);
}
.indikator-checklist__reason {
  width: 100%;
  font-size: 11.5px;
  font-style: italic;
  color: var(--color-text-soft);
  padding-left: 24px;
}
.indikator-checklist__actions {
  margin-top: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.indikator-checklist__actions-hint {
  font-size: 12px;
  color: var(--color-text-soft);
}
</style>
