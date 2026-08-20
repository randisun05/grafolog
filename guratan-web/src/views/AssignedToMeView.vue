<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'

const samples = ref([])
const loading = ref(true)
const error = ref('')

const pending = computed(() => samples.value.filter((s) => s.status !== 'completed'))

onMounted(async () => {
  try {
    const { data } = await api.get('/samples')
    samples.value = data.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat daftar sample.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="assigned">
    <h1>Ditugaskan ke Saya</h1>
    <p class="assigned__note">
      Sample yang perlu Anda isi skornya - baik yang Anda buat sendiri lewat Portal Grafolog,
      maupun yang ditugaskan HR/Administrator.
    </p>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="pending.length === 0" class="assigned__empty">
      Tidak ada sample yang menunggu diisi skornya.
    </p>
    <ul v-else class="assigned__list">
      <li v-for="sample in pending" :key="sample.id" class="assigned__item">
        <div>
          <span class="assigned__client">{{ sample.user?.name ?? `User #${sample.user_id}` }}</span>
          <span class="assigned__meta">{{ sample.tier }} · {{ sample.user?.email }}</span>
        </div>
        <RouterLink
          :to="{ name: 'portal-grafolog', query: { sampleId: sample.id } }"
          class="btn btn--primary"
        >
          Isi Skor
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.assigned {
  max-width: 640px;
}
.assigned__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.assigned__empty {
  color: var(--color-text-soft);
}
.assigned__list {
  list-style: none;
  padding: 0;
}
.assigned__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px 16px;
  padding: 14px 4px;
  border-bottom: 1px solid var(--color-border);
}
.assigned__client {
  display: block;
  font-weight: 600;
}
.assigned__meta {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-top: 2px;
}
</style>
