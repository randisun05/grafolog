<script setup>
// Tab "Matriks" Admin KB (2026-10-16): keterkaitan variabel ukur, indikator,
// dan aspek dari indikator_rules + korelasi empiris dari data sampel.
// Murni baca - klik indikator membuka tab Indikator untuk mengedit aturannya.
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

const emit = defineEmits(['open-indikator'])

const views = [
  { key: 'celah', label: 'Ringkasan & Celah' },
  { key: 'va', label: 'Variabel × Aspek' },
  { key: 'aa', label: 'Aspek → Aspek' },
  { key: 'ciri', label: 'Ciri Bersama' },
  { key: 'empiris', label: 'Korelasi Empiris' },
]
const view = ref('celah')

const loading = ref(true)
const loadError = ref('')
const data = ref(null)
const selected = ref(null) // { judul, sub, indikator?: [], tautan?: [] }

const aspekById = computed(() =>
  Object.fromEntries((data.value?.aspek ?? []).map((a) => [a.id, a])),
)
const variabelById = computed(() =>
  Object.fromEntries((data.value?.variabel ?? []).map((v) => [v.id, v])),
)
const groups = computed(() =>
  (data.value?.sindrom ?? []).map((s) => ({
    s,
    aspek: data.value.aspek.filter((a) => a.sindrom_kode === s.kode_romawi),
  })),
)
const flatAspek = computed(() => groups.value.flatMap((g) => g.aspek))

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    data.value = (await api.get('/admin/knowledge/matrix')).data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat matriks.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

// --- warna sel: satu hue (segel), 5 tingkat --------------------------------
const STEPS = [18, 34, 52, 72, 92]
function cellStyle(v, max) {
  if (!v) return {}
  const pct = STEPS[Math.min(4, Math.floor((v / max) * 4.999))]
  return {
    background: `color-mix(in oklab, var(--color-seal-dark) ${pct}%, var(--color-surface))`,
    color: pct >= 52 ? 'var(--color-paper)' : 'var(--color-text)',
  }
}

// --- Variabel × Aspek -----------------------------------------------------
const vaMap = computed(() => {
  const m = {}
  for (const c of data.value?.variabel_aspek ?? []) m[`${c.variable_id}:${c.aspek_id}`] = c
  return m
})
const vaRows = computed(() => {
  if (!data.value) return []
  const reach = {}
  for (const c of data.value.variabel_aspek) reach[c.variable_id] = (reach[c.variable_id] ?? 0) + 1
  return data.value.variabel
    .filter((v) => v.dipakai)
    .sort((a, b) => (reach[b.id] ?? 0) - (reach[a.id] ?? 0))
    .map((v) => ({ ...v, reach: reach[v.id] ?? 0 }))
})
const vaMax = computed(() =>
  Math.max(1, ...(data.value?.variabel_aspek ?? []).map((c) => c.indikator.length)),
)
function pickVa(v, a) {
  const c = vaMap.value[`${v.id}:${a.id}`]
  if (!c) return
  selected.value = {
    judul: `${v.nama} → ${a.kode} ${a.nama}`,
    sub: `${c.indikator.length} indikator bisa tercentang otomatis dari variabel ini.`,
    indikator: c.indikator,
  }
}

// --- Aspek → Aspek --------------------------------------------------------
const aaLevel = ref('sindrom')
const aaMap = computed(() => {
  const m = {}
  for (const c of data.value?.aspek_aspek ?? []) m[`${c.dari_aspek_id}:${c.ke_aspek_id}`] = c.tautan
  return m
})
const ssMap = computed(() => {
  const m = {}
  for (const c of data.value?.aspek_aspek ?? []) {
    const k = `${aspekById.value[c.dari_aspek_id].sindrom_kode}:${aspekById.value[c.ke_aspek_id].sindrom_kode}`
    ;(m[k] ??= []).push(...c.tautan)
  }
  return m
})
const aaMax = computed(() => Math.max(1, ...Object.values(aaMap.value).map((t) => t.length)))
const ssMax = computed(() => Math.max(1, ...Object.values(ssMap.value).map((t) => t.length)))
function pickAa(judul, tautan) {
  if (!tautan?.length) return
  selected.value = {
    judul,
    sub: `${tautan.length} tautan: indikator kiri tercentang → indikator kanan ikut.`,
    tautan,
  }
}

// --- Ciri bersama -----------------------------------------------------------
const ciriFilter = ref('')
const ciriList = computed(() => {
  const q = ciriFilter.value.trim().toLowerCase()
  const all = data.value?.ciri_bersama.grup ?? []
  return q ? all.filter((g) => g.nama.toLowerCase().includes(q)) : all
})

