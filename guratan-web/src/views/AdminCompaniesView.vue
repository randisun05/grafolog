<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'
import { formatTanggal } from '@/lib/format'

const toast = useToast()
const tabs = [
  { key: 'pending', label: 'Menunggu' },
  { key: 'verified', label: 'Terverifikasi' },
  { key: 'rejected', label: 'Ditolak' },
]
const status = ref('pending')
const list = ref([])
const loading = ref(true)
const loadError = ref('')
const notes = ref({})
const deciding = ref(null)

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    list.value = (
      await api.get('/admin/company-applications', { params: { status: status.value } })
    ).data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat perusahaan.'
  } finally {
    loading.value = false
  }
}

async function decide(c, decision) {
  if (decision === 'reject' && !notes.value[c.id]?.trim()) {
    toast.push('Isi alasan penolakan dulu.')
    return
  }
  deciding.value = c.id
  try {
    await api.post(`/admin/company-applications/${c.id}/decision`, {
      decision,
      note: notes.value[c.id] || null,
    })
    toast.push(decision === 'approve' ? `${c.name} diverifikasi.` : `${c.name} ditolak.`)
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan keputusan.')
  } finally {
    deciding.value = null
  }
}

function selectTab(k) {
  status.value = k
  load()
}

onMounted(load)
</script>

<template>
  <div class="admin-co">
    <h1>Verifikasi Perusahaan</h1>
    <p class="admin-co__intro">
      Perusahaan yang mendaftar mandiri belum bisa menambah kandidat atau memesan asesmen sampai
      diverifikasi. Pastikan perusahaan & penanggung jawab HR-nya nyata sebelum menyetujui.
    </p>

    <div class="admin-co__tabs" role="tablist">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        role="tab"
        :aria-selected="status === t.key"
        :class="['admin-co__tab', { 'admin-co__tab--active': status === t.key }]"
        @click="selectTab(t.key)"
      >
        {{ t.label }}
      </button>
    </div>

    <SkeletonLoader v-if="loading" variant="list" :rows="3" label="Memuat perusahaan..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="!list.length" class="admin-co__empty">Tidak ada perusahaan di tahap ini.</p>
    <ul v-else class="admin-co__list">
      <li v-for="c in list" :key="c.id" class="admin-co__item">
        <div class="admin-co__info">
          <strong>{{ c.name }}</strong>
          <span
            >{{ c.industry || '-' }} · {{ c.employee_range || '-' }} karyawan ·
            {{ c.phone || '-' }}</span
          >
          <span>{{ c.address || '-' }}</span>
          <a v-if="c.website" :href="c.website" target="_blank" rel="noopener">{{ c.website }}</a>
          <span v-for="u in c.users" :key="u.id"
            >HR: {{ u.name }} · {{ u.email }} · {{ u.phone || '-' }}</span
          >
          <span>Daftar {{ formatTanggal(c.created_at) }}</span>
          <span v-if="c.review_note" class="admin-co__note">Catatan: {{ c.review_note }}</span>
        </div>
        <div v-if="status === 'pending'" class="admin-co__actions">
          <textarea
            :id="`co-note-${c.id}`"
            v-model="notes[c.id]"
            rows="2"
            placeholder="Catatan (wajib bila ditolak)"
          ></textarea>
          <div class="admin-co__buttons">
            <button
              type="button"
              class="btn btn--primary"
              :disabled="deciding === c.id"
              @click="decide(c, 'approve')"
            >
              Setujui
            </button>
            <button
              type="button"
              class="btn"
              :disabled="deciding === c.id"
              @click="decide(c, 'reject')"
            >
              Tolak
            </button>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.admin-co {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.admin-co__intro,
.admin-co__empty {
  color: var(--color-text-soft);
  margin: 0;
}
.admin-co__tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.admin-co__tab {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  border-radius: 999px;
  padding: 6px 14px;
  cursor: pointer;
}
.admin-co__tab--active {
  background: var(--color-ink);
  border-color: var(--color-ink);
  color: var(--color-paper);
}
.admin-co__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.admin-co__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.admin-co__info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
  min-width: 0;
}
.admin-co__info span {
  color: var(--color-text-soft);
}
.admin-co__note {
  font-style: italic;
}
.admin-co__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 240px;
}
.admin-co__buttons {
  display: flex;
  gap: 8px;
}
</style>
