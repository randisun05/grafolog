<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const logs = ref([])
const meta = ref({ currentPage: 1, lastPage: 1, total: 0 })
const loading = ref(false)
const loadError = ref('')

const channelFilter = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')

const channelLabel = { email: 'Email', whatsapp: 'WhatsApp' }
const statusLabel = { sent: 'Terkirim', failed: 'Gagal', skipped: 'Dilewati' }
const typeLabel = { laporan_selesai: 'Laporan Selesai', reset_password: 'Reset Kata Sandi' }

async function load(page = meta.value.currentPage) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/notification-logs', {
      params: {
        channel: channelFilter.value || undefined,
        status: statusFilter.value || undefined,
        type: typeFilter.value || undefined,
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
        page,
      },
    })
    logs.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page, total: data.total }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat log notifikasi.'
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

function formatDate(iso) {
  return new Date(iso).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => load(1))
</script>

<template>
  <div class="admin-notification-logs">
    <h1>Log Notifikasi</h1>
    <p class="admin-notification-logs__note">
      Riwayat setiap email/WhatsApp yang dicoba dikirim sistem (laporan selesai, reset kata sandi)
      beserta statusnya — <strong>Terkirim</strong> (berhasil dikirim ke provider), <strong>Gagal</strong>
      (dicoba tapi provider menolak/error), atau <strong>Dilewati</strong> (memang belum dicoba —
      nomor WhatsApp kosong atau kredensial Fonnte belum diisi). Hanya baca.
    </p>

    <div class="admin-notification-logs__filters">
      <label>
        Channel
        <select v-model="channelFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option value="email">Email</option>
          <option value="whatsapp">WhatsApp</option>
        </select>
      </label>
      <label>
        Status
        <select v-model="statusFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option value="sent">Terkirim</option>
          <option value="failed">Gagal</option>
          <option value="skipped">Dilewati</option>
        </select>
      </label>
      <label>
        Tipe
        <select v-model="typeFilter" @change="onFilterChange">
          <option value="">Semua</option>
          <option value="laporan_selesai">Laporan Selesai</option>
          <option value="reset_password">Reset Kata Sandi</option>
        </select>
      </label>
      <label>
        Dari tanggal
        <input v-model="fromFilter" type="date" @change="onFilterChange" />
      </label>
      <label>
        Sampai tanggal
        <input v-model="toFilter" type="date" @change="onFilterChange" />
      </label>
      <span class="admin-notification-logs__result-count">{{ meta.total }} entri</span>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="logs.length === 0" class="admin-notification-logs__empty">Tidak ada log yang cocok.</p>
    <table v-else class="admin-notification-logs__table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Channel</th>
          <th>Tipe</th>
          <th>Penerima</th>
          <th>User</th>
          <th>Status</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="log in logs" :key="log.id">
          <td class="admin-notification-logs__time">{{ formatDate(log.created_at) }}</td>
          <td>
            <span class="badge" :class="log.channel === 'email' ? 'badge--channel-email' : 'badge--channel-whatsapp'">
              {{ channelLabel[log.channel] ?? log.channel }}
            </span>
          </td>
          <td>{{ typeLabel[log.type] ?? log.type }}</td>
          <td class="admin-notification-logs__recipient">{{ log.recipient }}</td>
          <td>{{ log.user ? `${log.user.name} (${log.user.email})` : '-' }}</td>
          <td>
            <span
              class="badge"
              :class="{
                'badge--active': log.status === 'sent',
                'badge--inactive': log.status === 'skipped',
                'badge--danger': log.status === 'failed',
              }"
            >
              {{ statusLabel[log.status] ?? log.status }}
            </span>
          </td>
          <td class="admin-notification-logs__error">{{ log.error_message ?? '-' }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="meta.lastPage > 1" class="admin-notification-logs__pagination">
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
.admin-notification-logs {
  max-width: 1100px;
}
.admin-notification-logs__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-notification-logs__filters {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  gap: 14px;
  margin-bottom: 20px;
  padding: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.admin-notification-logs__filters label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
}
.admin-notification-logs__result-count {
  margin-left: auto;
  font-size: 12.5px;
  color: var(--color-text-soft);
  align-self: center;
}
.admin-notification-logs__empty {
  color: var(--color-text-soft);
}
.admin-notification-logs__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-notification-logs__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-notification-logs__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-notification-logs__time {
  white-space: nowrap;
  color: var(--color-text-soft);
}
.admin-notification-logs__recipient {
  font-family: monospace;
  font-size: 12.5px;
}
.admin-notification-logs__error {
  color: var(--color-text-soft);
  font-size: 12.5px;
  max-width: 280px;
}
.admin-notification-logs__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  font-size: 13px;
}
.badge {
  font-size: 11.5px;
  padding: 3px 8px;
  border-radius: 999px;
  white-space: nowrap;
}
.badge--active {
  background: var(--color-sage);
  color: #fff;
}
.badge--inactive {
  background: var(--color-ink-faint);
  color: var(--color-ink-soft);
}
.badge--danger {
  background: var(--color-seal);
  color: #fff;
}
.badge--channel-email {
  background: var(--color-gold-soft);
  color: var(--color-gold);
}
.badge--channel-whatsapp {
  background: var(--color-sage-soft);
  color: var(--color-sage);
}
</style>
