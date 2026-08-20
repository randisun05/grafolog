<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'

const dashboard = ref(null)
const loading = ref(true)
const error = ref('')

const announcements = ref([])
const dismissedIds = ref([])
const visibleAnnouncements = computed(() =>
  announcements.value.filter((a) => !dismissedIds.value.includes(a.id)),
)

function dismissAnnouncement(id) {
  dismissedIds.value = [...dismissedIds.value, id]
}

const statusLabel = {
  completed: 'Selesai',
  generating: 'Diproses',
  failed: 'Gagal',
}

function formatValue(kpi) {
  if (kpi.value === null) return '–'
  if (kpi.key === 'avg_turnaround_days') return `${kpi.value} hari`
  return kpi.value
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

onMounted(async () => {
  try {
    const { data } = await api.get('/dashboard')
    dashboard.value = data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat dashboard.'
  } finally {
    loading.value = false
  }

  try {
    const { data } = await api.get('/announcements')
    announcements.value = data
  } catch {
    // Pengumuman gagal dimuat bukan hal fatal - dashboard tetap tampil tanpa banner.
  }
})
</script>

<template>
  <div class="dashboard">
    <h1>Dashboard</h1>

    <div v-if="visibleAnnouncements.length" class="dashboard__announcements">
      <div v-for="a in visibleAnnouncements" :key="a.id" class="dashboard__announcement">
        <div>
          <strong>{{ a.title }}</strong>
          <p>{{ a.body }}</p>
        </div>
        <button type="button" class="dashboard__announcement-close" aria-label="Tutup" @click="dismissAnnouncement(a.id)">
          &times;
        </button>
      </div>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat dashboard..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else>
      <div class="dashboard__kpi">
        <div v-for="kpi in dashboard.kpi" :key="kpi.key" class="dashboard__kpi-card">
          <span class="dashboard__kpi-label">{{ kpi.label }}</span>
          <span class="dashboard__kpi-value">{{ formatValue(kpi) }}</span>
        </div>
      </div>

      <section class="dashboard__activity">
        <h2>Aktivitas Terbaru</h2>
        <p v-if="dashboard.activity.length === 0" class="dashboard__empty">Belum ada aktivitas.</p>
        <ul v-else class="dashboard__activity-list">
          <li v-for="item in dashboard.activity" :key="item.id" class="dashboard__activity-item">
            <RouterLink :to="`/reports/${item.id}`">{{ item.label }}</RouterLink>
            <span class="dashboard__activity-meta">
              <span :class="['badge', `badge--${item.status}`]">{{ statusLabel[item.status] ?? item.status }}</span>
              <span>{{ formatDate(item.occurred_at) }}</span>
            </span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.dashboard {
  max-width: 100%;
}
.dashboard__announcements {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 16px;
}
.dashboard__announcement {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  background: var(--color-sage-soft);
  border: 1px solid var(--color-sage);
  border-radius: var(--radius-md);
}
.dashboard__announcement strong {
  display: block;
  font-family: var(--font-heading);
  font-size: 14px;
  margin-bottom: 2px;
}
.dashboard__announcement p {
  font-size: 13px;
  color: var(--color-ink-soft);
  margin: 0;
}
.dashboard__announcement-close {
  flex: 0 0 auto;
  border: none;
  background: transparent;
  color: var(--color-ink-soft);
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  padding: 0 4px;
}
.dashboard__announcement-close:hover {
  color: var(--color-ink);
}
.dashboard__kpi {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin: 20px 0 32px;
}
.dashboard__kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.dashboard__kpi-label {
  font-size: 13px;
  color: var(--color-text-soft);
}
.dashboard__kpi-value {
  font-family: var(--font-heading);
  font-size: 28px;
  font-weight: 600;
  color: var(--color-heading);
}
.dashboard__activity h2 {
  font-size: 18px;
  margin-bottom: 12px;
}
.dashboard__empty {
  color: var(--color-text-soft);
}
.dashboard__activity-list {
  list-style: none;
  padding: 0;
}
.dashboard__activity-item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 4px 12px;
  padding: 14px 4px;
  border-bottom: 1px solid var(--color-border);
}
.dashboard__activity-item a {
  text-decoration: none;
  color: var(--color-ink);
  font-weight: 500;
}
.dashboard__activity-item a:hover {
  color: var(--color-seal);
}
.dashboard__activity-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--color-text-soft);
  white-space: nowrap;
}
.badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.badge--completed {
  background: var(--color-sage-soft);
  color: var(--color-sage);
}
.badge--generating {
  background: var(--color-seal-soft);
  color: var(--color-seal-dark);
}
.badge--failed {
  background: var(--color-seal-soft);
  color: var(--color-seal-dark);
}
</style>
