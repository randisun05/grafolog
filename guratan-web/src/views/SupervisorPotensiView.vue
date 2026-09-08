<script setup>
import { ref, computed, onMounted } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import api from '@/lib/api'
import { chartColors, baseChartOptions } from '@/lib/chartTheme'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)

const toast = useToast()

const topikOptions = ref([])
const selectedTopikIds = ref([])

const potensi = ref(null)
const loading = ref(false)
const loadError = ref('')

async function loadTopikOptions() {
  try {
    const { data } = await api.get('/topik')
    topikOptions.value = data
  } catch {
    // Non-fatal - dashboard tetap berguna tanpa filter kategori kalau
    // fetch ini gagal, sama filosofi fallback-non-fatal di tempat lain.
  }
}

async function loadPotensi() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/supervisor/potensi', {
      params: { topik_ids: selectedTopikIds.value },
    })
    potensi.value = data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat dashboard potensi.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

function toggleTopik(id) {
  const idx = selectedTopikIds.value.indexOf(id)
  if (idx === -1) {
    selectedTopikIds.value.push(id)
  } else {
    selectedTopikIds.value.splice(idx, 1)
  }
  loadPotensi()
}

function chartDataFor(sindrom) {
  const c = chartColors()
  return {
    labels: sindrom.aspek.map((a) => a.nama),
    datasets: [
      {
        label: 'Rata-rata Skor',
        data: sindrom.aspek.map((a) => a.avg_skor),
        backgroundColor: c.seal,
      },
    ],
  }
}

const chartOptions = computed(() => ({
  ...baseChartOptions(),
  scales: {
    ...baseChartOptions().scales,
    y: { ...baseChartOptions().scales.y, min: 0, max: 10 },
  },
}))

onMounted(() => {
  loadTopikOptions()
  loadPotensi()
})
</script>

<template>
  <div class="supervisor-potensi">
    <h1>Dashboard Potensi</h1>
    <p class="supervisor-potensi__note">
      Ringkasan reflektif rata-rata skor & distribusi tingkat seluruh kandidat perusahaan - insight untuk
      membantu keputusan SDM, bukan alat diagnosis final atau skor kelayakan.
    </p>

    <div v-if="topikOptions.length > 0" class="supervisor-potensi__topik">
      <span class="supervisor-potensi__topik-label">Filter Kategori (Topik)</span>
      <label v-for="t in topikOptions" :key="t.id" class="supervisor-potensi__topik-checkbox">
        <input
          type="checkbox"
          :checked="selectedTopikIds.includes(t.id)"
          @change="toggleTopik(t.id)"
        />
        {{ t.nama }}
      </label>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else-if="potensi">
      <div class="supervisor-potensi__tiles">
        <div class="supervisor-potensi__tile">
          <span class="supervisor-potensi__tile-label">Kandidat</span>
          <strong>{{ potensi.candidate_count }}</strong>
        </div>
        <div class="supervisor-potensi__tile">
          <span class="supervisor-potensi__tile-label">Laporan</span>
          <strong>{{ potensi.report_count }}</strong>
        </div>
      </div>

      <p v-if="potensi.sindrom.length === 0" class="supervisor-potensi__empty">
        Belum ada laporan selesai yang cocok dengan filter ini.
      </p>

      <section v-for="sindrom in potensi.sindrom" :key="sindrom.id" class="supervisor-potensi__section">
        <h2>{{ sindrom.kode_romawi }} - {{ sindrom.nama }}</h2>
        <div class="supervisor-potensi__chart">
          <Bar :data="chartDataFor(sindrom)" :options="chartOptions" />
        </div>
        <table class="supervisor-potensi__table">
          <thead>
            <tr>
              <th>Aspek</th>
              <th>Rata-rata Skor</th>
              <th>Distribusi Tingkat</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="aspek in sindrom.aspek" :key="aspek.kode">
              <td>{{ aspek.nama }}</td>
              <td>{{ aspek.avg_skor ?? '-' }}</td>
              <td>
                Rendah {{ aspek.narasi_level_distribution.low }} - Sedang
                {{ aspek.narasi_level_distribution.medium }} - Tinggi {{ aspek.narasi_level_distribution.high }} -
                Sangat Tinggi {{ aspek.narasi_level_distribution.very_high }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<style scoped>
.supervisor-potensi {
  max-width: 1000px;
}
.supervisor-potensi__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.supervisor-potensi__topik {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding: 14px 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.supervisor-potensi__topik-label {
  font-size: 12.5px;
  color: var(--color-text-soft);
  font-weight: 600;
}
.supervisor-potensi__topik-checkbox {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}
.supervisor-potensi__tiles {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 24px;
}
.supervisor-potensi__tile {
  flex: 1;
  min-width: 160px;
  padding: 14px;
  background: var(--color-paper-alt);
  border-radius: var(--radius-sm);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.supervisor-potensi__tile-label {
  font-size: 12px;
  color: var(--color-text-soft);
}
.supervisor-potensi__tile strong {
  font-size: 18px;
  font-family: var(--font-heading);
}
.supervisor-potensi__empty {
  color: var(--color-text-soft);
}
.supervisor-potensi__section {
  margin-bottom: 28px;
  padding: 20px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.supervisor-potensi__section h2 {
  font-size: 15px;
  margin-bottom: 14px;
}
.supervisor-potensi__chart {
  height: 240px;
  position: relative;
  margin-bottom: 16px;
}
.supervisor-potensi__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 13px;
}
.supervisor-potensi__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.supervisor-potensi__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
</style>
