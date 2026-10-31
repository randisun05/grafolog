<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'
import { formatRupiah } from '@/lib/format'

/**
 * Kelola perusahaan B2B (dari branch lama): daftar + ringkasan aktivitas lintas perusahaan, buat perusahaan,
 * aktif/nonaktif, dan pencatatan kontrak custom (sales-led, hanya catatan - sistem TIDAK menghitung tagihan dari sini).
 * Verifikasi pendaftaran mandiri perusahaan tetap di /admin/verifikasi?tab=perusahaan.
 */
const toast = useToast()
const companies = ref([])
const loading = ref(true)
const loadError = ref('')

const companyForm = ref({ name: '' })
const companyErrors = ref({})
const companySubmitting = ref(false)
const togglingId = ref(null)

const expandedId = ref(null)
const contractForm = ref({})
const contractErrors = ref({})
const contractSubmitting = ref(false)
const contractDeletingId = ref(null)

const STATUS_LABEL = { draft: 'Draft', aktif: 'Aktif', dihentikan: 'Dihentikan' }

function emptyContractForm() {
  return { judul: '', catatan: '', nilai_kontrak: '', mulai_at: '', berakhir_at: '', status: 'draft' }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    companies.value = (await api.get('/admin/companies')).data.data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat perusahaan.'
  } finally {
    loading.value = false
  }
}

async function submitCompany() {
  companySubmitting.value = true
  companyErrors.value = {}
  try {
    await api.post('/admin/companies', companyForm.value)
    toast.push(`Perusahaan ${companyForm.value.name} berhasil dibuat.`, 'success')
    companyForm.value = { name: '' }
    await load()
  } catch (e) {
    companyErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat perusahaan.')
  } finally {
    companySubmitting.value = false
  }
}

