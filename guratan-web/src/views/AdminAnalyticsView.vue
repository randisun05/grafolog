<script setup>
import { ref, computed, onMounted } from 'vue'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'
import api from '@/lib/api'
import { chartColors, categoricalPalette, baseChartOptions } from '@/lib/chartTheme'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Tooltip, Legend)

const toast = useToast()

// Dashboard Analitik (Fase 3, 2026-09-03) - lihat guratan-api/CLAUDE.md
// "Laporan/Rekap admin — Fase 3". 3 section, tiap section fetch endpoint
// analytics-nya sendiri (bukan 1 endpoint besar) supaya loading/error
// state independen per section.
const presets = [
  { label: '7 Hari', days: 7 },
  { label: '30 Hari', days: 30 },
  { label: '90 Hari', days: 90 },
  { label: 'Tahun Ini', days: null },
]

function isoDate(d) {
  return d.toISOString().slice(0, 10)
}

const rangeFrom = ref('')
const rangeTo = ref('')

function applyPreset(preset) {
  const today = new Date()
  rangeTo.value = isoDate(today)
  if (preset.days === null) {
    rangeFrom.value = isoDate(new Date(today.getFullYear(), 0, 1))
  } else {
    const from = new Date(today)
    from.setDate(from.getDate() - preset.days)
    rangeFrom.value = isoDate(from)
  }
  loadAll()
}

function loadAll() {
  loadRevenue()
  loadProductUsage()
  loadUserGrowth()
}

// --- Revenue ---
const revenue = ref(null)
const revenueLoading = ref(false)
const revenueError = ref('')

async function loadRevenue() {
  revenueLoading.value = true
  revenueError.value = ''
  try {
    const { data } = await api.get('/admin/analytics/revenue', {
      params: { from: rangeFrom.value, to: rangeTo.value },
    })
    revenue.value = data
  } catch (e) {
    revenueError.value = e.response?.data?.message ?? 'Gagal memuat data revenue.'
    toast.push(revenueError.value)
  } finally {
    revenueLoading.value = false
  }
}

const revenueChartData = computed(() => {
  if (!revenue.value) return null
  const c = chartColors()
  const labels = revenue.value.series.map((s) => s.period)

  return {
    labels,
    datasets: [
      { label: 'Laporan', data: revenue.value.series.map((s) => s.report_revenue), borderColor: c.seal, backgroundColor: c.seal, tension: 0.3 },
      { label: 'Token', data: revenue.value.series.map((s) => s.token_revenue), borderColor: c.gold, backgroundColor: c.gold, tension: 0.3 },
      { label: 'Total', data: revenue.value.series.map((s) => s.total), borderColor: c.sage, backgroundColor: c.sage, tension: 0.3 },
    ],
  }
})

// --- Analisa Produk ---
const productUsage = ref(null)
const productUsageLoading = ref(false)
const productUsageError = ref('')

async function loadProductUsage() {
  productUsageLoading.value = true
  productUsageError.value = ''
  try {
    const { data } = await api.get('/admin/analytics/product-usage', {
      params: { from: rangeFrom.value, to: rangeTo.value },
    })
    productUsage.value = data
  } catch (e) {
    productUsageError.value = e.response?.data?.message ?? 'Gagal memuat data analisa produk.'
    toast.push(productUsageError.value)
  } finally {
    productUsageLoading.value = false
  }
}

const tierChartData = computed(() => {
  if (!productUsage.value) return null
  const palette = categoricalPalette()

  return {
    labels: Object.keys(productUsage.value.by_tier),
    datasets: [{ label: 'Jumlah Sample', data: Object.values(productUsage.value.by_tier), backgroundColor: palette }],
  }
})

const statusChartData = computed(() => {
  if (!productUsage.value) return null
  const palette = categoricalPalette()

  return {
    labels: Object.keys(productUsage.value.by_status),
    datasets: [{ data: Object.values(productUsage.value.by_status), backgroundColor: palette }],
  }
})

// --- Pertumbuhan Pengguna ---
const userGrowth = ref(null)
const userGrowthLoading = ref(false)
const userGrowthError = ref('')

async function loadUserGrowth() {
  userGrowthLoading.value = true
  userGrowthError.value = ''
  try {
    const { data } = await api.get('/admin/analytics/user-growth', {
      params: { from: rangeFrom.value, to: rangeTo.value },
    })
    userGrowth.value = data
  } catch (e) {
    userGrowthError.value = e.response?.data?.message ?? 'Gagal memuat data pertumbuhan pengguna.'
    toast.push(userGrowthError.value)
  } finally {
    userGrowthLoading.value = false
  }
}

const userGrowthChartData = computed(() => {
  if (!userGrowth.value) return null
  const palette = categoricalPalette()
  const roles = Object.keys(userGrowth.value.by_role)
  const labels = userGrowth.value.series.map((s) => s.period)

  return {
    labels,
    datasets: roles.map((role, i) => ({
      label: role,
      data: userGrowth.value.series.map((s) => s.by_role[role] ?? 0),
      borderColor: palette[i % palette.length],
      backgroundColor: palette[i % palette.length],
      tension: 0.3,
    })),
  }
})

