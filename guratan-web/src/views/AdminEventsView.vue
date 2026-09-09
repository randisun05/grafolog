<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import { downloadBlob } from '@/lib/downloadBlob'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import RichTextEditor from '@/components/shared/RichTextEditor.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const events = ref([])
const meta = ref({ currentPage: 1, lastPage: 1 })
const loading = ref(true)
const loadError = ref('')

async function loadEvents(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/events', { params: { page } })
    events.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat kegiatan.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > meta.value.lastPage) return
  loadEvents(page)
}

function emptyCreateForm() {
  return {
    title: '', description: '', location: '', is_online: false,
    starts_at: '', ends_at: '', capacity: '', status: 'draft',
  }
}

const createForm = ref(emptyCreateForm())
const createCoverFile = ref(null)
const createErrors = ref({})
const creating = ref(false)

function onCreateCoverChange(e) {
  createCoverFile.value = e.target.files?.[0] ?? null
}

function buildFormData(form, coverFile, methodOverride) {
  const formData = new FormData()
  formData.append('title', form.title)
  formData.append('description', form.description)
  formData.append('location', form.location ?? '')
  formData.append('is_online', form.is_online ? '1' : '0')
  formData.append('starts_at', form.starts_at)
  if (form.ends_at) formData.append('ends_at', form.ends_at)
  if (form.capacity) formData.append('capacity', form.capacity)
  formData.append('status', form.status)
  if (coverFile) formData.append('cover_image', coverFile)
  if (methodOverride) formData.append('_method', methodOverride)
  return formData
}

async function createEvent() {
  creating.value = true
  createErrors.value = {}
  try {
    await api.post('/admin/events', buildFormData(createForm.value, createCoverFile.value))
    toast.push(`Kegiatan "${createForm.value.title}" berhasil dibuat.`, 'success')
    createForm.value = emptyCreateForm()
    createCoverFile.value = null
    await loadEvents(meta.value.currentPage)
  } catch (e) {
    createErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat kegiatan.')
  } finally {
    creating.value = false
  }
}

// --- edit + peserta - expand-row ---
const editingId = ref(null)
const editForm = ref({})
const editCoverFile = ref(null)
const editErrors = ref({})
const savingId = ref(null)
const participants = ref([])
const participantsLoading = ref(false)

function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function startEdit(event) {
  editingId.value = event.id
  editErrors.value = {}
  editCoverFile.value = null
  editForm.value = {
    title: event.title,
    description: event.description,
    location: event.location ?? '',
    is_online: event.is_online,
    starts_at: toLocalInput(event.starts_at),
    ends_at: toLocalInput(event.ends_at),
    capacity: event.capacity ?? '',
    status: event.status,
  }
  participants.value = []
  participantsLoading.value = true
  try {
    const { data } = await api.get(`/admin/events/${event.id}/registrations`)
    participants.value = data
  } catch {
    toast.push('Gagal memuat daftar peserta.')
  } finally {
    participantsLoading.value = false
  }
}

function cancelEdit() {
  editingId.value = null
}

function onEditCoverChange(e) {
  editCoverFile.value = e.target.files?.[0] ?? null
}

async function saveEvent(event) {
  savingId.value = event.id
  editErrors.value = {}
  try {
    const { data } = await api.post(
      `/admin/events/${event.id}`,
      buildFormData(editForm.value, editCoverFile.value, 'PATCH'),
    )
    Object.assign(event, data)
    toast.push(`Kegiatan "${event.title}" berhasil diperbarui.`, 'success')
    editingId.value = null
  } catch (e) {
    editErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal memperbarui kegiatan.')
  } finally {
    savingId.value = null
  }
}

async function exportParticipants(event) {
  try {
    const response = await api.get(`/admin/events/${event.id}/registrations/export`, { responseType: 'blob' })
    downloadBlob(response.data, `peserta-${event.slug}.csv`)
  } catch {
    toast.push('Gagal mengekspor daftar peserta.')
  }
}