async function toggleActive(company) {
  togglingId.value = company.id
  try {
    const { data } = await api.patch(`/admin/companies/${company.id}`, {
      name: company.name,
      is_active: !company.is_active,
    })
    company.is_active = data.is_active
    toast.push(`${company.name} ${company.is_active ? 'diaktifkan' : 'dinonaktifkan'}.`, 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah status perusahaan.')
  } finally {
    togglingId.value = null
  }
}

function toggleExpand(company) {
  expandedId.value = expandedId.value === company.id ? null : company.id
  contractForm.value = emptyContractForm()
  contractErrors.value = {}
}

function latestContract(company) {
  return company.contracts?.length ? company.contracts[company.contracts.length - 1] : null
}

function isExpired(contract) {
  return (
    contract.status === 'aktif' && contract.berakhir_at && new Date(contract.berakhir_at) < new Date()
  )
}

async function submitContract(company) {
  contractSubmitting.value = true
  contractErrors.value = {}
  try {
    const payload = {
      ...contractForm.value,
      nilai_kontrak: contractForm.value.nilai_kontrak || null,
      berakhir_at: contractForm.value.berakhir_at || null,
    }
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

onMounted(load)
</script>

<template>
  <div class="acm">
    <h1>Kelola Perusahaan</h1>
    <p class="acm__hint">
      Ringkasan aktivitas tiap perusahaan, pencatatan kontrak, dan aktif/nonaktif. Menonaktifkan perusahaan hanya
      mencegahnya dipakai untuk akun HR baru; akun HR yang sudah ada dinonaktifkan sendiri-sendiri di Kelola Staf.
      Verifikasi pendaftaran mandiri ada di menu Verifikasi Perusahaan.
    </p>

    <form class="acm__form" @submit.prevent="submitCompany">
      <label>
        Nama Perusahaan
        <input v-model="companyForm.name" type="text" placeholder="mis. PT Nusantara Rekrut" required />
      </label>
      <button type="submit" class="btn btn--primary" :disabled="companySubmitting">
        {{ companySubmitting ? 'Membuat...' : 'Buat Perusahaan' }}
      </button>
    </form>
    <p v-if="companyErrors.name" class="error">{{ companyErrors.name[0] }}</p>

    <SkeletonLoader v-if="loading" variant="table" :columns="7" label="Memuat perusahaan" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="companies.length === 0">Belum ada perusahaan.</p>
    <div v-else class="table-scroll">
      <table class="acm__table">
        <thead>
          <tr>
            <th>Nama</th>
            <th>HR</th>
            <th>Kandidat</th>
            <th>Selesai</th>
            <th>Rata-rata durasi</th>
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
                  {{ STATUS_LABEL[latestContract(c).status] }}
                  <strong v-if="isExpired(latestContract(c))"> · kadaluarsa</strong>
                </template>
                <span v-else class="acm__muted">Belum ada</span>
              </td>
              <td>{{ c.is_active ? 'Aktif' : 'Nonaktif' }}</td>
              <td class="acm__actions">
                <button type="button" class="btn" :disabled="togglingId === c.id" @click="toggleActive(c)">
                  {{ c.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
                <button type="button" class="btn" @click="toggleExpand(c)">
                  {{ expandedId === c.id ? 'Tutup' : 'Kontrak' }}
                </button>
              </td>
            </tr>
            <tr v-if="expandedId === c.id">
              <td colspan="8">
                <div class="acm__panel">
                  <h3>Riwayat Kontrak — {{ c.name }}</h3>
                  <p class="acm__hint">
                    Kontrak custom sales-led, dicatat manual — sistem tidak menghitung tagihan otomatis dari sini.
                  </p>
                  <p v-if="!c.contracts?.length" class="acm__muted">Belum ada kontrak tercatat.</p>
                  <ul v-else class="acm__contracts">
                    <li v-for="k in c.contracts" :key="k.id">
                      <div>
                        <strong>{{ k.judul }}</strong> · {{ STATUS_LABEL[k.status] }}
                        <strong v-if="isExpired(k)"> · kadaluarsa</strong>
                        <p class="acm__muted">
                          {{ k.mulai_at }} – {{ k.berakhir_at ?? 'tanpa batas' }}
                          <template v-if="k.nilai_kontrak"> · {{ formatRupiah(Number(k.nilai_kontrak)) }}</template>
                        </p>
                        <p v-if="k.catatan" class="acm__muted">{{ k.catatan }}</p>
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

                  <h3>Tambah Kontrak</h3>
                  <div class="acm__row">
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
                  <div class="acm__row">
                    <label>
                      Mulai
                      <input v-model="contractForm.mulai_at" type="date" required />
                    </label>
                    <label>
                      Berakhir (opsional)
                      <input v-model="contractForm.berakhir_at" type="date" />
                    </label>
                    <label>
                      Nilai kontrak (opsional, referensi internal)
                      <input v-model="contractForm.nilai_kontrak" type="number" min="0" placeholder="Rp" />
                    </label>
                  </div>
                  <p v-if="contractErrors.mulai_at" class="error">{{ contractErrors.mulai_at[0] }}</p>
                  <p v-if="contractErrors.berakhir_at" class="error">{{ contractErrors.berakhir_at[0] }}</p>
                  <label>
                    Catatan ketentuan (opsional)
                    <textarea v-model="contractForm.catatan" rows="2"></textarea>
                  </label>
                  <button
                    type="button"
                    class="btn btn--primary"
                    :disabled="contractSubmitting || !contractForm.judul || !contractForm.mulai_at"
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
    </div>
  </div>
</template>

<style scoped>
.acm__hint,
.acm__muted {
  font-size: 13px;
  color: var(--color-text-soft);
}
.acm__form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
  margin: 16px 0;
}
.acm__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.acm__table th,
.acm__table td {
  text-align: left;
  padding: 8px 10px;
  border-bottom: 1px solid var(--color-border);
  vertical-align: top;
}
.acm__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.acm__panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.acm__row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.acm__row label {
  flex: 1 1 180px;
}
.acm__contracts {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.acm__contracts li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
</style>
