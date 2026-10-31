<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatRupiah, formatTanggal } from '@/lib/format'
import { useToast } from '@/composables/useToast'

/** Grafolog: komisi dari pesanan selesai + riwayat pencairan. Token bukan uang dan terpisah dari komisi. */
const data = ref(null)
const loading = ref(true)
const editing = ref(false)
const bank = ref({ bank_name: '', account_number: '', account_holder: '' })
const saving = ref(false)
const bankError = ref('')
const toast = useToast()
const STATUS = {
  diperoleh: 'Menunggu pencairan',
  dibayar: 'Sudah dicairkan',
  dibatalkan: 'Dibatalkan',
}

async function openEdit() {
  const { data: b } = await api.get('/bank-account')
  if (b) bank.value = b
  editing.value = true
}
async function saveBank() {
  saving.value = true
  bankError.value = ''
  try {
    await api.put('/bank-account', bank.value)
    toast.push('Rekening disimpan.', 'success')
    editing.value = false
    data.value = (await api.get('/earnings')).data
  } catch (e) {
    bankError.value =
      e.response?.data?.errors?.account_number?.[0] ??
      e.response?.data?.message ??
      'Gagal menyimpan.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    data.value = (await api.get('/earnings')).data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="earn">
    <h1>Pendapatan Saya</h1>
    <p class="earn__hint">
      Komisi dihitung per pesanan yang selesai, menurut aturan produk yang berlaku. Pencairan
      dicatat administrator. Token terpisah dan tidak dapat diuangkan.
    </p>
    <SkeletonLoader v-if="loading" variant="dashboard" label="Memuat..." />
    <template v-else>
      <div class="earn__kpis">
        <div class="earn__kpi">
          <span>Menunggu pencairan</span><strong>{{ formatRupiah(data.totals.diperoleh) }}</strong>
        </div>
        <div class="earn__kpi">
          <span>Sudah dicairkan</span><strong>{{ formatRupiah(data.totals.dibayar) }}</strong>
        </div>
      </div>
      <section class="earn__bank">
        <h2>Rekening pencairan</h2>
        <p v-if="data.bank && !editing">
          {{ data.bank.bank_name }} · {{ data.bank.masked }}
          <button type="button" class="btn" @click="openEdit">Ubah</button>
        </p>
        <p v-else-if="!editing" class="earn__hint">
          Belum diisi - isi agar komisi dapat dicairkan.
          <button type="button" class="btn btn--primary" @click="openEdit">Isi rekening</button>
        </p>
        <form v-if="editing" class="earn__form" @submit.prevent="saveBank">
          <label
            >Bank <input v-model="bank.bank_name" required maxlength="80" placeholder="mis. BCA"
          /></label>
          <label
            >Nomor rekening
            <input v-model="bank.account_number" required inputmode="numeric" maxlength="30"
          /></label>
          <label>Atas nama <input v-model="bank.account_holder" required maxlength="120" /></label>
          <p v-if="bankError" class="error">{{ bankError }}</p>
          <div>
            <button class="btn btn--primary" :disabled="saving">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
            <button type="button" class="btn" @click="editing = false">Batal</button>
          </div>
          <small class="earn__hint"
            >Nomor rekening disimpan terenkripsi dan hanya dibuka administrator saat mencatat
            pencairan (tercatat di audit).</small
          >
        </form>
      </section>
      <h2>Komisi per pesanan</h2>
      <p v-if="!data.earnings.length" class="earn__hint">Belum ada komisi.</p>
      <div v-else class="table-scroll">
        <table class="earn__table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Pesanan</th>
              <th>Produk</th>
              <th>Dasar</th>
              <th>Aturan</th>
              <th>Komisi</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in data.earnings" :key="e.id">
              <td>{{ formatTanggal(e.earned_at) }}</td>
              <td>#{{ e.order_id }}</td>
              <td>{{ e.product }}</td>
              <td>{{ formatRupiah(e.base_amount) }}</td>
              <td>{{ e.rule }}</td>
              <td>
                <strong>{{ formatRupiah(e.amount) }}</strong>
              </td>
              <td>
                {{ STATUS[e.status]
                }}<small v-if="e.note" class="earn__hint"> · {{ e.note }}</small>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <h2>Riwayat pencairan</h2>
      <p v-if="!data.payouts.length" class="earn__hint">Belum ada pencairan.</p>
      <ul v-else class="earn__payouts">
        <li v-for="p in data.payouts" :key="p.id">
          {{ formatTanggal(p.paid_at) }} — <strong>{{ formatRupiah(p.total) }}</strong>
          <span v-if="p.reference" class="earn__hint"> · ref. {{ p.reference }}</span>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.earn__hint {
  color: var(--color-text-soft);
  font-size: 13px;
}
.earn h2 {
  font-size: 16px;
  margin: 18px 0 8px;
}
.earn__kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}
.earn__kpi {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
}
.earn__kpi span {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.earn__kpi strong {
  font-size: 20px;
}
.earn__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.earn__table th,
.earn__table td {
  text-align: left;
  padding: 6px 8px;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}
.earn__payouts {
  padding-left: 18px;
  font-size: 14px;
}
.earn__form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 360px;
}
.earn__form label {
  display: flex;
  flex-direction: column;
  gap: 3px;
  font-size: 13px;
}
</style>
