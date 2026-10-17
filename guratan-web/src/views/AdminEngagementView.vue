<script setup>
import { ref } from 'vue'
import AdminSelfCheckPanel from '@/components/engagement/AdminSelfCheckPanel.vue'
import AdminAffiliatePanel from '@/components/engagement/AdminAffiliatePanel.vue'
import AdminArticlesPanel from '@/components/engagement/AdminArticlesPanel.vue'

/** Admin Fase E: cek tulisan (lead magnet), produk mitra/afiliasi, artikel. */
const tabs = [
  { key: 'cek', label: 'Cek Tulisan & Lead' },
  { key: 'afiliasi', label: 'Produk Mitra' },
  { key: 'artikel', label: 'Artikel' },
]
const tab = ref('cek')
</script>

<template>
  <div class="aeng">
    <h1>Konten & Engagement</h1>
    <div class="aeng__tabs" role="tablist">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        role="tab"
        :aria-selected="tab === t.key"
        :class="['aeng__tab', { 'aeng__tab--active': tab === t.key }]"
        @click="tab = t.key"
      >
        {{ t.label }}
      </button>
    </div>
    <AdminSelfCheckPanel v-if="tab === 'cek'" />
    <AdminAffiliatePanel v-else-if="tab === 'afiliasi'" />
    <AdminArticlesPanel v-else />
  </div>
</template>

<style scoped>
.aeng {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.aeng__tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.aeng__tab {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  border-radius: 999px;
  padding: 6px 14px;
  cursor: pointer;
}
.aeng__tab--active {
  background: var(--color-ink);
  border-color: var(--color-ink);
  color: var(--color-paper);
}
</style>