function formatDate(iso) {
  if (!iso) return '-'
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusLabel = { draft: 'Draft', published: 'Diterbitkan', cancelled: 'Dibatalkan' }

onMounted(() => loadEvents(1))
</script>

<template>
  <div class="admin-events">
    <h1>Kelola Kegiatan</h1>
    <p class="admin-events__note">
      Kegiatan yang tampil publik di <code>/kegiatan</code> begitu statusnya "Diterbitkan". Pengguna mendaftar
      langsung di Guratan (perlu login) - daftar peserta dan export CSV ada di panel "Ubah" tiap kegiatan.
    </p>

    <div class="admin-events__create">
      <h2>Buat Kegiatan Baru</h2>
      <form @submit.prevent="createEvent">
        <label>
          Judul
          <input v-model="createForm.title" type="text" required />
        </label>
        <p v-if="createErrors.title" class="error">{{ createErrors.title[0] }}</p>

        <label>Deskripsi</label>
        <RichTextEditor v-model="createForm.description" />
        <p v-if="createErrors.description" class="error">{{ createErrors.description[0] }}</p>

        <div class="admin-events__row">
          <label>
            Lokasi
            <input v-model="createForm.location" type="text" placeholder="mis. Aula A / Online" />
          </label>
          <label class="admin-events__checkbox">
            <input v-model="createForm.is_online" type="checkbox" />
            Kegiatan online
          </label>
        </div>

        <div class="admin-events__row">
          <label>
            Mulai
            <input v-model="createForm.starts_at" type="datetime-local" required />
          </label>
          <label>
            Selesai (opsional)
            <input v-model="createForm.ends_at" type="datetime-local" />
          </label>
        </div>
        <p v-if="createErrors.ends_at" class="error">{{ createErrors.ends_at[0] }}</p>

        <label>
          Kuota Peserta (opsional, kosongkan untuk tanpa batas)
          <input v-model="createForm.capacity" type="number" min="1" />
        </label>

        <label>
          Gambar Sampul
          <input type="file" accept="image/*" @change="onCreateCoverChange" />
        </label>

        <label>
          Status
          <select v-model="createForm.status">
            <option value="draft">Draft</option>
            <option value="published">Diterbitkan</option>
            <option value="cancelled">Dibatalkan</option>
          </select>
        </label>

        <button type="submit" class="btn btn--primary" :disabled="creating">
          {{ creating ? 'Menyimpan...' : 'Simpan Kegiatan' }}
        </button>
      </form>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <table v-else class="admin-events__table">
      <thead>
        <tr>
          <th>Judul</th>
          <th>Mulai</th>
          <th>Status</th>
          <th>Kuota</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="event in events" :key="event.id">
          <tr>
            <td>{{ event.title }}</td>
            <td>{{ formatDate(event.starts_at) }}</td>
            <td>
              <span class="badge" :class="event.status === 'published' ? 'badge--active' : 'badge--inactive'">
                {{ statusLabel[event.status] }}
              </span>
            </td>
            <td>{{ event.spots_remaining === null ? 'Tanpa batas' : `${event.spots_remaining} tersisa` }}</td>
            <td>
              <button type="button" class="btn" @click="editingId === event.id ? cancelEdit() : startEdit(event)">
                {{ editingId === event.id ? 'Batal' : 'Ubah' }}
              </button>
            </td>
          </tr>
          <tr v-if="editingId === event.id" class="admin-events__edit-row">
            <td colspan="5">
              <div class="admin-events__edit-form">
                <label>
                  Judul
                  <input v-model="editForm.title" type="text" />
                </label>

                <label>Deskripsi</label>
                <RichTextEditor v-model="editForm.description" />

                <div class="admin-events__row">
                  <label>
                    Lokasi
                    <input v-model="editForm.location" type="text" />
                  </label>
                  <label class="admin-events__checkbox">
                    <input v-model="editForm.is_online" type="checkbox" />
                    Kegiatan online
                  </label>
                </div>

                <div class="admin-events__row">
                  <label>
                    Mulai
                    <input v-model="editForm.starts_at" type="datetime-local" />
                  </label>
                  <label>
                    Selesai
                    <input v-model="editForm.ends_at" type="datetime-local" />
                  </label>
                </div>

                <label>
                  Kuota Peserta
                  <input v-model="editForm.capacity" type="number" min="1" />
                </label>

                <label>
                  Ganti Gambar Sampul (opsional)
                  <input type="file" accept="image/*" @change="onEditCoverChange" />
                </label>

                <label>
                  Status
                  <select v-model="editForm.status">
                    <option value="draft">Draft</option>
                    <option value="published">Diterbitkan</option>
                    <option value="cancelled">Dibatalkan</option>
                  </select>
                </label>

                <button
                  type="button"
                  class="btn btn--primary"
                  :disabled="savingId === event.id"
                  @click="saveEvent(event)"
                >
                  {{ savingId === event.id ? 'Menyimpan...' : 'Simpan' }}
                </button>

                <div class="admin-events__participants">
                  <h3>Peserta Terdaftar</h3>
                  <LoadingSpinner v-if="participantsLoading" label="Memuat peserta..." />
                  <template v-else>
                    <p v-if="participants.length === 0" class="admin-events__empty">Belum ada peserta.</p>
                    <table v-else class="admin-events__participants-table">
                      <thead>
                        <tr>
                          <th>Nama</th>
                          <th>Email</th>
                          <th>Nomor WA</th>
                          <th>Terdaftar</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="p in participants" :key="p.id">
                          <td>{{ p.user?.name }}</td>
                          <td>{{ p.user?.email }}</td>
                          <td>{{ p.user?.phone ?? '-' }}</td>
                          <td>{{ formatDate(p.registered_at) }}</td>
                        </tr>
                      </tbody>
                    </table>
                    <button type="button" class="btn" @click="exportParticipants(event)">Export CSV</button>
                  </template>
                </div>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <div v-if="meta.lastPage > 1" class="admin-events__pagination">
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
.admin-events {
  max-width: 900px;
}
.admin-events__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-events__create {
  margin-bottom: 24px;
  padding: 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-events__create h2 {
  font-size: 15px;
  margin-bottom: 12px;
}
.admin-events__create label,
.admin-events__edit-form label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
.admin-events__row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.admin-events__row label {
  flex: 1;
  min-width: 180px;
}
.admin-events__checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
}
.admin-events__checkbox input {
  margin: 0;
  width: auto;
}
.admin-events__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-events__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-events__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
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
.admin-events__edit-row {
  background: var(--color-paper-alt);
}
.admin-events__edit-form {
  padding: 14px 4px;
  max-width: 620px;
}
.admin-events__participants {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border);
}
.admin-events__participants h3 {
  font-size: 14px;
  margin-bottom: 10px;
}
.admin-events__empty {
  color: var(--color-text-soft);
  font-size: 13px;
}
.admin-events__participants-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
  margin-bottom: 10px;
}
.admin-events__participants-table th {
  text-align: left;
  padding: 6px 8px;
  color: var(--color-text-soft);
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
}
.admin-events__participants-table td {
  padding: 6px 8px;
  border-bottom: 1px solid var(--color-border);
}
.admin-events__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  font-size: 13px;
}
</style>
