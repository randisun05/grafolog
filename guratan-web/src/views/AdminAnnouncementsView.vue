<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const roleOptions = [
  { value: 'user', label: 'Klien' },
  { value: 'grafolog', label: 'Grafolog' },
  { value: 'hr', label: 'HR' },
  { value: 'administrator', label: 'Administrator' },
  { value: 'supervisor', label: 'Supervisor' },
]

const announcements = ref([])
const loading = ref(true)
const loadError = ref('')

const form = ref({ title: '', body: '', roles: [], starts_at: '', ends_at: '' })
const errors = ref({})
const submitting = ref(false)
const togglingId = ref(null)

async function loadAnnouncements() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/announcements')
    announcements.value = data.data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat pengumuman.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  submitting.value = true
  errors.value = {}
  try {
    await api.post('/admin/announcements', {
      title: form.value.title,
      body: form.value.body,
      target_roles: form.value.roles.length ? form.value.roles : null,
      starts_at: form.value.starts_at || null,
      ends_at: form.value.ends_at || null,
    })
    toast.push('Pengumuman berhasil dibuat.', 'success')
    form.value = { title: '', body: '', roles: [], starts_at: '', ends_at: '' }
    await loadAnnouncements()
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat pengumuman.')
  } finally {
    submitting.value = false
  }
}

async function toggleActive(announcement) {
  togglingId.value = announcement.id
  try {
    await api.put(`/admin/announcements/${announcement.id}`, { is_active: !announcement.is_active })
    toast.push(`Pengumuman ${announcement.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`, 'success')
    await loadAnnouncements()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah status pengumuman.')
  } finally {
    togglingId.value = null
  }
}

function roleLabels(targetRoles) {
  if (!targetRoles) return 'Semua role'
  return targetRoles
    .map((r) => roleOptions.find((o) => o.value === r)?.label ?? r)
    .join(', ')
}

onMounted(loadAnnouncements)
</script>

<template>
  <div class="admin-announcements">
    <h1>Kelola Pengumuman</h1>
    <p class="admin-announcements__note">
      Pengumuman tampil sebagai banner di Dashboard — bisa ditutup pengguna per sesi, muncul lagi
      di kunjungan berikutnya selama masih aktif.
    </p>

    <form class="admin-announcements__form" @submit.prevent="submit">
      <label>
        Judul
        <input v-model="form.title" type="text" required />
      </label>
      <p v-if="errors.title" class="error">{{ errors.title[0] }}</p>

      <label>
        Isi
        <textarea v-model="form.body" rows="3" required></textarea>
      </label>
      <p v-if="errors.body" class="error">{{ errors.body[0] }}</p>

      <label class="admin-announcements__roles">
        Tampilkan untuk role (kosongkan = semua)
        <span>
          <label v-for="opt in roleOptions" :key="opt.value">
            <input v-model="form.roles" type="checkbox" :value="opt.value" /> {{ opt.label }}
          </label>
        </span>
      </label>

      <div class="admin-announcements__row">
        <label>
          Mulai tampil (opsional)
          <input v-model="form.starts_at" type="date" />
        </label>
        <label>
          Berhenti tampil (opsional)
          <input v-model="form.ends_at" type="date" />
        </label>
      </div>

      <button type="submit" class="btn btn--primary" :disabled="submitting">
        {{ submitting ? 'Membuat...' : 'Buat Pengumuman' }}
      </button>
    </form>

    <h2 class="admin-announcements__list-title">Semua Pengumuman</h2>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="announcements.length === 0" class="admin-announcements__empty">Belum ada pengumuman.</p>
    <ul v-else class="admin-announcements__list">
      <li v-for="a in announcements" :key="a.id" class="admin-announcements__item">
        <div>
          <strong>{{ a.title }}</strong>
          <p class="admin-announcements__meta">{{ roleLabels(a.target_roles) }}</p>
        </div>
        <div class="admin-announcements__item-actions">
          <span class="badge" :class="a.is_active ? 'badge--active' : 'badge--inactive'">
            {{ a.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
          <button type="button" class="btn" :disabled="togglingId === a.id" @click="toggleActive(a)">
            {{ a.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.admin-announcements {
  max-width: 720px;
}
.admin-announcements__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-announcements__form {
  padding: 20px;
  margin-bottom: 32px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-announcements__form label {
  display: block;
  margin-bottom: 14px;
  font-size: 14px;
  color: var(--color-text-soft);
}
.admin-announcements__roles span {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 6px;
  font-size: 13px;
}
.admin-announcements__roles span label {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  font-size: 13px;
}
.admin-announcements__roles input[type='checkbox'] {
  width: auto;
  margin: 0;
}
.admin-announcements__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.admin-announcements__list-title {
  font-size: 16px;
  margin-bottom: 10px;
}
.admin-announcements__empty {
  color: var(--color-text-soft);
}
.admin-announcements__list {
  list-style: none;
  padding: 0;
}
.admin-announcements__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px 16px;
  padding: 14px 4px;
  border-bottom: 1px solid var(--color-border);
}
.admin-announcements__meta {
  font-size: 12px;
  color: var(--color-text-soft);
  margin: 2px 0 0;
}
.admin-announcements__item-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 600;
}
.badge--active {
  background: var(--color-sage-soft);
  color: var(--color-sage);
}
.badge--inactive {
  background: var(--color-seal-soft);
  color: var(--color-seal);
}
</style>
