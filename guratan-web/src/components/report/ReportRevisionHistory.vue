<script setup>
import { ref } from 'vue'
import api from '@/lib/api'
import ReportDocument from './ReportDocument.vue'

const props = defineProps({
  reportId: { type: [Number, String], required: true },
})

const open = ref(false)
const loading = ref(false)
const loaded = ref(false)
const revisions = ref([])
const openRevisionId = ref(null)
const revisionData = ref({})

const jenisLabel = {
  koreksi_skor: 'Koreksi Skor',
  edit_manual: 'Edit Narasi Manual',
  edit_narasi_terpadu: 'Edit Narasi Terpadu',
}

async function toggle() {
  open.value = !open.value
  if (open.value && !loaded.value) {
    loading.value = true
    try {
      const { data } = await api.get(`/reports/${props.reportId}/revisions`)
      revisions.value = data
      loaded.value = true
    } finally {
      loading.value = false
    }
  }
}

async function viewRevision(revision) {
  if (openRevisionId.value === revision.id) {
    openRevisionId.value = null
    return
  }
  openRevisionId.value = revision.id
  if (!revisionData.value[revision.id]) {
    const { data } = await api.get(`/reports/${props.reportId}/revisions/${revision.id}`)
    revisionData.value[revision.id] = data.data
  }
}
</script>

<template>
  <div class="revision-history">
    <button type="button" class="btn" @click="toggle">
      {{ open ? 'Sembunyikan Riwayat Perubahan' : 'Lihat Riwayat Perubahan' }}
    </button>

    <div v-if="open" class="revision-history__body">
      <p v-if="loading">Memuat riwayat...</p>
      <p v-else-if="revisions.length === 0" class="revision-history__empty">
        Belum ada perubahan - laporan ini masih versi asli.
      </p>
      <ul v-else class="revision-history__list">
        <li v-for="r in revisions" :key="r.id" class="revision-history__item">
          <button type="button" class="revision-history__toggle" @click="viewRevision(r)">
            <span class="revision-history__jenis">{{ jenisLabel[r.jenis] ?? r.jenis }}</span>
            <span class="revision-history__meta">
              {{ r.actor?.name ?? 'Tidak diketahui' }} &middot; {{ new Date(r.created_at).toLocaleString('id-ID') }}
            </span>
          </button>
          <p v-if="r.catatan" class="revision-history__catatan">"{{ r.catatan }}"</p>

          <div v-if="openRevisionId === r.id" class="revision-history__snapshot">
            <p class="revision-history__snapshot-label">Isi laporan pada versi ini (sebelum diubah):</p>
            <template v-if="revisionData[r.id]">
              <p v-if="r.jenis === 'edit_narasi_terpadu'" class="revision-history__narasi-snapshot">
                {{ revisionData[r.id].narasi_terpadu || '(kosong)' }}
              </p>
              <ReportDocument v-else :data="revisionData[r.id]" />
            </template>
            <p v-else>Memuat...</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.revision-history {
  margin: 16px 0;
}
.revision-history__body {
  margin-top: 12px;
}
.revision-history__empty {
  color: var(--color-text-soft);
  font-size: 13px;
}
.revision-history__list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.revision-history__item {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  background: var(--color-surface);
}
.revision-history__toggle {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 4px 12px;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  color: var(--color-ink);
}
.revision-history__jenis {
  font-weight: 600;
  font-size: 13px;
}
.revision-history__meta {
  font-size: 12px;
  color: var(--color-text-soft);
}
.revision-history__catatan {
  font-size: 12.5px;
  font-style: italic;
  color: var(--color-text-soft);
  margin-top: 4px;
}
.revision-history__snapshot {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed var(--color-border);
}
.revision-history__snapshot-label {
  font-size: 12px;
  color: var(--color-text-soft);
  margin-bottom: 8px;
}
.revision-history__narasi-snapshot {
  white-space: pre-line;
  font-size: 13.5px;
  line-height: 1.6;
}
</style>
