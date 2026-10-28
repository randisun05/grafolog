<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatRupiah, formatTanggal } from '@/lib/format'

/** Pemantauan pemakaian & biaya AI. Biaya = perkiraan (token x harga di konfigurasi), bukan tagihan resmi. */
const data = ref(null)
const loading = ref(true)
const error = ref('')
const range = ref('30')

const PURPOSE = {
  terjemah_narasi: 'Terjemah narasi laporan',
  analisis_kandidat: 'Analisis kandidat (HR)',
}
const STATUS = { ok: 'Berhasil', gagal: 'Gagal', cache: 'Dari cache (hemat)' }

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = range.value === 'month' ? { month: 1 } : { days: Number(range.value) }
    data.value = (await api.get('/admin/llm-usage', { params })).data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat data.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

const maxDay = computed(() => Math.max(1, ...(data.value?.by_day ?? []).map((d) => d.cost_idr)))
const fmt = (n) => new Intl.NumberFormat('id-ID').format(n ?? 0)
const failRate = computed(() => {
  const s = data.value?.summary
  const total = (s?.calls ?? 0) + (s?.failed ?? 0)
  return total ? Math.round(((s?.failed ?? 0) / total) * 100) : 0
})
const budgetPct = computed(() => Math.min(100, data.value?.budget?.percent ?? 0))
</script>

