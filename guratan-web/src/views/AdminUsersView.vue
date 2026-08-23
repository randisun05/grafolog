<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'

const toast = useToast()
const auth = useAuthStore()

const users = ref([])
const loading = ref(true)
const loadError = ref('')

const companies = ref([])

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'grafolog',
  company_id: '',
})
const errors = ref({})
const submitting = ref(false)

const roleLabel = {
  administrator: 'Administrator',
  supervisor: 'Supervisor',
  grafolog: 'Grafolog',
  hr: 'HR',
  user: 'Klien',
}

function companyName(id) {
  return companies.value.find((c) => c.id === id)?.name ?? '-'
}

async function loadUsers() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/users')
    users.value = data.data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat daftar user.'
  } finally {
    loading.value = false
  }
}

async function loadCompanies() {
  try {
    const { data } = await api.get('/admin/companies')
    companies.value = data.data
  } catch {
    // Daftar company gagal dimuat bukan fatal untuk halaman ini - form
    // buat HR akan menampilkan dropdown kosong, tetap bisa dicoba lagi.
  }
}

async function submit() {
  submitting.value = true
  errors.value = {}
  try {
    const payload = { ...form.value, company_id: form.value.role === 'hr' ? form.value.company_id || null : null }
    const { data } = await api.post('/admin/users', payload)
    users.value = [{ ...data, created_at: new Date().toISOString() }, ...users.value]
    toast.push(`Akun ${roleLabel[data.role]} untuk ${data.name} berhasil dibuat.`, 'success')
    form.value = { name: '', email: '', password: '', password_confirmation: '', role: 'grafolog', company_id: '' }
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat akun.')
  } finally {
    submitting.value = false
  }
}

// --- edit akun staf ---

const editingId = ref(null)
const editForm = ref({})
const editErrors = ref({})
const savingId = ref(null)

function startEditUser(u) {
  editingId.value = u.id
  editErrors.value = {}
  editForm.value = {
    name: u.name,
    email: u.email,
    role: u.role,
    company_id: u.company_id ?? '',
    is_active: u.is_active,
    password: '',
    password_confirmation: '',
  }
}

function cancelEditUser() {
  editingId.value = null
}

async function saveUser(u) {
  savingId.value = u.id
  editErrors.value = {}
  try {
    const payload = {
      name: editForm.value.name,
      email: editForm.value.email,
      role: editForm.value.role,
      company_id: editForm.value.role === 'hr' ? editForm.value.company_id || null : null,
      is_active: editForm.value.is_active,
    }
    if (editForm.value.password) {
      payload.password = editForm.value.password
      payload.password_confirmation = editForm.value.password_confirmation
    }
    const { data } = await api.patch(`/admin/users/${u.id}`, payload)
    Object.assign(u, data)
    toast.push(`Akun ${u.name} berhasil diperbarui.`, 'success')
    editingId.value = null
  } catch (e) {
    editErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal memperbarui akun.')
  } finally {
    savingId.value = null
  }
}

// --- kelola perusahaan (dibutuhkan HR - HR wajib terikat ke satu company) ---

const companyForm = ref({ name: '' })
const companyErrors = ref({})
const companySubmitting = ref(false)

async function submitCompany() {
  companySubmitting.value = true
  companyErrors.value = {}
  try {
    await api.post('/admin/companies', companyForm.value)
    toast.push(`Perusahaan ${companyForm.value.name} berhasil dibuat.`, 'success')
    companyForm.value = { name: '' }
    await loadCompanies()
  } catch (e) {
    companyErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat perusahaan.')
  } finally {
    companySubmitting.value = false
  }
}

const companyTogglingId = ref(null)

