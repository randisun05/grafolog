<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminGrafologView from '@/views/AdminGrafologView.vue'
import AdminCompaniesView from '@/views/AdminCompaniesView.vue'

/** Satu pintu verifikasi: pendaftar grafolog (sertifikat) dan perusahaan B2B. */
const route = useRoute()
const router = useRouter()
const tab = ref(route.query.tab === 'perusahaan' ? 'perusahaan' : 'grafolog')

function go(key) {
  tab.value = key
  router.replace({ query: { tab: key } })
}
</script>

<template>
  <div class="vf">
    <div class="vf__tabs" role="tablist">
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'grafolog'"
        :class="['vf__tab', { 'vf__tab--on': tab === 'grafolog' }]"
        @click="go('grafolog')"
      >
        Grafolog
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'perusahaan'"
        :class="['vf__tab', { 'vf__tab--on': tab === 'perusahaan' }]"
        @click="go('perusahaan')"
      >
        Perusahaan
      </button>
    </div>
    <AdminGrafologView v-if="tab === 'grafolog'" />
    <AdminCompaniesView v-else />
  </div>
</template>

<style scoped>
.vf {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.vf__tabs {
  display: flex;
  gap: 6px;
}
.vf__tab {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  border-radius: 999px;
  padding: 7px 18px;
  cursor: pointer;
  font-weight: 600;
}
.vf__tab--on {
  background: var(--color-seal);
  border-color: var(--color-seal);
  color: #fff;
}
</style>
