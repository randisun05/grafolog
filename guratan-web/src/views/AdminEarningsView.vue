<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatRupiah, formatTanggal } from '@/lib/format'
import { useToast } from '@/composables/useToast'

/** Admin: komisi grafolog - ringkasan per grafolog, daftar, dan pencatatan pencairan (transfer di luar sistem). */
const toast = useToast()
const data = ref(null)
const loading = ref(true)
const status = ref('')
const reference = ref({})
const shown = ref({})

async function load() {
  loading.value = true
  try {
    data.value = (
      await api.get('/admin/earnings', { params: status.value ? { status: status.value } : {} })
    ).data
  } finally {
    loading.value = false
  }
}
async function showBank(g) {
  try {
    shown.value[g.grafolog_id] = (await api.get(`/admin/earnings/bank/${g.grafolog_id}`)).data
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membuka rekening.')
  }
}
async function payout(g) {
  if (
    !window.confirm(
      `Catat pencairan ${formatRupiah(g.menunggu)} untuk ${g.name}? Pastikan transfer sudah dilakukan.`,
    )
  )
    return
  try {
    await api.post('/admin/earnings/payout', {
      grafolog_id: g.grafolog_id,
      reference: reference.value[g.grafolog_id] || undefined,
    })
    toast.push('Pencairan dicatat.', 'success')
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mencatat pencairan.')
  }
}
onMounted(load)
</script>

<template>
  <div class="ae">
    <h1>Komisi Grafolog</h1>
    <p class="ae__hint">
      Aturan komisi diatur per produk di Kelola Produk (default + override per grafolog). Komisi
      dicatat saat pesanan selesai dan dibatalkan bila pesanan direfund sebelum dicairkan. Sistem
      tidak mentransfer uang - catat pencairan setelah transfer manual.
    </p>
    <SkeletonLoader v-if="loading" variant="table" :columns="4" label="Memuat..." />
    <template v-else>
      <h2>Per grafolog</h2>
      <p v-if="!data.per_grafolog.length" class="ae__hint">Belum ada komisi.</p>
      <div v-else class="table-scroll">
        <table class="ae__table">
          <thead>
            <tr>
              <th>Grafolog</th>
              <th>Menunggu</th>
              <th>Dicairkan</th>
              <th>Rekening</th>
              <th>Catat pencairan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in data.per_grafolog" :key="g.grafolog_id">
              <td>{{ g.name }}</td>
              <td>
                <strong>{{ formatRupiah(g.menunggu) }}</strong>
              </td>
              <td>{{ formatRupiah(g.dibayar) }}</td>
              <td>
                <template v-if="shown[g.grafolog_id]">
                  {{ shown[g.grafolog_id].bank_name }} {{ shown[g.grafolog_id].account_number
                  }}<br /><small>a.n. {{ shown[g.grafolog_id].account_holder }}</small>
                </template>
                <template v-else-if="g.bank">
                  {{ g.bank }}
                  <button type="button" class="btn" @click="showBank(g)">Tampilkan</button>
                </template>
                <small v-else>Belum diisi</small>
              </td>
              <td class="ae__act">
                <input
                  v-model="reference[g.grafolog_id]"
                  type="text"
                  maxlength="120"
                  placeholder="No. referensi transfer"
                  :aria-label="`Referensi ${g.name}`"
                />
                <button
                  type="button"
                  class="btn btn--primary"
                  :disabled="!g.menunggu"
                  @click="payout(g)"
                >
                  Cairkan
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <h2>Daftar komisi</h2>
      <select v-model="status" aria-label="Status" @change="load">
        <option value="">Semua</option>
        <option value="diperoleh">Menunggu pencairan</option>
        <option value="dibayar">Dicairkan</option>
        <option value="dibatalkan">Dibatalkan</option>
      </select>
      <div class="table-scroll">
        <table class="ae__table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Grafolog</th>
              <th>Pesanan</th>
              <th>Produk</th>
              <th>Dasar</th>
              <th>Komisi</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in data.earnings" :key="e.id">
              <td>{{ formatTanggal(e.earned_at) }}</td>
              <td>{{ e.grafolog }}</td>
              <td>#{{ e.order_id }}</td>
              <td>{{ e.product }}</td>
              <td>{{ formatRupiah(e.base_amount) }}</td>
              <td>{{ formatRupiah(e.amount) }}</td>
              <td>
                {{ e.status }}<small v-if="e.note"> · {{ e.note }}</small>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.ae__hint {
  color: var(--color-text-soft);
  font-size: 13px;
}
.ae h2 {
  font-size: 16px;
  margin: 18px 0 8px;
}
.ae select {
  width: auto;
  margin-bottom: 8px;
}
.ae__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.ae__table th,
.ae__table td {
  text-align: left;
  padding: 6px 8px;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}
.ae__act {
  display: flex;
  gap: 6px;
}
.ae__act input {
  width: 190px;
}
</style>
