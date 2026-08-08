<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue'
import api from '@/lib/api'

const loading = ref(true)
const loadError = ref('')
const sindromList = ref([]) // [{id, kode_romawi, nama, aspek: [{id, kode, nama, indikator_count}]}]
const aspekMeta = reactive({}) // aspek_id -> { sindromId, kode, nama }

const selectedSindromId = ref(null)
const selectedAspekId = ref(null)
const selectedIndikatorId = ref(null)

const aspekDetail = ref(null) // { aspek, indikator: [...] }
const aspekLoading = ref(false)
const indikatorDetail = ref(null) // { indikator, referensi_keluar, referensi_masuk }
const indikatorLoading = ref(false)

const mapContainerRef = ref(null)
const sindromRefs = reactive({})
const aspekRefs = reactive({})
const indikatorRefs = reactive({})
const lines = ref([])

const selectedSindrom = computed(() => sindromList.value.find((s) => s.id === selectedSindromId.value) ?? null)

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/knowledge/concept-map')
    sindromList.value = data
    for (const s of data) {
      for (const a of s.aspek) {
        aspekMeta[a.id] = { sindromId: s.id, kode: a.kode, nama: a.nama }
      }
    }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat peta konsep.'
  } finally {
    loading.value = false
  }
  window.addEventListener('resize', recomputeLines)
})
onUnmounted(() => window.removeEventListener('resize', recomputeLines))

async function selectSindrom(id) {
  selectedSindromId.value = id
  selectedAspekId.value = null
  selectedIndikatorId.value = null
  aspekDetail.value = null
  indikatorDetail.value = null
  await recomputeLines()
}

async function selectAspek(id) {
  selectedAspekId.value = id
  selectedIndikatorId.value = null
  indikatorDetail.value = null
  aspekLoading.value = true
  try {
    const { data } = await api.get(`/admin/knowledge/concept-map/aspek/${id}`)
    aspekDetail.value = data
  } finally {
    aspekLoading.value = false
  }
  await recomputeLines()
}

async function selectIndikator(id) {
  selectedIndikatorId.value = id
  indikatorLoading.value = true
  try {
    const { data } = await api.get(`/admin/knowledge/concept-map/indikator/${id}`)
    indikatorDetail.value = data
  } finally {
    indikatorLoading.value = false
  }
  await recomputeLines()
}

// Klik chip relasi (referensi keluar/masuk) di panel detail: lompat peta
// ke Sindrom -> Aspek -> Indikator target, bukan cuma menampilkan teksnya -
// ini yang bikin panel relasi jadi ikut "bisa dijelajah", bukan info statis.
async function jumpToIndikator(ind) {
  const meta = aspekMeta[ind.aspek_id]
  if (!meta) return
  selectedSindromId.value = meta.sindromId
  await selectAspek(ind.aspek_id)
  await selectIndikator(ind.id)
}

function setRef(store, id) {
  return (el) => {
    if (el) store[id] = el
    else delete store[id]
  }
}

async function recomputeLines() {
  await nextTick()
  if (!mapContainerRef.value) return
  const containerRect = mapContainerRef.value.getBoundingClientRect()
  const pts = []

  if (selectedSindromId.value && sindromRefs[selectedSindromId.value] && selectedAspekId.value && aspekRefs[selectedAspekId.value]) {
    pts.push(edgeLine(sindromRefs[selectedSindromId.value], aspekRefs[selectedAspekId.value], containerRect))
  }
  if (selectedAspekId.value && aspekRefs[selectedAspekId.value] && selectedIndikatorId.value && indikatorRefs[selectedIndikatorId.value]) {
    pts.push(edgeLine(aspekRefs[selectedAspekId.value], indikatorRefs[selectedIndikatorId.value], containerRect))
  }
  lines.value = pts
}

