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

// --- kontrak B2B (Fase 3 - record-only, lihat guratan-api/CLAUDE.md
// "B2B Fase 3") - sales-led, sistem cuma mencatat kesepakatan, TIDAK
// menghitung tagihan otomatis. ---

const expandedCompanyId = ref(null)
const contractForm = ref({})
const contractErrors = ref({})
const contractSubmitting = ref(false)
const contractDeletingId = ref(null)

function emptyContractForm() {
  return { judul: '', catatan: '', nilai_kontrak: '', mulai_at: '', berakhir_at: '', status: 'draft' }
}

function toggleCompanyExpand(company) {
  if (expandedCompanyId.value === company.id) {
    expandedCompanyId.value = null
    return
  }
  expandedCompanyId.value = company.id
  contractForm.value = emptyContractForm()
  contractErrors.value = {}
}

function latestContract(company) {
  return company.contracts?.length ? company.contracts[company.contracts.length - 1] : null
}

function isContractExpired(contract) {
  return contract.status === 'aktif' && contract.berakhir_at && new Date(contract.berakhir_at) < new Date()
}

const contractStatusLabel = { draft: 'Draft', aktif: 'Aktif', dihentikan: 'Dihentikan' }

async function submitContract(company) {
  contractSubmitting.value = true
  contractErrors.value = {}
  try {
    const payload = { ...contractForm.value, nilai_kontrak: contractForm.value.nilai_kontrak || null, berakhir_at: contractForm.value.berakhir_at || null }
    const { data } = await api.post(`/admin/companies/${company.id}/contracts`, payload)
    company.contracts = [...(company.contracts ?? []), data]
    contractForm.value = emptyContractForm()
    toast.push('Kontrak berhasil dicatat.', 'success')
  } catch (e) {
    contractErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal mencatat kontrak.')
  } finally {
    contractSubmitting.value = false
  }
}

