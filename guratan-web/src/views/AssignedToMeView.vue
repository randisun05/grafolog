<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import ServiceDeliveryCard from '@/components/commerce/ServiceDeliveryCard.vue'
import GrafologRequestsPanel from '@/components/order/GrafologRequestsPanel.vue'

const samples = ref([])
const revisions = ref([])
const services = ref([])
const loading = ref(true)
const error = ref('')

const pending = computed(() => samples.value.filter((s) => s.status !== 'completed'))

const openServices = computed(() =>
  services.value.filter((d) => !['selesai', 'dibatalkan'].includes(d.status)),
)
async function reloadServices() {
  services.value = (await api.get('/service-deliveries')).data
}

// Dua prosedur berbeda: penugasan dari biro (klien website/B2B, ada baris assignment) vs
// klien yang dibawa grafolog sendiri (didaftarkan lewat Portal Grafolog).
function sourceOf(sample) {
  if (sample.assignment) {
    return sample.project?.source === 'hr'
      ? { key: 'b2b', label: 'Penugasan biro · B2B' }
      : { key: 'biro', label: 'Penugasan biro · Website' }
  }
  return { key: 'mine', label: 'Klien saya' }
}
const filter = ref('all')
const visible = computed(() =>
  filter.value === 'all'
    ? pending.value
    : pending.value.filter((s) => (filter.value === 'mine') === !s.assignment),
)

onMounted(async () => {
  try {
    const [{ data }, { data: revisionData }, { data: serviceData }] = await Promise.all([
      api.get('/samples'),
      api.get('/reports', { params: { review_status: 'perlu_revisi' } }),
      api.get('/service-deliveries'),
    ])
    samples.value = data.data
    revisions.value = revisionData.data
    services.value = serviceData
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
    <GrafologRequestsPanel />
    <p class="assigned__note">
      Sample yang perlu Anda isi skornya - baik yang Anda buat sendiri lewat Portal Grafolog, maupun
      yang ditugaskan HR/Administrator.
    </p>

    <section v-if="revisions.length" class="assigned__revisions">
      <h2>Perlu Revisi dari Validator</h2>
      <ul class="assigned__list">
        <li v-for="r in revisions" :key="r.id" class="assigned__item">
          <div>
            <span class="assigned__client">Laporan #{{ r.id }} · {{ r.sample?.user?.name }}</span>
            <span class="assigned__meta">{{ r.review_note }}</span>
          </div>
          <RouterLink :to="{ name: 'report', params: { id: r.id } }" class="btn btn--primary"
            >Perbaiki</RouterLink
          >
        </li>
      </ul>
    </section>

    <section v-if="openServices.length" class="assigned__services">
      <h2>Layanan Pendukung Ditugaskan</h2>
      <ServiceDeliveryCard
        v-for="d in openServices"
        :key="d.id"
        :delivery="d"
        mode="grafolog"
        @updated="reloadServices"
      />
    </section>

    <h2 class="assigned__h2">Analisis Tulisan Tangan</h2>
    <SkeletonLoader v-if="loading" variant="list" label="Memuat..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="pending.length === 0" class="assigned__empty">
      Tidak ada sample yang menunggu diisi skornya.
    </p>
    <template v-else>
      <div class="assigned__filter" role="group" aria-label="Sumber klien">
        <button type="button" :aria-pressed="filter === 'all'" @click="filter = 'all'">
          Semua
        </button>
        <button type="button" :aria-pressed="filter === 'biro'" @click="filter = 'biro'">
          Penugasan biro
        </button>
        <button type="button" :aria-pressed="filter === 'mine'" @click="filter = 'mine'">
          Klien saya
        </button>
      </div>
      <ul class="assigned__list">
        <li v-for="sample in visible" :key="sample.id" class="assigned__item">
          <div>
            <span :class="['assigned__source', `assigned__source--${sourceOf(sample).key}`]">{{
              sourceOf(sample).label
            }}</span>
            <span class="assigned__client">{{
              sample.user?.name ?? `User #${sample.user_id}`
            }}</span>
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
    </template>
  </div>
</template>

<style scoped>
.assigned {
  max-width: 760px;
}
.assigned__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.assigned__revisions {
  margin-bottom: 24px;
}
.assigned__revisions h2 {
  font-size: 16px;
  color: var(--color-error);
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
.assigned__services {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 24px;
}
.assigned__services h2,
.assigned__h2 {
  font-size: 16px;
}
.assigned__filter {
  display: inline-flex;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  margin-bottom: 8px;
}
.assigned__filter button {
  border: 0;
  background: transparent;
  color: var(--color-text-soft);
  padding: 6px 12px;
  font-size: 13px;
  cursor: pointer;
}
.assigned__filter button[aria-pressed='true'] {
  background: var(--color-ink);
  color: var(--color-paper);
}
.assigned__source {
  display: inline-block;
  font-size: 11.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  margin-bottom: 4px;
  background: var(--color-gold-soft);
}
.assigned__source--mine {
  background: var(--color-sage-soft);
}
.assigned__source--b2b {
  background: var(--color-seal-soft);
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