function edgeLine(fromEl, toEl, containerRect) {
  const a = fromEl.getBoundingClientRect()
  const b = toEl.getBoundingClientRect()
  const x1 = a.right - containerRect.left
  const y1 = a.top + a.height / 2 - containerRect.top
  const x2 = b.left - containerRect.left
  const y2 = b.top + b.height / 2 - containerRect.top
  const midX = (x1 + x2) / 2

  return { d: `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}` }
}

function formatRule(rule) {
  if (rule.rule_type === 'category') {
    return `${rule.variable_a.nama} = "${rule.category_label}"`
  }
  const op = { equals: '=', greater_than: '>', less_than: '<', greater_or_equal: '≥', less_or_equal: '≤' }[rule.operator]
  const right = rule.variable_b
    ? `${rule.koefisien != 1 ? `${rule.koefisien}× ` : ''}${rule.variable_b.nama}`
    : rule.compare_value
  return `${rule.variable_a.nama} ${op} ${right}`
}
</script>

<template>
  <div class="concept-map">
    <p class="concept-map__hint">
      Jelajahi relasi knowledge base: Sindrom → Aspek → Indikator, lalu klik 1 Indikator untuk
      melihat aturan operator (↔ Variabel Ukur) dan referensi silangnya (↔ Indikator lain, dua
      arah). Murni baca — untuk mengubah data, gunakan tab lain di halaman ini.
    </p>

    <p v-if="loading">Memuat peta konsep...</p>
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <div v-else ref="mapContainerRef" class="concept-map__body">
      <svg class="concept-map__lines">
        <path v-for="(l, i) in lines" :key="i" :d="l.d" fill="none" stroke="var(--color-seal)" stroke-width="1.5" />
      </svg>

      <div class="concept-map__col">
        <h4>Sindrom</h4>
        <button
          v-for="s in sindromList"
          :key="s.id"
          :ref="setRef(sindromRefs, s.id)"
          type="button"
          class="concept-map__node"
          :class="{ 'concept-map__node--active': selectedSindromId === s.id }"
          @click="selectSindrom(s.id)"
        >
          {{ s.kode_romawi }}. {{ s.nama }}
        </button>
      </div>

      <div class="concept-map__col">
        <h4>Aspek</h4>
        <p v-if="!selectedSindrom" class="concept-map__empty">Pilih Sindrom dulu.</p>
        <button
          v-for="a in selectedSindrom?.aspek ?? []"
          :key="a.id"
          :ref="setRef(aspekRefs, a.id)"
          type="button"
          class="concept-map__node"
          :class="{ 'concept-map__node--active': selectedAspekId === a.id }"
          @click="selectAspek(a.id)"
        >
          {{ a.kode }} - {{ a.nama }}
          <span class="concept-map__badge">{{ a.indikator_count }}</span>
        </button>
      </div>

      <div class="concept-map__col">
        <h4>Indikator</h4>
        <p v-if="!selectedAspekId" class="concept-map__empty">Pilih Aspek dulu.</p>
        <p v-else-if="aspekLoading" class="concept-map__empty">Memuat...</p>
        <button
          v-for="ind in aspekDetail?.indikator ?? []"
          :key="ind.id"
          :ref="setRef(indikatorRefs, ind.id)"
          type="button"
          class="concept-map__node concept-map__node--indikator"
          :class="{ 'concept-map__node--active': selectedIndikatorId === ind.id }"
          @click="selectIndikator(ind.id)"
        >
          <span>{{ ind.kode }} {{ ind.nama }}</span>
          <span class="concept-map__tags">
            <span v-if="ind.rules_count > 0" class="concept-map__badge concept-map__badge--rule">{{ ind.rules_count }} aturan</span>
            <span v-if="ind.cross_ref_count > 0" class="concept-map__badge concept-map__badge--ref">{{ ind.cross_ref_count }} referensi</span>
          </span>
        </button>
      </div>

      <div class="concept-map__col concept-map__col--detail">
        <h4>Relasi</h4>
        <p v-if="!selectedIndikatorId" class="concept-map__empty">Pilih Indikator untuk melihat relasinya.</p>
        <p v-else-if="indikatorLoading" class="concept-map__empty">Memuat...</p>
        <template v-else-if="indikatorDetail">
          <p class="concept-map__detail-title">{{ indikatorDetail.indikator.kode }} — {{ indikatorDetail.indikator.nama }}</p>

          <p class="concept-map__section-label">Aturan Operator (↔ Variabel Ukur)</p>
          <p v-if="indikatorDetail.indikator.rules.length === 0" class="concept-map__empty">
            Tidak ada aturan — dicentang manual berdasar pengamatan.
          </p>
          <ul v-else class="concept-map__relation-list">
            <li v-for="rule in indikatorDetail.indikator.rules" :key="rule.id">{{ formatRule(rule) }}</li>
          </ul>

          <p class="concept-map__section-label">Referensi Silang Keluar (memicu)</p>
          <p v-if="indikatorDetail.referensi_keluar.length === 0" class="concept-map__empty">Tidak ada.</p>
          <ul v-else class="concept-map__relation-list">
            <li v-for="ind in indikatorDetail.referensi_keluar" :key="ind.id">
              <button type="button" class="concept-map__chip" @click="jumpToIndikator(ind)">{{ ind.kode }} — {{ ind.nama }}</button>
            </li>
          </ul>

          <p class="concept-map__section-label">Direferensikan Oleh</p>
          <p v-if="indikatorDetail.referensi_masuk.length === 0" class="concept-map__empty">Tidak ada.</p>
          <ul v-else class="concept-map__relation-list">
            <li v-for="ind in indikatorDetail.referensi_masuk" :key="ind.id">
              <button type="button" class="concept-map__chip" @click="jumpToIndikator(ind)">{{ ind.kode }} — {{ ind.nama }}</button>
            </li>
          </ul>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.concept-map__hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.concept-map__empty {
  color: var(--color-text-soft);
  font-size: 12.5px;
}
.concept-map__body {
  position: relative;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(220px, 1.2fr);
  gap: 14px;
  align-items: start;
}
.concept-map__lines {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  overflow: visible;
}
.concept-map__col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 480px;
  overflow-y: auto;
  padding: 4px;
}
.concept-map__col h4 {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-soft);
  margin-bottom: 2px;
}
.concept-map__node {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: left;
  padding: 8px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  cursor: pointer;
  font-size: 12.5px;
  color: var(--color-ink);
}
.concept-map__node--indikator {
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.concept-map__node--active {
  border-color: var(--color-seal);
  background: var(--color-seal-soft);
}
.concept-map__badge {
  align-self: flex-start;
  font-size: 10.5px;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--color-sage-soft);
  color: var(--color-ink);
}
.concept-map__tags {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  flex-shrink: 0;
}
.concept-map__badge--rule {
  background: var(--color-gold-soft, var(--color-sage-soft));
}
.concept-map__badge--ref {
  background: var(--color-sage-soft);
}
.concept-map__col--detail {
  border-left: 1px solid var(--color-border);
  padding-left: 14px;
}
.concept-map__detail-title {
  font-weight: 600;
  font-size: 13px;
  margin-bottom: 10px;
}
.concept-map__section-label {
  font-size: 11.5px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-soft);
  margin-top: 12px;
  margin-bottom: 4px;
}
.concept-map__relation-list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12.5px;
}
.concept-map__chip {
  border: 1px solid var(--color-border);
  background: var(--color-paper);
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 12px;
  cursor: pointer;
  color: var(--color-ink);
}
.concept-map__chip:hover {
  border-color: var(--color-seal);
}

@media (max-width: 1000px) {
  .concept-map__body {
    grid-template-columns: 1fr;
  }
  .concept-map__lines {
    display: none;
  }
  .concept-map__col--detail {
    border-left: none;
    border-top: 1px solid var(--color-border);
    padding-left: 4px;
    padding-top: 10px;
  }
}
</style>
