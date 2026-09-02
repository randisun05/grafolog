<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const applications = ref([])
const meta = ref({ currentPage: 1, lastPage: 1, total: 0 })
const loading = ref(false)
const loadError = ref('')
const statusFilter = ref('pending')

const expandedId = ref(null)
const rejectNote = ref({})
const rejecting = ref({})
const approving = ref({})
const downloading = ref({})

async function load(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/grafolog-applications', {
      params: { status: statusFilter.value || undefined, page },
    })
    applications.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page, total: data.total }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat pengajuan.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

function onFilterChange() {
  load(1)
}

function goToPage(page) {
  if (page < 1 || page > meta.value.lastPage) return
  load(page)
}

function toggleExpand(application) {
  expandedId.value = expandedId.value === application.id ? null : application.id
}

async function viewDocument(application) {
  downloading.value = { ...downloading.value, [application.id]: true }
  try {
    const response = await api.get(`/admin/grafolog-applications/${application.id}/document`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(response.data)
    window.open(url, '_blank', 'noopener')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membuka dokumen.')
  } finally {
    downloading.value = { ...downloading.value, [application.id]: false }
  }
}

async function approve(application) {
  approving.value = { ...approving.value, [application.id]: true }
  try {
    await api.post(`/admin/grafolog-applications/${application.id}/approve`)
    toast.push(`Akun grafolog untuk ${application.name} berhasil dibuat.`, 'success')
    expandedId.value = null
    load(meta.value.currentPage)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyetujui pengajuan.')
  } finally {
    approving.value = { ...approving.value, [application.id]: false }
  }
}

async function reject(application) {
  rejecting.value = { ...rejecting.value, [application.id]: true }
  try {
    await api.post(`/admin/grafolog-applications/${application.id}/reject`, {
      review_note: rejectNote.value[application.id] || '',
    })
    toast.push(`Pengajuan ${application.name} ditolak.`, 'success')
    expandedId.value = null
    load(meta.value.currentPage)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menolak pengajuan.')
  } finally {
    rejecting.value = { ...rejecting.value, [application.id]: false }
  }
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => load(1))
</script>

<template>
  <div class="admin-grafolog-apps">
    <h1>Verifikasi Pendaftaran Grafolog</h1>
    <p class="admin-grafolog-apps__note">
      Calon grafolog mendaftar lewat biodata + bukti profesi (sertifikat/kartu anggota/dokumen lain). Setujui
      untuk langsung membuat akun grafolog aktif, atau tolak dengan catatan opsional.
    </p>

    <div class="admin-grafolog-apps__filters">
      <label>
        Status
        <select v-model="statusFilter" @change="onFilterChange">
          <option value="pending">Menunggu</option>
          <option value="approved">Disetujui</option>
          <option value="rejected">Ditolak</option>
          <option value="">Semua</option>
        </select>
      </label>
      <span class="admin-grafolog-apps__result-count">{{ meta.total }} pengajuan</span>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="applications.length === 0" class="admin-grafolog-apps__empty">Tidak ada pengajuan.</p>
    <div v-else class="admin-grafolog-apps__list">
      <div v-for="application in applications" :key="application.id" class="admin-grafolog-apps__item">
        <div class="admin-grafolog-apps__row" @click="toggleExpand(application)">
          <div class="admin-grafolog-apps__main">
            <strong>{{ application.name }}</strong>
            <span class="admin-grafolog-apps__email">{{ application.email }}</span>
          </div>
          <span class="admin-grafolog-apps__date">{{ formatDate(application.created_at) }}</span>
          <span class="badge" :class="`badge--status-${application.status}`">
            {{ { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' }[application.status] }}
          </span>
        </div>

        <div v-if="expandedId === application.id" class="admin-grafolog-apps__detail">
          <p v-if="application.phone"><strong>Telepon/WhatsApp:</strong> {{ application.phone }}</p>
          <p v-if="application.catatan"><strong>Pengalaman/Sertifikasi:</strong> {{ application.catatan }}</p>
          <p v-if="application.reviewer">
            <strong>Ditinjau oleh:</strong> {{ application.reviewer.name }} ({{ formatDate(application.reviewed_at) }})
          </p>
          <p v-if="application.review_note"><strong>Catatan peninjau:</strong> {{ application.review_note }}</p>

          <button
            type="button"
            class="btn"
            :disabled="downloading[application.id]"
            @click="viewDocument(application)"
          >
            {{ downloading[application.id] ? 'Membuka...' : 'Lihat Bukti Profesi' }}
          </button>

          <div v-if="application.status === 'pending'" class="admin-grafolog-apps__actions">
            <button
              type="button"
              class="btn btn--primary"
              :disabled="approving[application.id]"
              @click="approve(application)"
            >
              {{ approving[application.id] ? 'Menyetujui...' : 'Setujui & Buat Akun' }}
            </button>

            <div class="admin-grafolog-apps__reject">
              <input
                v-model="rejectNote[application.id]"
                type="text"
                placeholder="Alasan penolakan (opsional)"
              />
              <button
                type="button"
                class="btn"
                :disabled="rejecting[application.id]"
                @click="reject(application)"
              >
                {{ rejecting[application.id] ? 'Menolak...' : 'Tolak' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="meta.lastPage > 1" class="admin-grafolog-apps__pagination">
      <button type="button" class="btn" :disabled="meta.currentPage <= 1" @click="goToPage(meta.currentPage - 1)">
        &larr; Sebelumnya
      </button>
      <span>Halaman {{ meta.currentPage }} / {{ meta.lastPage }}</span>
      <button
        type="button"
        class="btn"
        :disabled="meta.currentPage >= meta.lastPage"
        @click="goToPage(meta.currentPage + 1)"
      >
        Berikutnya &rarr;
      </button>
    </div>
  </div>
</template>

<style scoped>
.admin-grafolog-apps {
  max-width: 800px;
}
.admin-grafolog-apps__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-grafolog-apps__filters {
  display: flex;
  align-items: end;
  gap: 14px;
  margin-bottom: 20px;
  padding: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.admin-grafolog-apps__filters label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
}
.admin-grafolog-apps__result-count {
  margin-left: auto;
  font-size: 12.5px;
  color: var(--color-text-soft);
  align-self: center;
}
.admin-grafolog-apps__empty {
  color: var(--color-text-soft);
}
.admin-grafolog-apps__list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.admin-grafolog-apps__item {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}
.admin-grafolog-apps__row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  cursor: pointer;
}
.admin-grafolog-apps__main {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.admin-grafolog-apps__email {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.admin-grafolog-apps__date {
  margin-left: auto;
  font-size: 12.5px;
  color: var(--color-text-soft);
  white-space: nowrap;
}
.badge {
  font-size: 11.5px;
  padding: 3px 8px;
  border-radius: 999px;
  white-space: nowrap;
}
.badge--status-pending {
  background: var(--color-gold);
  color: #3a2c00;
}
.badge--status-approved {
  background: var(--color-sage);
  color: #fff;
}
.badge--status-rejected {
  background: var(--color-seal);
  color: #fff;
}
.admin-grafolog-apps__detail {
  padding: 14px 16px 16px;
  border-top: 1px solid var(--color-border);
  background: var(--color-paper-alt);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.admin-grafolog-apps__detail p {
  font-size: 13.5px;
  color: var(--color-ink-soft);
}
.admin-grafolog-apps__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
}
.admin-grafolog-apps__reject {
  display: flex;
  gap: 8px;
  align-items: center;
}
.admin-grafolog-apps__reject input {
  margin: 0;
  min-width: 220px;
}
.admin-grafolog-apps__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  font-size: 13px;
}
</style>