// --- Celah ----------------------------------------------------------------
const celah = computed(() => data.value?.celah)

// --- Empiris ----------------------------------------------------------------
const minN = ref(30)
const empiris = ref(null)
const empirisLoading = ref(false)
const empirisError = ref('')
async function loadEmpiris() {
  empirisLoading.value = true
  empirisError.value = ''
  try {
    empiris.value = (
      await api.get('/admin/knowledge/matrix/empiris', { params: { min_n: minN.value } })
    ).data
  } catch (e) {
    empirisError.value = e.response?.data?.message ?? 'Gagal menghitung korelasi.'
  } finally {
    empirisLoading.value = false
  }
}
function topPairs(sel) {
  return [...sel].sort((a, b) => Math.abs(b.r) - Math.abs(a.r)).slice(0, 25)
}
function selectView(k) {
  view.value = k
  selected.value = null
  if (k === 'empiris' && !empiris.value) loadEmpiris()
}
</script>

<template>
  <div class="km-matrix">
    <SkeletonLoader v-if="loading" variant="table" :rows="6" label="Memuat matriks..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <template v-else>
      <p class="km-matrix__intro">
        Peta keterkaitan dari aturan di knowledge base: variabel ukur mencentang indikator,
        indikator memicu indikator lain, dan indikator bernama sama di aspek berbeda dihitung
        sebagai ciri yang sama. Ini menggambarkan aturan pakem, bukan korelasi yang terbukti,
        kecuali tab Korelasi Empiris.
      </p>

      <div class="km-matrix__nav" role="tablist">
        <button
          v-for="v in views"
          :key="v.key"
          type="button"
          role="tab"
          :aria-selected="view === v.key"
          class="km-matrix__nav-btn"
          :class="{ 'km-matrix__nav-btn--active': view === v.key }"
          @click="selectView(v.key)"
        >
          {{ v.label }}
        </button>
      </div>

      <!-- RINGKASAN & CELAH -->
      <section v-if="view === 'celah'" class="km-matrix__section">
        <div class="km-matrix__facts">
          <div>
            <b>{{ data.ringkasan.aturan_ukur }}</b
            ><span>aturan ukur (variabel → indikator)</span>
          </div>
          <div>
            <b>{{ data.ringkasan.tautan_indikator }}</b
            ><span>tautan indikator → indikator</span>
          </div>
          <div>
            <b>{{ data.ringkasan.variabel_dipakai }} / {{ data.ringkasan.variabel }}</b
            ><span>variabel ukur dipakai aturan</span>
          </div>
          <div>
            <b>{{ data.ringkasan.indikator_beraturan }} / {{ data.ringkasan.indikator }}</b>
            <span>indikator bisa tercentang otomatis</span>
          </div>
        </div>

        <div class="km-matrix__gap">
          <h3>Ciri bersama</h3>
          <p>
            {{ data.ciri_bersama.grup.length }} ciri tulisan muncul di 2 aspek atau lebih.
            {{ celah.pasangan_ciri_belum_tertaut }} pasangan belum punya aturan tautan.
            <strong v-if="data.ciri_bersama.aktif">Sinkronisasi ciri bersama AKTIF</strong>
            <strong v-else>Sinkronisasi ciri bersama NONAKTIF</strong>:
            {{
              data.ciri_bersama.aktif
                ? 'mencentang satu ciri otomatis mencentang kembarannya di aspek lain, ke arah mana pun.'
                : 'hanya aturan tautan (satu arah) yang berlaku. Aktifkan lewat CHECKLIST_CIRI_BERSAMA=true.'
            }}
          </p>
        </div>

        <div class="km-matrix__gap">
          <h3>Arah tautan indikator</h3>
          <p>
            {{ celah.arah_tautan.maju }} maju (aspek kecil → besar),
            {{ celah.arah_tautan.mundur }} mundur, {{ celah.arah_tautan.dua_arah }} pasangan dua
            arah. Rantai terpanjang {{ celah.rantai_terpanjang }} indikator,
            {{ celah.ada_siklus ? 'ADA siklus' : 'tanpa siklus' }}.
          </p>
        </div>

        <div class="km-matrix__gap">
          <h3>Variabel ukur tanpa aturan ({{ celah.variabel_tanpa_aturan.length }})</h3>
          <p class="km-matrix__muted">
            Diukur grafolog, tetapi hasilnya belum mencentang indikator apa pun.
          </p>
          <div class="km-matrix__chips">
            <span v-for="id in celah.variabel_tanpa_aturan" :key="id" class="km-matrix__chip">
              {{ variabelById[id].nama }}
            </span>
          </div>
        </div>

        <div class="km-matrix__gap">
          <h3>Aspek tanpa indikator terukur ({{ celah.aspek_tanpa_indikator_terukur.length }})</h3>
          <p class="km-matrix__muted">
            Skornya hanya naik lewat tautan, ciri bersama, atau centang manual.
            <template v-if="celah.aspek_tanpa_aturan.length">
              Tanpa aturan sama sekali:
              {{
                celah.aspek_tanpa_aturan
                  .map((id) => `${aspekById[id].kode} ${aspekById[id].nama}`)
                  .join(', ')
              }}.
            </template>
          </p>
          <div class="km-matrix__chips">
            <span
              v-for="id in celah.aspek_tanpa_indikator_terukur"
              :key="id"
              class="km-matrix__chip"
            >
              {{ aspekById[id].kode }} {{ aspekById[id].nama }}
            </span>
          </div>
        </div>

        <div class="km-matrix__gap">
          <h3>Indikator tanpa aturan</h3>
          <p class="km-matrix__muted">
            {{ celah.indikator_tanpa_aturan }} indikator hanya bisa dicentang manual. Tambahkan
            aturan lewat tab Indikator → Aturan Operator.
          </p>
        </div>
      </section>

      <!-- VARIABEL × ASPEK -->
      <section v-else-if="view === 'va'" class="km-matrix__section">
        <p class="km-matrix__muted">
          Angka = jumlah indikator di aspek itu yang bisa dicentang oleh variabel tersebut. Klik sel
          untuk melihat indikatornya.
        </p>
        <div class="km-matrix__scroll">
          <table class="km-matrix__hm">
            <thead>
              <tr>
                <th></th>
                <th
                  v-for="g in groups"
                  :key="g.s.id"
                  :colspan="g.aspek.length"
                  class="km-matrix__band"
                  :title="g.s.nama"
                >
                  {{ g.s.kode_romawi }}
                </th>
              </tr>
              <tr>
                <th></th>
                <th v-for="a in flatAspek" :key="a.id" class="km-matrix__col" :title="a.nama">
                  <span>{{ a.kode }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in vaRows" :key="v.id">
                <th class="km-matrix__row">
                  {{ v.nama }} <span class="km-matrix__muted">{{ v.reach }}</span>
                </th>
                <td v-for="a in flatAspek" :key="a.id">
                  <button
                    v-if="vaMap[`${v.id}:${a.id}`]"
                    type="button"
                    class="km-matrix__cell"
                    :style="cellStyle(vaMap[`${v.id}:${a.id}`].indikator.length, vaMax)"
                    :title="`${v.nama} → ${a.kode} ${a.nama}`"
                    @click="pickVa(v, a)"
                  >
                    {{ vaMap[`${v.id}:${a.id}`].indikator.length }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ASPEK → ASPEK -->
      <section v-else-if="view === 'aa'" class="km-matrix__section">
        <div class="km-matrix__seg" role="group" aria-label="Tingkat">
          <button
            type="button"
            :aria-pressed="aaLevel === 'sindrom'"
            @click="((aaLevel = 'sindrom'), (selected = null))"
          >
            Per sindrom
          </button>
          <button
            type="button"
            :aria-pressed="aaLevel === 'aspek'"
            @click="((aaLevel = 'aspek'), (selected = null))"
          >
            Per aspek
          </button>
        </div>
        <p class="km-matrix__muted">
          Baris = aspek sumber (indikatornya tercentang), kolom = aspek yang ikut terpengaruh. Klik
          sel untuk melihat tautannya.
        </p>
        <div class="km-matrix__scroll">
          <table v-if="aaLevel === 'sindrom'" class="km-matrix__hm km-matrix__hm--big">
            <thead>
              <tr>
                <th></th>
                <th
                  v-for="c in data.sindrom"
                  :key="c.id"
                  class="km-matrix__col-plain"
                  :title="c.nama"
                >
                  {{ c.kode_romawi }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in data.sindrom" :key="r.id">
                <th class="km-matrix__row">{{ r.kode_romawi }} {{ r.nama_id || r.nama }}</th>
                <td
                  v-for="c in data.sindrom"
                  :key="c.id"
                  :class="{ 'km-matrix__diag': r.id === c.id }"
                >
                  <button
                    v-if="ssMap[`${r.kode_romawi}:${c.kode_romawi}`]"
                    type="button"
                    class="km-matrix__cell"
                    :style="cellStyle(ssMap[`${r.kode_romawi}:${c.kode_romawi}`].length, ssMax)"
                    @click="
                      pickAa(
                        `Sindrom ${r.kode_romawi} → ${c.kode_romawi}`,
                        ssMap[`${r.kode_romawi}:${c.kode_romawi}`],
                      )
                    "
                  >
                    {{ ssMap[`${r.kode_romawi}:${c.kode_romawi}`].length }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <table v-else class="km-matrix__hm">
            <thead>
              <tr>
                <th></th>
                <th v-for="a in flatAspek" :key="a.id" class="km-matrix__col" :title="a.nama">
                  <span>{{ a.kode }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in flatAspek" :key="r.id">
                <th class="km-matrix__row">{{ r.kode }} {{ r.nama }}</th>
                <td
                  v-for="c in flatAspek"
                  :key="c.id"
                  :class="{ 'km-matrix__diag': r.id === c.id }"
                >
                  <button
                    v-if="aaMap[`${r.id}:${c.id}`]"
                    type="button"
                    class="km-matrix__cell"
                    :style="cellStyle(aaMap[`${r.id}:${c.id}`].length, aaMax)"
                    :title="`${r.kode} ${r.nama} → ${c.kode} ${c.nama}`"
                    @click="
                      pickAa(`${r.kode} ${r.nama} → ${c.kode} ${c.nama}`, aaMap[`${r.id}:${c.id}`])
                    "
                  >
                    {{ aaMap[`${r.id}:${c.id}`].length }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- CIRI BERSAMA -->
      <section v-else-if="view === 'ciri'" class="km-matrix__section">
        <p class="km-matrix__muted">
          Indikator bernama sama di aspek berbeda = satu ciri tulisan.
          {{
            data.ciri_bersama.aktif
              ? 'Sinkronisasi aktif: centang satu, kembarannya ikut (badge "Ciri sama" di checklist).'
              : 'Sinkronisasi nonaktif.'
          }}
          Kolom "Aturan tautan" menghitung pasangan yang juga punya aturan Indikator Lain
          Tercentang.
        </p>
        <input
          v-model="ciriFilter"
          type="search"
          class="km-matrix__search"
          placeholder="Cari ciri, mis. pressure"
        />
        <div class="km-matrix__scroll">
          <table class="km-matrix__list">
            <thead>
              <tr>
                <th>Ciri tulisan</th>
                <th>Indikator</th>
                <th>Aturan tautan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in ciriList" :key="g.nama">
                <td>{{ g.nama }}</td>
                <td>
                  <div class="km-matrix__chips">
                    <button
                      v-for="i in g.indikator"
                      :key="i.id"
                      type="button"
                      class="km-matrix__chip km-matrix__chip--link"
                      :title="`${aspekById[i.aspek_id].kode} ${aspekById[i.aspek_id].nama}`"
                      @click="emit('open-indikator', i.kode)"
                    >
                      {{ i.kode }}
                    </button>
                  </div>
                </td>
                <td class="km-matrix__num">
                  <span
                    class="km-matrix__bar"
                    :aria-label="`${g.pasangan_tertaut} dari ${g.pasangan} pasangan`"
                  >
                    <i
                      class="km-matrix__bar-ok"
                      :style="{ width: (g.pasangan_tertaut / g.pasangan) * 100 + '%' }"
                    ></i>
                  </span>
                  {{ g.pasangan_tertaut }}/{{ g.pasangan }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- EMPIRIS -->
      <section v-else class="km-matrix__section">
        <p class="km-matrix__muted">
          Korelasi Pearson dari data nyata: antar-hasil ukur per sampel dan antar-skor aspek per
          laporan selesai. Pasangan dengan pengamatan bersama di bawah ambang tidak dihitung. Riset
          menunjukkan ciri tulisan umumnya berkorelasi lemah, jadi butuh banyak sampel sebelum angka
          ini bermakna.
        </p>
        <form class="km-matrix__empiris-form" @submit.prevent="loadEmpiris">
          <label for="km-min-n">Minimal pengamatan per pasangan</label>
          <input id="km-min-n" v-model.number="minN" type="number" min="3" max="10000" />
          <button type="submit" class="btn" :disabled="empirisLoading">Hitung</button>
        </form>
        <p v-if="minN < 30" class="km-matrix__warn">
          Ambang di bawah 30 hanya untuk uji coba; korelasinya belum bisa dipercaya.
        </p>
        <SkeletonLoader
          v-if="empirisLoading"
          variant="list"
          :rows="3"
          label="Menghitung korelasi..."
        />
        <p v-else-if="empirisError" class="error">{{ empirisError }}</p>
        <div v-else-if="empiris" class="km-matrix__empiris">
          <div v-for="part in ['variabel', 'aspek']" :key="part">
            <h3>{{ part === 'variabel' ? 'Antar-variabel ukur' : 'Antar-skor aspek' }}</h3>
            <p class="km-matrix__muted">
              {{
                part === 'variabel'
                  ? `${empiris.variabel.n_sampel} sampel terukur`
                  : `${empiris.aspek.n_laporan} laporan selesai`
              }}
              · {{ empiris[part].sel.length }} pasangan memenuhi ambang {{ empiris.min_n }}.
            </p>
            <p v-if="!empiris[part].sel.length" class="km-matrix__empty">
              Belum cukup data untuk dihitung.
            </p>
            <table v-else class="km-matrix__list">
              <thead>
                <tr>
                  <th>Pasangan</th>
                  <th>r</th>
                  <th>n</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in topPairs(empiris[part].sel)" :key="`${p.a}-${p.b}`">
                  <td>
                    <template v-if="part === 'variabel'"
                      >{{ variabelById[p.a]?.nama }} × {{ variabelById[p.b]?.nama }}</template
                    >
                    <template v-else>
                      {{ aspekById[p.a]?.kode }} {{ aspekById[p.a]?.nama }} ×
                      {{ aspekById[p.b]?.kode }} {{ aspekById[p.b]?.nama }}
                    </template>
                  </td>
                  <td class="km-matrix__num">
                    <span class="km-matrix__rbar">
                      <i
                        :class="p.r >= 0 ? 'km-matrix__rbar-pos' : 'km-matrix__rbar-neg'"
                        :style="{
                          width: Math.abs(p.r) * 50 + '%',
                          [p.r >= 0 ? 'left' : 'right']: '50%',
                        }"
                      ></i>
                    </span>
                    {{ p.r.toFixed(2) }}
                  </td>
                  <td class="km-matrix__num">{{ p.n }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- DETAIL SEL -->
      <aside v-if="selected" class="km-matrix__detail" aria-live="polite">
        <div class="km-matrix__detail-head">
          <div>
            <h3>{{ selected.judul }}</h3>
            <p class="km-matrix__muted">{{ selected.sub }}</p>
          </div>
          <button type="button" class="btn" @click="selected = null">Tutup</button>
        </div>
        <ul v-if="selected.indikator" class="km-matrix__detail-list">
          <li v-for="i in selected.indikator" :key="i.id">
            <button
              type="button"
              class="km-matrix__chip km-matrix__chip--link"
              @click="emit('open-indikator', i.kode)"
            >
              {{ i.kode }}
            </button>
            {{ i.nama }}
          </li>
        </ul>
        <ul v-else class="km-matrix__detail-list">
          <li v-for="(t, idx) in selected.tautan" :key="idx">
            <button
              type="button"
              class="km-matrix__chip km-matrix__chip--link"
              @click="emit('open-indikator', t.dari.kode)"
            >
              {{ t.dari.kode }}
            </button>
            →
            <button
              type="button"
              class="km-matrix__chip km-matrix__chip--link"
              @click="emit('open-indikator', t.ke.kode)"
            >
              {{ t.ke.kode }}
            </button>
            {{ t.dari.nama === t.ke.nama ? t.ke.nama : `${t.dari.nama} → ${t.ke.nama}` }}
          </li>
        </ul>
      </aside>
    </template>
  </div>
</template>

<style scoped>
.km-matrix {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}
.km-matrix__intro,
.km-matrix__muted {
  color: var(--color-text-soft);
  font-size: 14px;
  margin: 0;
}
.km-matrix__nav {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.km-matrix__nav-btn {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 13px;
  cursor: pointer;
}
.km-matrix__nav-btn--active {
  background: var(--color-ink);
  border-color: var(--color-ink);
  color: var(--color-paper);
}
.km-matrix__section {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}
.km-matrix__facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
}
.km-matrix__facts div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.km-matrix__facts b {
  font-family: var(--font-heading);
  font-size: 24px;
  font-variant-numeric: tabular-nums;
}
.km-matrix__facts span {
  font-size: 13px;
  color: var(--color-text-soft);
}
.km-matrix__gap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}
.km-matrix__gap h3 {
  margin: 0;
  font-size: 15px;
}
.km-matrix__gap p {
  margin: 0;
  font-size: 14px;
}
.km-matrix__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.km-matrix__chip {
  font-size: 12px;
  padding: 3px 7px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-background);
  color: var(--color-text);
}
.km-matrix__chip--link {
  cursor: pointer;
  font-family: ui-monospace, Menlo, monospace;
}
.km-matrix__chip--link:hover,
.km-matrix__chip--link:focus-visible {
  border-color: var(--color-seal);
  color: var(--color-seal);
}
.km-matrix__scroll {
  overflow-x: auto;
  min-width: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 10px;
}
.km-matrix__hm {
  border-collapse: separate;
  border-spacing: 2px;
  font-size: 12px;
}
.km-matrix__hm th {
  font-weight: 500;
  color: var(--color-text-soft);
  white-space: nowrap;
}
.km-matrix__row {
  text-align: right;
  padding-right: 8px;
  position: sticky;
  left: 0;
  background: var(--color-surface);
  z-index: 1;
}
.km-matrix__band {
  text-align: left;
  border-bottom: 2px solid var(--color-ink);
  color: var(--color-text) !important;
  font-size: 11px;
}
.km-matrix__col span {
  display: inline-block;
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  font-size: 10.5px;
}
.km-matrix__hm td {
  width: 22px;
  height: 22px;
  min-width: 22px;
  padding: 0;
  border-radius: 3px;
  background: var(--color-ink-faint);
}
.km-matrix__hm--big td {
  width: 46px;
  height: 40px;
}
.km-matrix__diag {
  outline: 1px dashed var(--color-border-hover);
  outline-offset: -1px;
}
.km-matrix__cell {
  width: 100%;
  height: 100%;
  border: 0;
  border-radius: 3px;
  font-size: 11px;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}
.km-matrix__cell:focus-visible,
.km-matrix__cell:hover {
  outline: 2px solid var(--color-ink);
}
.km-matrix__seg {
  display: inline-flex;
  align-self: flex-start;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}
.km-matrix__seg button {
  border: 0;
  background: transparent;
  color: var(--color-text-soft);
  padding: 6px 14px;
  font-size: 13px;
  cursor: pointer;
}
.km-matrix__seg button[aria-pressed='true'] {
  background: var(--color-ink);
  color: var(--color-paper);
}
.km-matrix__search {
  max-width: 320px;
}
.km-matrix__list {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.km-matrix__list th {
  text-align: left;
  font-size: 12px;
  color: var(--color-text-soft);
  font-weight: 500;
  padding: 0 10px 8px 0;
  border-bottom: 1px solid var(--color-border);
}
.km-matrix__list td {
  padding: 8px 10px 8px 0;
  border-bottom: 1px solid var(--color-border);
  vertical-align: top;
}
.km-matrix__num {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.km-matrix__bar,
.km-matrix__rbar {
  display: inline-block;
  position: relative;
  width: 80px;
  height: 8px;
  border-radius: 2px;
  background: var(--color-seal-soft);
  margin-right: 6px;
  vertical-align: middle;
  overflow: hidden;
}
.km-matrix__bar-ok {
  position: absolute;
  inset: 0 auto 0 0;
  background: var(--color-sage);
}
.km-matrix__rbar {
  background: var(--color-ink-faint);
}
.km-matrix__rbar i {
  position: absolute;
  top: 0;
  bottom: 0;
}
.km-matrix__rbar-pos {
  background: var(--color-sage);
}
.km-matrix__rbar-neg {
  background: var(--color-seal);
}
.km-matrix__empiris-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  font-size: 14px;
}
.km-matrix__empiris-form input {
  width: 100px;
}
.km-matrix__warn {
  margin: 0;
  font-size: 13px;
  color: var(--color-seal-dark);
}
.km-matrix__empiris {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
}
.km-matrix__empiris h3 {
  margin: 0 0 4px;
  font-size: 15px;
}
.km-matrix__empty {
  font-size: 14px;
  padding: 12px;
  border: 1px dashed var(--color-border-hover);
  border-radius: var(--radius-md);
  color: var(--color-text-soft);
}
.km-matrix__detail {
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.km-matrix__detail-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}
.km-matrix__detail-head h3 {
  margin: 0;
  font-size: 15px;
}
.km-matrix__detail-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13.5px;
  max-height: 320px;
  overflow-y: auto;
}
</style>