function formatRupiah(amount) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount ?? 0)
}

onMounted(() => applyPreset(presets[2]))
</script>

<template>
  <div class="admin-analytics">
    <h1>Dashboard Analitik</h1>
    <p class="admin-analytics__note">Ringkasan revenue, penggunaan produk, dan pertumbuhan pengguna.</p>

    <div class="admin-analytics__range">
      <button v-for="preset in presets" :key="preset.label" type="button" class="btn" @click="applyPreset(preset)">
        {{ preset.label }}
      </button>
      <label>
        Dari
        <input v-model="rangeFrom" type="date" @change="loadAll" />
      </label>
      <label>
        Sampai
        <input v-model="rangeTo" type="date" @change="loadAll" />
      </label>
    </div>

    <!-- Revenue -->
    <section class="admin-analytics__section">
      <h2>Revenue</h2>
      <LoadingSpinner v-if="revenueLoading" label="Memuat..." />
      <p v-else-if="revenueError" class="error">{{ revenueError }}</p>
      <template v-else-if="revenue">
        <div class="admin-analytics__tiles">
          <div class="admin-analytics__tile">
            <span class="admin-analytics__tile-label">Total Revenue</span>
            <strong>{{ formatRupiah(revenue.total_revenue) }}</strong>
          </div>
          <div class="admin-analytics__tile">
            <span class="admin-analytics__tile-label">Revenue Laporan</span>
            <strong>{{ formatRupiah(revenue.report_revenue) }}</strong>
          </div>
          <div class="admin-analytics__tile">
            <span class="admin-analytics__tile-label">Revenue Token</span>
            <strong>{{ formatRupiah(revenue.token_revenue) }}</strong>
          </div>
        </div>
        <div class="admin-analytics__chart">
          <Line v-if="revenueChartData" :data="revenueChartData" :options="baseChartOptions()" />
        </div>
      </template>
    </section>

    <!-- Analisa Produk -->
    <section class="admin-analytics__section">
      <h2>Analisa Produk</h2>
      <LoadingSpinner v-if="productUsageLoading" label="Memuat..." />
      <p v-else-if="productUsageError" class="error">{{ productUsageError }}</p>
      <template v-else-if="productUsage">
        <p class="admin-analytics__total">{{ productUsage.total_samples }} sample dalam rentang ini.</p>
        <div class="admin-analytics__chart-row">
          <div class="admin-analytics__chart admin-analytics__chart--half">
            <p class="admin-analytics__chart-title">Per Tier</p>
            <Bar v-if="tierChartData" :data="tierChartData" :options="baseChartOptions()" />
          </div>
          <div class="admin-analytics__chart admin-analytics__chart--half">
            <p class="admin-analytics__chart-title">Per Status</p>
            <Doughnut v-if="statusChartData" :data="statusChartData" :options="baseChartOptions()" />
          </div>
        </div>
      </template>
    </section>

    <!-- Pertumbuhan Pengguna -->
    <section class="admin-analytics__section">
      <h2>Pertumbuhan Pengguna</h2>
      <LoadingSpinner v-if="userGrowthLoading" label="Memuat..." />
      <p v-else-if="userGrowthError" class="error">{{ userGrowthError }}</p>
      <template v-else-if="userGrowth">
        <p class="admin-analytics__total">{{ userGrowth.total_new_users }} akun baru dalam rentang ini.</p>
        <div class="admin-analytics__chart">
          <Line v-if="userGrowthChartData" :data="userGrowthChartData" :options="baseChartOptions()" />
        </div>
      </template>
    </section>
  </div>
</template>

<style scoped>
.admin-analytics {
  max-width: 1000px;
}
.admin-analytics__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-analytics__range {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  gap: 10px;
  margin-bottom: 24px;
  padding: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.admin-analytics__range label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
}
.admin-analytics__section {
  margin-bottom: 32px;
  padding: 20px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.admin-analytics__section h2 {
  font-size: 16px;
  margin-bottom: 14px;
}
.admin-analytics__total {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.admin-analytics__tiles {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 20px;
}
.admin-analytics__tile {
  flex: 1;
  min-width: 160px;
  padding: 14px;
  background: var(--color-paper-alt);
  border-radius: var(--radius-sm);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.admin-analytics__tile-label {
  font-size: 12px;
  color: var(--color-text-soft);
}
.admin-analytics__tile strong {
  font-size: 18px;
  font-family: var(--font-heading);
}
.admin-analytics__chart {
  height: 280px;
  position: relative;
}
.admin-analytics__chart-row {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}
.admin-analytics__chart--half {
  flex: 1;
  min-width: 280px;
}
.admin-analytics__chart-title {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-bottom: 8px;
}
</style>
