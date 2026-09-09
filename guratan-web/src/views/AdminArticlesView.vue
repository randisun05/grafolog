<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import RichTextEditor from '@/components/shared/RichTextEditor.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const articles = ref([])
const meta = ref({ currentPage: 1, lastPage: 1 })
const loading = ref(true)
const loadError = ref('')

async function loadArticles(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/articles', { params: { page } })
    articles.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat artikel.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > meta.value.lastPage) return
  loadArticles(page)
}

// --- buat artikel baru ---
const createForm = ref({ title: '', excerpt: '', body: '', status: 'draft' })
const createCoverFile = ref(null)
const createErrors = ref({})
const creating = ref(false)

function onCreateCoverChange(e) {
  createCoverFile.value = e.target.files?.[0] ?? null
}

function buildFormData(form, coverFile, methodOverride) {
  const formData = new FormData()
  formData.append('title', form.title)
  formData.append('excerpt', form.excerpt ?? '')
  formData.append('body', form.body)
  formData.append('status', form.status)
  if (coverFile) formData.append('cover_image', coverFile)
  if (methodOverride) formData.append('_method', methodOverride)
  return formData
}

async function createArticle() {
  creating.value = true
  createErrors.value = {}
  try {
    await api.post('/admin/articles', buildFormData(createForm.value, createCoverFile.value))
    toast.push(`Artikel "${createForm.value.title}" berhasil dibuat.`, 'success')
    createForm.value = { title: '', excerpt: '', body: '', status: 'draft' }
    createCoverFile.value = null
    await loadArticles(meta.value.currentPage)
  } catch (e) {
    createErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat artikel.')
  } finally {
    creating.value = false
  }
}

// --- edit artikel - expand-row, pola sama AdminProductsView.vue ---
const editingId = ref(null)
const editForm = ref({})
const editCoverFile = ref(null)
const editErrors = ref({})
const savingId = ref(null)

function startEdit(article) {
  editingId.value = article.id
  editErrors.value = {}
  editCoverFile.value = null
  editForm.value = {
    title: article.title,
    excerpt: article.excerpt ?? '',
    body: article.body,
    status: article.status,
  }
}

function cancelEdit() {
  editingId.value = null
}

function onEditCoverChange(e) {
  editCoverFile.value = e.target.files?.[0] ?? null
}

async function saveArticle(article) {
  savingId.value = article.id
  editErrors.value = {}
  try {
    const { data } = await api.post(
      `/admin/articles/${article.id}`,
      buildFormData(editForm.value, editCoverFile.value, 'PATCH'),
    )
    Object.assign(article, data)
    toast.push(`Artikel "${article.title}" berhasil diperbarui.`, 'success')
    editingId.value = null
  } catch (e) {
    editErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal memperbarui artikel.')
  } finally {
    savingId.value = null
  }
}

const deletingId = ref(null)

async function deleteArticle(article) {
  if (!window.confirm(`Hapus artikel "${article.title}"? Tindakan ini tidak bisa dibatalkan.`)) return
  deletingId.value = article.id
  try {
    await api.delete(`/admin/articles/${article.id}`)
    toast.push(`Artikel "${article.title}" dihapus.`, 'success')
    await loadArticles(meta.value.currentPage)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus artikel.')
  } finally {
    deletingId.value = null
  }
}

function formatDate(iso) {
  if (!iso) return '-'
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => loadArticles(1))
</script>

