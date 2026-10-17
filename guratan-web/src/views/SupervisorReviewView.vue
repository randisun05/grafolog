<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

const tabs = [
  { value: 'menunggu_review', label: 'Menunggu Review' },
  { value: 'perlu_revisi', label: 'Dikembalikan ke Grafolog' },
  { value: 'disetujui', label: 'Disetujui' },
]
const status = ref('menunggu_review')
const reports = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/reviews', { params: { status: status.value } })
    reports.value = data.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat antrean review.'
  } finally {
    loading.value = false
  }
}

function grafologName(r) {
  return (
    r.sample?.assignment?.grafolog?.name ??
    (r.sample?.creator?.role === 'grafolog' ? r.sample.creator.name : '–')
  )
}

function formatDate(value) {
  return value
    ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
    : '–'
}

watch(status, load)
onMounted(load)
</script>

<template>
  <div class="reviews">
    <h1>Antrean Validasi Laporan</h1>
    <p class="reviews__note">
      Validasi bersifat opsional: hanya laporan yang grafolognya meminta validasi masuk ke sini, dan
      baru terlihat klien/HR setelah Anda setujui. Biaya validasi (token) masuk ke saldo Anda pada
      keputusan pertama - setuju maupun minta revisi.
    </p>

    <div class="reviews__tabs">
      <button
        v-for="t in tabs"
        :key="t.value"
        type="button"
        class="btn"
        :class="{ 'btn--primary': status === t.value }"
        @click="status = t.value"
      >
        {{ t.label }}
      </button>
    </div>

    <SkeletonLoader v-if="loading" variant="list" label="Memuat..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!reports.length" class="reviews__empty">Tidak ada laporan di tahap ini.</p>
    <ul v-else class="reviews__list">
      <li v-for="r in reports" :key="r.id" class="reviews__item">
        <div class="reviews__info">
          <strong>Laporan #{{ r.id }} · {{ r.sample?.user?.name }}</strong>
          <span class="reviews__meta">
            {{ r.tier }} · Grafolog: {{ grafologName(r) }} · Selesai
            {{ formatDate(r.generated_at) }}
            <template v-if="r.validation_fee"> · {{ r.validation_fee }} token</template>
          </span>
          <span v-if="r.review_note && status === 'perlu_revisi'" class="reviews__meta"
            >Catatan: {{ r.review_note }}</span
          >
        </div>
        <RouterLink :to="{ name: 'report', params: { id: r.id } }" class="btn btn--primary">
          {{ status === 'menunggu_review' ? 'Tinjau' : 'Lihat' }}
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.reviews {
  max-width: 820px;
}
.reviews__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 16px;
}
.reviews__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 18px;
}
.reviews__empty {
  color: var(--color-text-soft);
}
.reviews__list {
  list-style: none;
  padding: 0;
}
.reviews__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px 16px;
  padding: 14px 0;
  border-bottom: 1px solid var(--color-border);
}
.reviews__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.reviews__meta {
  font-size: 12.5px;
  color: var(--color-text-soft);
  overflow-wrap: anywhere;
}
</style>
