<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const codes = ref([])
const loading = ref(true)
const loadError = ref('')

const form = ref({
  code: '',
  type: 'percentage',
  value: '',
  tiers: [], // kosong = berlaku semua tier
  max_uses: '',
  valid_until: '',
})
const errors = ref({})
const submitting = ref(false)
const togglingId = ref(null)

async function loadCodes() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/discount-codes')
    codes.value = data.data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat kode diskon.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  submitting.value = true
  errors.value = {}
  try {
    await api.post('/admin/discount-codes', {
      code: form.value.code,
      type: form.value.type,
      value: Number(form.value.value),
      applicable_tiers: form.value.tiers.length ? form.value.tiers : null,
      max_uses: form.value.max_uses ? Number(form.value.max_uses) : null,
      valid_until: form.value.valid_until || null,
    })
    toast.push(`Kode ${form.value.code.toUpperCase()} berhasil dibuat.`, 'success')
    form.value = { code: '', type: 'percentage', value: '', tiers: [], max_uses: '', valid_until: '' }
    await loadCodes()
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat kode diskon.')
  } finally {
    submitting.value = false
  }
}

async function toggleActive(discount) {
  togglingId.value = discount.id
  try {
    await api.patch(`/admin/discount-codes/${discount.id}`, { is_active: !discount.is_active })
    toast.push(`Kode ${discount.code} ${discount.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`, 'success')
    await loadCodes()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah status kode.')
  } finally {
    togglingId.value = null
  }
}

function formatValue(discount) {
  return discount.type === 'percentage' ? `${discount.value}%` : `Rp ${discount.value.toLocaleString('id-ID')}`
}

onMounted(loadCodes)
</script>

<template>
  <div class="admin-discounts">
    <h1>Kelola Diskon</h1>
    <p class="admin-discounts__note">
      Kode yang sudah pernah dipakai tidak bisa diedit nilainya — nonaktifkan, lalu buat kode baru
      kalau perlu perilaku berbeda.
    </p>

    <form class="admin-discounts__form" @submit.prevent="submit">
      <div class="admin-discounts__row">
        <label>
          Kode
          <input v-model="form.code" type="text" placeholder="mis. HEMAT10" required />
        </label>
        <label>
          Tipe
          <select v-model="form.type">
            <option value="percentage">Persentase (%)</option>
            <option value="fixed">Potongan Tetap (Rp)</option>
          </select>
        </label>
        <label>
          Nilai
          <input v-model="form.value" type="number" min="1" required />
        </label>
      </div>
      <p v-if="errors.code" class="error">{{ errors.code[0] }}</p>
      <p v-if="errors.value" class="error">{{ errors.value[0] }}</p>

      <div class="admin-discounts__row">
        <label class="admin-discounts__checkboxes">
          Berlaku untuk tier (kosongkan = semua)
          <span>
            <label><input v-model="form.tiers" type="checkbox" value="comprehensive" /> Comprehensive</label>
            <label><input v-model="form.tiers" type="checkbox" value="master" /> Master</label>
            <label><input v-model="form.tiers" type="checkbox" value="token" /> Token (pembelian token grafolog)</label>
          </span>
        </label>
        <label>
          Kuota pemakaian (opsional)
          <input v-model="form.max_uses" type="number" min="1" placeholder="Tanpa batas" />
        </label>
        <label>
          Berlaku sampai (opsional)
          <input v-model="form.valid_until" type="date" />
        </label>
      </div>

      <button type="submit" class="btn btn--primary" :disabled="submitting">
        {{ submitting ? 'Membuat...' : 'Buat Kode' }}
      </button>
    </form>

    <h2 class="admin-discounts__list-title">Semua Kode</h2>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="codes.length === 0" class="admin-discounts__empty">Belum ada kode diskon.</p>
    <table v-else class="admin-discounts__table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nilai</th>
          <th>Tier</th>
          <th>Pemakaian</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="d in codes" :key="d.id">
          <td class="admin-discounts__code">{{ d.code }}</td>
          <td>{{ formatValue(d) }}</td>
          <td>{{ d.applicable_tiers?.join(', ') ?? 'Semua' }}</td>
          <td>{{ d.used_count }}{{ d.max_uses ? ` / ${d.max_uses}` : '' }}</td>
          <td>
            <span class="badge" :class="d.is_active ? 'badge--active' : 'badge--inactive'">
              {{ d.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td>
            <button type="button" class="btn" :disabled="togglingId === d.id" @click="toggleActive(d)">
              {{ d.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.admin-discounts {
  max-width: 820px;
}
.admin-discounts__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-discounts__form {
  padding: 20px;
  margin-bottom: 32px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-discounts__row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
  margin-bottom: 14px;
}
.admin-discounts__form label {
  display: block;
  font-size: 14px;
  color: var(--color-text-soft);
}
.admin-discounts__checkboxes span {
  display: flex;
  gap: 14px;
  margin-top: 6px;
  font-size: 13px;
}
.admin-discounts__checkboxes span label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
}
.admin-discounts__checkboxes input[type='checkbox'] {
  width: auto;
  margin: 0;
}
.admin-discounts__list-title {
  font-size: 16px;
  margin-bottom: 10px;
}
.admin-discounts__empty {
  color: var(--color-text-soft);
}
.admin-discounts__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.admin-discounts__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-discounts__table td {
  padding: 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-discounts__code {
  font-family: monospace;
  font-weight: 600;
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
