<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

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
      await api.get('/admin/grafolog-applications', { params: { status: status.value } })
    ).data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat pendaftar.'
  } finally {
    loading.value = false
  }
}

async function openCertificate(g) {
  try {
    const { data } = await api.get(`/admin/grafolog-applications/${g.id}/certificate`, {
      responseType: 'blob',
    })
    window.open(URL.createObjectURL(data), '_blank', 'noopener')
  } catch {
    toast.push('Gagal membuka sertifikat.')
  }
}

async function decide(g, decision) {
  if (decision === 'reject' && !notes.value[g.id]?.trim()) {
    toast.push('Isi alasan penolakan dulu.')
    return
  }
  deciding.value = g.id
  try {
    await api.post(`/admin/grafolog-applications/${g.id}/decision`, {
      decision,
      note: notes.value[g.id] || null,
    })
    toast.push(decision === 'approve' ? `${g.name} diverifikasi.` : `${g.name} ditolak.`)
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

const fmt = (d) =>
  d
    ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '-'

onMounted(load)
</script>

<template>
  <div class="admin-gr">
    <h1>Verifikasi Grafolog</h1>
    <p class="admin-gr__intro">
      Pendaftar mandiri tidak bisa mendaftarkan klien, menerima penugasan, atau mengisi worksheet
      sampai diverifikasi. Periksa sertifikat sebelum menyetujui.
    </p>

    <div class="admin-gr__tabs" role="tablist">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        role="tab"
        :aria-selected="status === t.key"
        :class="['admin-gr__tab', { 'admin-gr__tab--active': status === t.key }]"
        @click="selectTab(t.key)"
      >
        {{ t.label }}
      </button>
    </div>

    <SkeletonLoader v-if="loading" variant="list" :rows="3" label="Memuat pendaftar..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="!list.length" class="admin-gr__empty">Tidak ada pendaftar di tahap ini.</p>
    <ul v-else class="admin-gr__list">
      <li v-for="g in list" :key="g.id" class="admin-gr__item">
        <div class="admin-gr__info">
          <strong>{{ g.name }}</strong>
          <span>{{ g.email }} · {{ g.phone || '-' }}</span>
          <span
            >Sertifikat: {{ g.certificate_number || '-' }} · Daftar {{ fmt(g.created_at) }}</span
          >
          <span v-if="g.grafolog_review_note" class="admin-gr__note"
            >Catatan: {{ g.grafolog_review_note }}</span
          >
        </div>
        <div class="admin-gr__actions">
          <button v-if="g.has_certificate" type="button" class="btn" @click="openCertificate(g)">
            Lihat sertifikat
          </button>
          <template v-if="status === 'pending'">
            <textarea
              v-model="notes[g.id]"
              :id="`gr-note-${g.id}`"
              rows="2"
              placeholder="Catatan (wajib bila ditolak)"
            ></textarea>
            <div class="admin-gr__buttons">
              <button
                type="button"
                class="btn btn--primary"
                :disabled="deciding === g.id"
                @click="decide(g, 'approve')"
              >
                Setujui
              </button>
              <button
                type="button"
                class="btn"
                :disabled="deciding === g.id"
                @click="decide(g, 'reject')"
              >
                Tolak
              </button>
            </div>
          </template>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.admin-gr {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.admin-gr__intro,
.admin-gr__empty {
  color: var(--color-text-soft);
  margin: 0;
}
.admin-gr__tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.admin-gr__tab {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  border-radius: 999px;
  padding: 6px 14px;
  cursor: pointer;
}
.admin-gr__tab--active {
  background: var(--color-ink);
  border-color: var(--color-ink);
  color: var(--color-paper);
}
.admin-gr__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.admin-gr__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.admin-gr__info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
  min-width: 0;
}
.admin-gr__info span {
  color: var(--color-text-soft);
}
.admin-gr__note {
  font-style: italic;
}
.admin-gr__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 240px;
}
.admin-gr__buttons {
  display: flex;
  gap: 8px;
}
</style>