async function deleteContract(company, contract) {
  contractDeletingId.value = contract.id
  try {
    await api.delete(`/admin/company-contracts/${contract.id}`)
    company.contracts = company.contracts.filter((c) => c.id !== contract.id)
    toast.push('Kontrak dihapus.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus kontrak.')
  } finally {
    contractDeletingId.value = null
  }
}
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
          <th>HR</th>
          <th>Kandidat</th>
          <th>Selesai</th>
          <th>Rata-rata Durasi</th>
          <th>Kontrak</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="c in companies" :key="c.id">
          <tr>
            <td>{{ c.name }}</td>
            <td>{{ c.hr_count }}</td>
            <td>{{ c.total_candidates }}</td>
            <td>{{ c.completed_reports }}</td>
            <td>{{ c.avg_turnaround_days !== null ? `${c.avg_turnaround_days} hari` : '–' }}</td>
            <td>
              <template v-if="latestContract(c)">
                <span class="badge" :class="`badge--contract-${latestContract(c).status}`">
                  {{ contractStatusLabel[latestContract(c).status] }}
                </span>
                <span v-if="isContractExpired(latestContract(c))" class="badge badge--inactive">Kadaluarsa</span>
              </template>
              <span v-else class="admin-users__hint-inline">Belum ada kontrak</span>
            </td>
            <td>
              <span class="badge" :class="c.is_active ? 'badge--active' : 'badge--inactive'">
                {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="admin-users__actions">
              <button type="button" class="btn" :disabled="companyTogglingId === c.id" @click="toggleCompanyActive(c)">
                {{ c.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
              <button type="button" class="btn" @click="toggleCompanyExpand(c)">
                {{ expandedCompanyId === c.id ? 'Tutup' : 'Kontrak' }}
              </button>
            </td>
          </tr>
          <tr v-if="expandedCompanyId === c.id" class="admin-users__edit-row">
            <td colspan="8">
              <div class="admin-users__edit-panel">
                <h4>Riwayat Kontrak — {{ c.name }}</h4>
                <p class="admin-users__hint">
                  Kontrak custom sales-led, dicatat manual — sistem tidak menghitung tagihan otomatis
                  dari sini.
                </p>

                <p v-if="!c.contracts || c.contracts.length === 0" class="admin-users__hint">
                  Belum ada kontrak tercatat.
                </p>
                <ul v-else class="admin-users__contract-list">
                  <li v-for="k in c.contracts" :key="k.id" class="admin-users__contract-item">
                    <div>
                      <strong>{{ k.judul }}</strong>
                      <span class="badge" :class="`badge--contract-${k.status}`">{{ contractStatusLabel[k.status] }}</span>
                      <span v-if="isContractExpired(k)" class="badge badge--inactive">Kadaluarsa</span>
                      <p class="admin-users__hint">
                        {{ k.mulai_at }} – {{ k.berakhir_at ?? 'tanpa batas' }}
                        <template v-if="k.nilai_kontrak"> · Rp {{ Number(k.nilai_kontrak).toLocaleString('id-ID') }}</template>
                      </p>
                      <p v-if="k.catatan" class="admin-users__hint">{{ k.catatan }}</p>
                    </div>
                    <button
                      type="button"
                      class="btn"
                      :disabled="contractDeletingId === k.id"
                      @click="deleteContract(c, k)"
                    >
                      Hapus
                    </button>
                  </li>
                </ul>

                <h4>Tambah Kontrak</h4>
                <div class="admin-users__row">
                  <label>
                    Judul
                    <input v-model="contractForm.judul" type="text" placeholder="mis. Kontrak Tahunan 2026" required />
                  </label>
                  <label>
                    Status
                    <select v-model="contractForm.status">
                      <option value="draft">Draft</option>
                      <option value="aktif">Aktif</option>
                      <option value="dihentikan">Dihentikan</option>
                    </select>
                  </label>
                </div>
                <p v-if="contractErrors.judul" class="error">{{ contractErrors.judul[0] }}</p>

                <div class="admin-users__row">
                  <label>
                    Mulai
                    <input v-model="contractForm.mulai_at" type="date" required />
                  </label>
                  <label>
                    Berakhir (opsional)
                    <input v-model="contractForm.berakhir_at" type="date" />
                  </label>
                  <label>
                    Nilai Kontrak (opsional, referensi internal)
                    <input v-model="contractForm.nilai_kontrak" type="number" min="0" placeholder="Rp" />
                  </label>
                </div>
                <p v-if="contractErrors.mulai_at" class="error">{{ contractErrors.mulai_at[0] }}</p>
                <p v-if="contractErrors.berakhir_at" class="error">{{ contractErrors.berakhir_at[0] }}</p>

                <label>
                  Catatan Ketentuan (opsional)
                  <textarea v-model="contractForm.catatan" rows="2" placeholder="Ketentuan hasil negosiasi sales"></textarea>
                </label>

                <button
                  type="button"
                  class="btn btn--primary"
                  :disabled="contractSubmitting"
                  @click="submitContract(c)"
                >
                  {{ contractSubmitting ? 'Menyimpan...' : 'Catat Kontrak' }}
                </button>
              </div>
            </td>
          </tr>
        </template>
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
.admin-users__hint-inline {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.admin-users__actions {
  display: flex;
  gap: 6px;
}
.badge--contract-draft {
  background: var(--color-paper-alt);
  color: var(--color-text-soft);
}
.badge--contract-aktif {
  background: var(--color-sage-soft);
  color: var(--color-sage);
}
.badge--contract-dihentikan {
  background: var(--color-seal-soft);
  color: var(--color-seal);
}
.admin-users__edit-panel h4 {
  font-size: 13.5px;
  margin: 16px 0 8px;
}
.admin-users__edit-panel h4:first-child {
  margin-top: 0;
}
.admin-users__contract-list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.admin-users__contract-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 10px;
  background: var(--color-paper-alt);
  border-radius: var(--radius-sm);
}
.admin-users__contract-item strong {
  margin-right: 8px;
}
</style>