<template>
  <div class="admin-articles">
    <h1>Kelola Artikel</h1>
    <p class="admin-articles__note">
      Artikel/berita yang tampil publik di <code>/artikel</code> begitu statusnya "Diterbitkan". Draft cuma
      terlihat di halaman ini.
    </p>

    <div class="admin-articles__create">
      <h2>Tulis Artikel Baru</h2>
      <form @submit.prevent="createArticle">
        <label>
          Judul
          <input v-model="createForm.title" type="text" required />
        </label>
        <p v-if="createErrors.title" class="error">{{ createErrors.title[0] }}</p>

        <label>
          Ringkasan (tampil di kartu daftar artikel)
          <textarea v-model="createForm.excerpt" rows="2"></textarea>
        </label>
        <p v-if="createErrors.excerpt" class="error">{{ createErrors.excerpt[0] }}</p>

        <label>Isi Artikel</label>
        <RichTextEditor v-model="createForm.body" />
        <p v-if="createErrors.body" class="error">{{ createErrors.body[0] }}</p>

        <label>
          Gambar Sampul
          <input type="file" accept="image/*" @change="onCreateCoverChange" />
        </label>
        <p v-if="createErrors.cover_image" class="error">{{ createErrors.cover_image[0] }}</p>

        <label>
          Status
          <select v-model="createForm.status">
            <option value="draft">Draft</option>
            <option value="published">Diterbitkan</option>
          </select>
        </label>

        <button type="submit" class="btn btn--primary" :disabled="creating">
          {{ creating ? 'Menyimpan...' : 'Simpan Artikel' }}
        </button>
      </form>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <table v-else class="admin-articles__table">
      <thead>
        <tr>
          <th>Judul</th>
          <th>Status</th>
          <th>Diterbitkan</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="article in articles" :key="article.id">
          <tr>
            <td>{{ article.title }}</td>
            <td>
              <span class="badge" :class="article.status === 'published' ? 'badge--active' : 'badge--inactive'">
                {{ article.status === 'published' ? 'Diterbitkan' : 'Draft' }}
              </span>
            </td>
            <td>{{ formatDate(article.published_at) }}</td>
            <td class="admin-articles__actions">
              <button type="button" class="btn" @click="editingId === article.id ? cancelEdit() : startEdit(article)">
                {{ editingId === article.id ? 'Batal' : 'Ubah' }}
              </button>
              <button
                type="button"
                class="btn"
                :disabled="deletingId === article.id"
                @click="deleteArticle(article)"
              >
                Hapus
              </button>
            </td>
          </tr>
          <tr v-if="editingId === article.id" class="admin-articles__edit-row">
            <td colspan="4">
              <div class="admin-articles__edit-form">
                <label>
                  Judul
                  <input v-model="editForm.title" type="text" />
                </label>
                <p v-if="editErrors.title" class="error">{{ editErrors.title[0] }}</p>

                <label>
                  Ringkasan
                  <textarea v-model="editForm.excerpt" rows="2"></textarea>
                </label>

                <label>Isi Artikel</label>
                <RichTextEditor v-model="editForm.body" />

                <label>
                  Ganti Gambar Sampul (opsional)
                  <input type="file" accept="image/*" @change="onEditCoverChange" />
                </label>

                <label>
                  Status
                  <select v-model="editForm.status">
                    <option value="draft">Draft</option>
                    <option value="published">Diterbitkan</option>
                  </select>
                </label>

                <button
                  type="button"
                  class="btn btn--primary"
                  :disabled="savingId === article.id"
                  @click="saveArticle(article)"
                >
                  {{ savingId === article.id ? 'Menyimpan...' : 'Simpan' }}
                </button>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <div v-if="meta.lastPage > 1" class="admin-articles__pagination">
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
.admin-articles {
  max-width: 900px;
}
.admin-articles__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-articles__create {
  margin-bottom: 24px;
  padding: 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-articles__create h2 {
  font-size: 15px;
  margin-bottom: 12px;
}
.admin-articles__create label,
.admin-articles__edit-form label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
.admin-articles__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-articles__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-articles__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-articles__actions {
  display: flex;
  gap: 6px;
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
.admin-articles__edit-row {
  background: var(--color-paper-alt);
}
.admin-articles__edit-form {
  padding: 14px 4px;
  max-width: 600px;
}
.admin-articles__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  font-size: 13px;
}
</style>