<template>
  <div class="llm">
    <div class="llm__head">
      <h1>Pemakaian & Biaya AI</h1>
      <select v-model="range" aria-label="Periode" @change="load">
        <option value="7">7 hari</option>
        <option value="30">30 hari</option>
        <option value="90">90 hari</option>
        <option value="month">Bulan ini</option>
      </select>
    </div>
    <p class="llm__hint">
      Biaya adalah <strong>perkiraan</strong> dari jumlah token × harga per 1 juta token yang diatur
      di konfigurasi server; bukan tagihan resmi penyedia. Isi prompt dan jawaban tidak disimpan.
    </p>

    <SkeletonLoader v-if="loading" variant="dashboard" label="Memuat..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else>
      <div class="llm__kpis">
        <div class="llm__kpi">
          <span>Panggilan berhasil</span><strong>{{ fmt(data.summary.calls) }}</strong>
        </div>
        <div class="llm__kpi">
          <span>Gagal / limit</span><strong>{{ fmt(data.summary.failed) }}</strong>
          <small>{{ failRate }}% dari percobaan</small>
        </div>
        <div class="llm__kpi">
          <span>Hemat dari cache</span><strong>{{ fmt(data.summary.cached) }}</strong>
          <small>analisis yang tidak memanggil AI</small>
        </div>
        <div class="llm__kpi">
          <span>Token (masuk / keluar)</span>
          <strong
            >{{ fmt(data.summary.prompt_tokens) }} / {{ fmt(data.summary.output_tokens) }}</strong
          >
        </div>
        <div class="llm__kpi">
          <span>Perkiraan biaya</span><strong>{{ formatRupiah(data.summary.cost_idr) }}</strong>
          <small
            >≈ ${{ data.summary.cost_usd }} ·
            {{
              data.summary.avg_cost_idr_per_call !== null
                ? formatRupiah(data.summary.avg_cost_idr_per_call) + '/panggilan'
                : '–'
            }}</small
          >
        </div>
        <div class="llm__kpi">
          <span>Rata-rata latensi</span>
          <strong>{{
            data.summary.avg_latency_ms !== null ? data.summary.avg_latency_ms + ' ms' : '–'
          }}</strong>
        </div>
      </div>

      <section class="llm__budget">
        <h2>Anggaran bulan ini</h2>
        <template v-if="data.budget.monthly_idr">
          <div
            class="llm__bar"
            role="progressbar"
            :aria-valuenow="budgetPct"
            aria-valuemin="0"
            aria-valuemax="100"
          >
            <span
              :class="{ 'llm__bar--warn': budgetPct >= 80 }"
              :style="{ width: budgetPct + '%' }"
            />
          </div>
          <p>
            Terpakai {{ formatRupiah(data.budget.spent_idr) }} dari
            {{ formatRupiah(data.budget.monthly_idr) }} ({{ data.budget.percent }}%). Proyeksi akhir
            bulan {{ formatRupiah(data.budget.projected_idr) }}. Peringatan dikirim ke admin di 80%
            dan 100%.
          </p>
        </template>
        <p v-else>
          Belum ada anggaran. Terpakai bulan ini {{ formatRupiah(data.budget.spent_idr) }} (proyeksi
          {{ formatRupiah(data.budget.projected_idr) }}). Atur
          <code>LLM_MONTHLY_BUDGET_IDR</code> di .env untuk mengaktifkan peringatan.
        </p>
      </section>

      <section>
        <h2>Biaya per hari</h2>
        <div
          class="llm__days"
          role="img"
          :aria-label="`Biaya per hari, maks ${formatRupiah(maxDay)}`"
        >
          <div
            v-for="d in data.by_day"
            :key="d.date"
            class="llm__day"
            :title="`${d.date}: ${formatRupiah(d.cost_idr)} · ${d.calls} panggilan, ${d.failed} gagal`"
          >
            <span class="llm__day-bar" :style="{ height: (d.cost_idr / maxDay) * 100 + '%' }" />
          </div>
        </div>
        <p class="llm__hint">
          Arahkan kursor ke batang untuk detail. Sumbu: 0 – {{ formatRupiah(maxDay) }} per hari.
        </p>
      </section>

      <div class="llm__grid">
        <section>
          <h2>Per kegunaan</h2>
          <div class="table-scroll">
            <table class="llm__table">
              <thead>
                <tr>
                  <th>Kegunaan</th>
                  <th>OK</th>
                  <th>Gagal</th>
                  <th>Cache</th>
                  <th>Biaya</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in data.by_purpose" :key="r.purpose">
                  <td>{{ PURPOSE[r.purpose] ?? r.purpose }}</td>
                  <td>{{ r.calls }}</td>
                  <td>{{ r.failed }}</td>
                  <td>{{ r.cached }}</td>
                  <td>{{ formatRupiah(r.cost_idr) }}</td>
                </tr>
                <tr v-if="!data.by_purpose.length">
                  <td colspan="5">Belum ada pemakaian.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
        <section>
          <h2>Per API key (slot)</h2>
          <div class="table-scroll">
            <table class="llm__table">
              <thead>
                <tr>
                  <th>Slot</th>
                  <th>OK</th>
                  <th>Gagal</th>
                  <th>Token</th>
                  <th>Biaya</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in data.by_key" :key="r.key_slot">
                  <td>#{{ r.key_slot }}</td>
                  <td>{{ r.calls }}</td>
                  <td :class="{ llm__bad: r.failed }">{{ r.failed }}</td>
                  <td>{{ fmt(r.tokens) }}</td>
                  <td>{{ formatRupiah(r.cost_idr) }}</td>
                </tr>
                <tr v-if="!data.by_key.length">
                  <td colspan="5">Belum ada panggilan.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="llm__hint">
            Provider {{ data.config.provider }} · model {{ data.config.model }} ·
            {{ data.config.keys_configured }} key terisi · harga ${{
              data.config.price_input_usd_per_m
            }}
            / ${{ data.config.price_output_usd_per_m }} per 1 juta token (masuk/keluar) · kurs
            {{ fmt(data.config.usd_idr) }}.
          </p>
        </section>
      </div>

      <section>
        <h2>Panggilan terbaru</h2>
        <div class="table-scroll">
          <table class="llm__table">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Kegunaan</th>
                <th>Status</th>
                <th>Slot</th>
                <th>Token</th>
                <th>Biaya</th>
                <th>Latensi</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in data.recent" :key="i">
                <td>{{ formatTanggal(r.at, true) }}</td>
                <td>{{ PURPOSE[r.purpose] ?? r.purpose }}</td>
                <td :class="{ llm__bad: r.status === 'gagal' }">
                  {{ STATUS[r.status] ?? r.status }}
                </td>
                <td>{{ r.key_slot ? '#' + r.key_slot : '–' }}</td>
                <td>{{ fmt(r.tokens) }}</td>
                <td>{{ r.status === 'ok' ? 'Rp' + r.cost_idr : '–' }}</td>
                <td>{{ r.latency_ms ? r.latency_ms + ' ms' : '–' }}</td>
                <td>
                  {{ r.error ?? ''
                  }}{{ r.http_status && r.status === 'gagal' ? ' (' + r.http_status + ')' : '' }}
                </td>
              </tr>
              <tr v-if="!data.recent.length">
                <td colspan="8">Belum ada panggilan AI tercatat.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.llm {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.llm__head {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
}
.llm__head select {
  width: auto;
}
.llm__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.llm h2 {
  font-size: 16px;
  margin: 0 0 8px;
}
.llm__kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
}
.llm__kpi {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.llm__kpi span {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.llm__kpi strong {
  font-size: 19px;
}
.llm__kpi small {
  font-size: 12px;
  color: var(--color-text-soft);
}
.llm__bar {
  height: 10px;
  border-radius: 999px;
  background: var(--color-border);
  overflow: hidden;
}
.llm__bar span {
  display: block;
  height: 100%;
  background: var(--color-sage);
}
.llm__bar .llm__bar--warn {
  background: var(--color-seal);
}
.llm__days {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  height: 110px;
  padding: 6px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.llm__day {
  flex: 1;
  height: 100%;
  display: flex;
  align-items: flex-end;
  min-width: 2px;
}
.llm__day-bar {
  display: block;
  width: 100%;
  min-height: 2px;
  background: var(--color-seal);
  border-radius: 2px 2px 0 0;
}
.llm__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 14px;
}
.llm__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.llm__table th,
.llm__table td {
  text-align: left;
  padding: 6px 8px;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}
.llm__table th {
  color: var(--color-text-soft);
  font-size: 12px;
}
.llm__bad {
  color: var(--color-seal);
  font-weight: 600;
}
</style>
