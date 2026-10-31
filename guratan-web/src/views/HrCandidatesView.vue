<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import HrCandidatesPanel from '@/components/hr/HrCandidatesPanel.vue'
import HrPositionsPanel from '@/components/hr/HrPositionsPanel.vue'
import HrOrdersPanel from '@/components/hr/HrOrdersPanel.vue'
import HrAnalysisPanel from '@/components/hr/HrAnalysisPanel.vue'
import HrPotensiPanel from '@/components/hr/HrPotensiPanel.vue'
import HrChatPanel from '@/components/hr/HrChatPanel.vue'

/**
 * Ruang kerja rekrutmen HR (Fase C, 2026-10-17): kandidat -> pesan asesmen (HR memilih
 * grafolog) -> unggah scan -> bandingkan hasil per posisi.
 */
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const tabs = [
  { key: 'kandidat', label: 'Kandidat' },
  { key: 'posisi', label: 'Posisi' },
  { key: 'pesanan', label: 'Pesan Asesmen' },
  { key: 'analisis', label: 'Analisis' },
  { key: 'potensi', label: 'Potensi' },
  { key: 'chat', label: 'Asisten Data' },
]
const tab = ref(tabs.some((t) => t.key === route.query.tab) ? route.query.tab : 'kandidat')
const positions = ref([])
const preselected = ref([])

function go(key) {
  tab.value = key
  router.replace({ query: { ...route.query, tab: key } })
}

async function loadPositions() {
  try {
    positions.value = (await api.get('/hr/positions')).data
  } catch {
    positions.value = []
  }
}

function orderFor(ids) {
  preselected.value = [...ids]
  go('pesanan')
}

onMounted(loadPositions)
</script>

<template>
  <div class="hr">
    <h1>Rekrutmen</h1>
    <p class="hr__company">{{ auth.user?.company?.name }}</p>

    <div class="hr__tabs" role="tablist">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        role="tab"
        :aria-selected="tab === t.key"
        :class="['hr__tab', { 'hr__tab--active': tab === t.key }]"
        @click="go(t.key)"
      >
        {{ t.label }}
      </button>
    </div>

    <HrCandidatesPanel v-if="tab === 'kandidat'" :positions="positions" @order="orderFor" />
    <HrPositionsPanel v-else-if="tab === 'posisi'" @changed="loadPositions" />
    <HrOrdersPanel
      v-else-if="tab === 'pesanan'"
      :preselected="preselected"
      @changed="go('kandidat')"
    />
    <HrAnalysisPanel v-else-if="tab === 'analisis'" :positions="positions" />
    <HrPotensiPanel v-else-if="tab === 'potensi'" />
    <HrChatPanel v-else />
  </div>
</template>

<style scoped>
.hr {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.hr h1 {
  margin-bottom: 0;
}
.hr__company {
  color: var(--color-text-soft);
  margin: -8px 0 0;
}
.hr__tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 10px;
}
.hr__tab {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  border-radius: 999px;
  padding: 6px 14px;
  cursor: pointer;
}
.hr__tab--active {
  background: var(--color-ink);
  border-color: var(--color-ink);
  color: var(--color-paper);
}
</style>