async function toggleCompanyActive(company) {
  companyTogglingId.value = company.id
  try {
    const { data } = await api.patch(`/admin/companies/${company.id}`, {
      name: company.name,
      is_active: !company.is_active,
    })
    Object.assign(company, data)
    toast.push(`Perusahaan ${company.name} ${company.is_active ? 'diaktifkan' : 'dinonaktifkan'}.`, 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah status perusahaan.')
  } finally {
    companyTogglingId.value = null
  }
}

onMounted(async () => {
  await Promise.all([loadUsers(), loadCompanies()])
})
</script>

<template>
  <div class="admin-users">
    <h1>Kelola Akun Staf</h1>
    <p class="admin-users__note">
      Buat akun Administrator, Supervisor, Grafolog, atau HR. Akun Klien tetap mendaftar sendiri lewat
      halaman Daftar.
    </p>

    <form class="admin-users__form" @submit.prevent="submit">
      <label>
        Nama
        <input v-model="form.name" type="text" required />
      </label>
      <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>

      <label>
        Email
        <input v-model="form.email" type="email" required />
      </label>
      <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

      <label>
        Kata Sandi
        <input v-model="form.password" type="password" required />
      </label>
      <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

      <label>
        Konfirmasi Kata Sandi
        <input v-model="form.password_confirmation" type="password" required />
      </label>

      <label>
        Role
        <select v-model="form.role">
          <option value="grafolog">Grafolog</option>
          <option value="supervisor">Supervisor</option>
          <option value="administrator">Administrator</option>
          <option value="hr">HR</option>
        </select>
      </label>
      <p v-if="errors.role" class="error">{{ errors.role[0] }}</p>

      <label v-if="form.role === 'hr'">
        Perusahaan
        <select v-model="form.company_id" required>
          <option value="" disabled>Pilih perusahaan</option>
          <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <p v-if="errors.company_id" class="error">{{ errors.company_id[0] }}</p>

      <button type="submit" class="btn btn--primary" :disabled="submitting">
        {{ submitting ? 'Membuat...' : 'Buat Akun' }}
      </button>
    </form>

    <h2 class="admin-users__list-title">Semua User</h2>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <table v-else class="admin-users__table">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Role</th>
          <th>Perusahaan</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="u in users" :key="u.id">
          <tr>
            <td>{{ u.name }}</td>
            <td>{{ u.email }}</td>
            <td>
              <span class="admin-users__role-badge">{{ roleLabel[u.role] ?? u.role }}</span>
            </td>
            <td>{{ u.company_id ? companyName(u.company_id) : '-' }}</td>
            <td>
              <span class="badge" :class="u.is_active ? 'badge--active' : 'badge--inactive'">
                {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td>
              <button
                v-if="u.role !== 'user'"
                type="button"
                class="btn"
                @click="editingId === u.id ? cancelEditUser() : startEditUser(u)"
              >
                {{ editingId === u.id ? 'Batal' : 'Ubah' }}
              </button>
            </td>
          </tr>
          <tr v-if="editingId === u.id" class="admin-users__edit-row">
            <td colspan="6">
              <div class="admin-users__edit-panel">
                <div class="admin-users__row">
                  <label>
                    Nama
                    <input v-model="editForm.name" type="text" required />
                  </label>
                  <label>
                    Email
                    <input v-model="editForm.email" type="email" required />
                  </label>
                  <label>
                    Role
                    <select v-model="editForm.role">
                      <option value="grafolog">Grafolog</option>
                      <option value="supervisor">Supervisor</option>
                      <option value="administrator">Administrator</option>
                      <option value="hr">HR</option>
                    </select>
                  </label>
                </div>
                <p v-if="editErrors.name" class="error">{{ editErrors.name[0] }}</p>
                <p v-if="editErrors.email" class="error">{{ editErrors.email[0] }}</p>
                <p v-if="editErrors.role" class="error">{{ editErrors.role[0] }}</p>

                <div class="admin-users__row">
                  <label v-if="editForm.role === 'hr'">
                    Perusahaan
                    <select v-model="editForm.company_id" required>
                      <option value="" disabled>Pilih perusahaan</option>
                      <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                  </label>
                  <label class="admin-users__active-toggle">
                    <input v-model="editForm.is_active" type="checkbox" :disabled="u.id === auth.user?.id" />
                    Akun aktif
                  </label>
                </div>
                <p v-if="editErrors.company_id" class="error">{{ editErrors.company_id[0] }}</p>
                <p v-if="u.id === auth.user?.id" class="admin-users__hint">
                  Tidak bisa menonaktifkan akun Anda sendiri di sini.
                </p>

                <div class="admin-users__row">
                  <label>
                    Reset Kata Sandi (opsional)
                    <input v-model="editForm.password" type="password" placeholder="Kosongkan jika tidak diubah" />
                  </label>
                  <label>
                    Konfirmasi Kata Sandi Baru
                    <input v-model="editForm.password_confirmation" type="password" />
                  </label>
                </div>
                <p v-if="editErrors.password" class="error">{{ editErrors.password[0] }}</p>

                <button type="button" class="btn btn--primary" :disabled="savingId === u.id" @click="saveUser(u)">
                  {{ savingId === u.id ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </button>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <h2 class="admin-users__list-title admin-users__companies-title">Perusahaan (untuk akun HR)</h2>
    <form class="admin-users__company-form" @submit.prevent="submitCompany">
      <label>
        Nama Perusahaan
        <input v-model="companyForm.name" type="text" placeholder="mis. PT Nusantara Rekrut" required />
      </label>
      <p v-if="companyErrors.name" class="error">{{ companyErrors.name[0] }}</p>
      <button type="submit" class="btn btn--primary" :disabled="companySubmitting">
        {{ companySubmitting ? 'Membuat...' : 'Buat Perusahaan' }}
      </button>
    </form>

    <p v-if="companies.length === 0" class="admin-users__empty">Belum ada perusahaan.</p>
    <table v-else class="admin-users__table">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in companies" :key="c.id">
          <td>{{ c.name }}</td>
          <td>
            <span class="badge" :class="c.is_active ? 'badge--active' : 'badge--inactive'">
              {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td>
            <button type="button" class="btn" :disabled="companyTogglingId === c.id" @click="toggleCompanyActive(c)">
              {{ c.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <p class="admin-users__hint">
      Menonaktifkan perusahaan tidak otomatis menonaktifkan akun HR yang sudah terikat ke sana - cuma
      mencegah perusahaan itu dipakai untuk akun HR baru. Nonaktifkan akun HR terkait secara terpisah
      di atas kalau perlu.
    </p>
  </div>
</template>

<style scoped>
.admin-users {
  max-width: 820px;
}
.admin-users__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-users__form,
.admin-users__company-form {
  padding: 20px;
  margin-bottom: 32px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-users__form label,
.admin-users__company-form label {
  display: block;
  margin-bottom: 14px;
  font-size: 14px;
  color: var(--color-text-soft);
}
.admin-users__form .error,
.admin-users__company-form .error {
  font-size: 12px;
  margin: -8px 0 10px;
}
.admin-users__list-title {
  font-size: 16px;
  margin-bottom: 10px;
}
.admin-users__companies-title {
  margin-top: 32px;
}
.admin-users__empty {
  color: var(--color-text-soft);
}
.admin-users__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.admin-users__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12.5px;
  border-bottom: 1px solid var(--color-border);
}
.admin-users__table td {
  padding: 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-users__role-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  background: var(--color-sage-soft);
  color: var(--color-sage);
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
.admin-users__edit-row td {
  background: var(--color-paper-alt);
}
.admin-users__edit-panel {
  padding: 12px 4px;
}
.admin-users__row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
  margin-bottom: 10px;
}
.admin-users__row label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
}
.admin-users__active-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  align-self: end;
}
.admin-users__active-toggle input {
  width: auto;
  margin: 0;
}
.admin-users__hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-top: 10px;
}
</style>
