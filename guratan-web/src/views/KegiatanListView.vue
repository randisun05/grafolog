<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'

const tab = ref('upcoming')
const events = ref([])
const loading = ref(true)
const loadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/events', { params: { when: tab.value } })
    events.value = data.data
  } catch {
    loadError.value = 'Gagal memuat kegiatan.'
  } finally {
    loading.value = false
  }
}

function switchTab(value) {
  tab.value = value
  load()
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
</script>

<template>
  <div class="kegiatan-list">
    <h1>Kegiatan</h1>
    <p class="kegiatan-list__note">Workshop, webinar, dan kegiatan lain seputar grafologi.</p>

    <div class="kegiatan-list__tabs">
      <button type="button" :class="{ active: tab === 'upcoming' }" @click="switchTab('upcoming')">Mendatang</button>
      <button type="button" :class="{ active: tab === 'past' }" @click="switchTab('past')">Selesai</button>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="events.length === 0" class="kegiatan-list__empty">Belum ada kegiatan.</p>
    <div v-else class="kegiatan-list__grid">
      <RouterLink v-for="event in events" :key="event.id" :to="`/kegiatan/${event.slug}`" class="kegiatan-card">
        <img v-if="event.cover_image_url" :src="event.cover_image_url" :alt="event.title" />
        <div class="kegiatan-card__body">
          <span class="kegiatan-card__date">{{ formatDate(event.starts_at) }}</span>
          <h2>{{ event.title }}</h2>
          <p v-if="event.location">{{ event.is_online ? 'Online' : event.location }}</p>
          <span v-if="event.spots_remaining !== null" class="kegiatan-card__spots">
            {{ event.spots_remaining }} kuota tersisa
          </span>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.kegiatan-list {
  max-width: 1000px;
}
.kegiatan-list__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.kegiatan-list__tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
}
.kegiatan-list__tabs button {
  padding: 7px 16px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 13px;
  cursor: pointer;
}
.kegiatan-list__tabs button.active {
  background: var(--color-seal);
  color: #fff;
  border-color: var(--color-seal);
}
.kegiatan-list__empty {
  color: var(--color-text-soft);
}
.kegiatan-list__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}
.kegiatan-card {
  display: block;
  text-decoration: none;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  box-shadow: var(--shadow-card);
  transition: transform 0.15s ease;
}
.kegiatan-card:hover {
  transform: translateY(-2px);
}
.kegiatan-card img {
  width: 100%;
  height: 150px;
  object-fit: cover;
  display: block;
}
.kegiatan-card__body {
  padding: 14px 16px 18px;
}
.kegiatan-card__date {
  font-size: 11.5px;
  color: var(--color-text-soft);
}
.kegiatan-card__body h2 {
  font-family: var(--font-heading);
  font-size: 17px;
  margin: 6px 0 8px;
}
.kegiatan-card__body p {
  font-size: 13.5px;
  color: var(--color-text-soft);
}
.kegiatan-card__spots {
  display: inline-block;
  margin-top: 8px;
  font-size: 12px;
  color: var(--color-seal);
}
</style>
