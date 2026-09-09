<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import RichTextViewer from '@/components/shared/RichTextViewer.vue'

const route = useRoute()
const auth = useAuthStore()
const toast = useToast()

const event = ref(null)
const loading = ref(true)
const loadError = ref('')
const isRegistered = ref(false)
const registering = ref(false)

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const isFull = computed(() => event.value?.spots_remaining === 0)

async function loadEvent() {
  try {
    const { data } = await api.get(`/events/${route.params.slug}`)
    event.value = data
  } catch {
    loadError.value = 'Kegiatan tidak ditemukan.'
    loading.value = false
    return
  }

  if (auth.isAuthenticated) {
    try {
      const { data } = await api.get('/event-registrations/mine')
      isRegistered.value = data.some((r) => r.event.id === event.value.id)
    } catch {
      // non-fatal - tombol daftar tetap tampil, cuma status "sudah terdaftar" tidak terdeteksi
    }
  }
  loading.value = false
}

async function register() {
  registering.value = true
  try {
    await api.post(`/events/${event.value.id}/register`)
    isRegistered.value = true
    toast.push('Berhasil mendaftar kegiatan ini.', 'success')
    const { data } = await api.get(`/events/${route.params.slug}`)
    event.value = data
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mendaftar.')
  } finally {
    registering.value = false
  }
}

async function cancelRegistration() {
  if (!window.confirm('Batalkan pendaftaran kegiatan ini?')) return
  registering.value = true
  try {
    await api.delete(`/events/${event.value.id}/register`)
    isRegistered.value = false
    toast.push('Pendaftaran dibatalkan.', 'success')
    const { data } = await api.get(`/events/${route.params.slug}`)
    event.value = data
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membatalkan pendaftaran.')
  } finally {
    registering.value = false
  }
}

onMounted(loadEvent)
</script>

<template>
  <div class="kegiatan-detail">
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <article v-else-if="event">
      <RouterLink to="/kegiatan" class="kegiatan-detail__back">&larr; Semua Kegiatan</RouterLink>
      <img v-if="event.cover_image_url" :src="event.cover_image_url" :alt="event.title" class="kegiatan-detail__cover" />
      <h1>{{ event.title }}</h1>

      <div class="kegiatan-detail__meta">
        <span>📅 {{ formatDate(event.starts_at) }}<template v-if="event.ends_at"> - {{ formatDate(event.ends_at) }}</template></span>
        <span v-if="event.location || event.is_online">📍 {{ event.is_online ? 'Online' : event.location }}</span>
        <span v-if="event.spots_remaining !== null">🎟️ {{ event.spots_remaining }} kuota tersisa</span>
      </div>

      <div class="kegiatan-detail__action">
        <RouterLink v-if="!auth.isAuthenticated" :to="`/login?redirect=/kegiatan/${route.params.slug}`" class="btn btn--primary">
          Login untuk Mendaftar
        </RouterLink>
        <button
          v-else-if="isRegistered"
          type="button"
          class="btn"
          :disabled="registering"
          @click="cancelRegistration"
        >
          {{ registering ? 'Memproses...' : 'Batalkan Pendaftaran' }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn--primary"
          :disabled="registering || isFull"
          @click="register"
        >
          {{ isFull ? 'Kuota Penuh' : registering ? 'Memproses...' : 'Daftar Kegiatan' }}
        </button>
      </div>

      <RichTextViewer :html="event.description" />
    </article>
  </div>
</template>

<style scoped>
.kegiatan-detail {
  max-width: 760px;
}
.kegiatan-detail__back {
  display: inline-block;
  margin-bottom: 16px;
  color: var(--color-text-soft);
  text-decoration: none;
  font-size: 13px;
}
.kegiatan-detail__back:hover {
  color: var(--color-seal);
}
.kegiatan-detail__cover {
  width: 100%;
  max-height: 360px;
  object-fit: cover;
  border-radius: var(--radius-md);
  margin-bottom: 16px;
}
.kegiatan-detail h1 {
  font-family: var(--font-heading);
  font-size: 28px;
  margin-bottom: 12px;
}
.kegiatan-detail__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 13.5px;
  color: var(--color-text-soft);
  margin-bottom: 18px;
}
.kegiatan-detail__action {
  margin-bottom: 24px;
}
</style>
