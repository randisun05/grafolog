<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import { formatRupiah, formatTanggal } from '@/lib/format'

/**
 * Refund hanya oleh administrator: kesalahan sistem, atau pesanan > 90 hari belum selesai.
 * Dana dikembalikan manual di dashboard DOKU; sistem mencatat & menutup layanan.
 */
const toast = useToast()
const data = ref({ overdue: [], refunded: [] })
const notes = ref({})
const orderId = ref('')
const manualNote = ref('')
const busy = ref(null)

async function load() {
  try {
    data.value = (await api.get('/admin/refunds')).data
  } catch {
    data.value = { overdue: [], refunded: [] }
  }
}

async function refund(id, reason, note) {
  if (!note?.trim()) {
    toast.push('Isi catatan refund untuk pelanggan.')
    return
  }
  busy.value = id
  try {
    await api.post(`/admin/orders/${id}/refund`, { reason, note })
    toast.push('Refund dicatat - proses pengembalian dana di DOKU.', 'success')
    notes.value[id] = ''
    if (reason === 'kesalahan_sistem') {
      orderId.value = ''
      manualNote.value = ''
    }
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memproses refund.')
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>

<template>
  <section class="arq">
    <h2>Refund</h2>
    <p class="arq__hint">
      Pesanan yang sudah dibayar tidak bisa dibatalkan pelanggan. Refund hanya karena kesalahan
      sistem atau grafolog belum menyelesaikan pesanan &gt; 90 hari.
    </p>

    <h3>Pesanan melewati 90 hari ({{ data.overdue.length }})</h3>
    <p v-if="!data.overdue.length" class="arq__hint">Tidak ada.</p>
    <ul>
      <li v-for="o in data.overdue" :key="o.id" class="arq__item">
        <div class="arq__info">
          <strong>#{{ o.id }} · {{ o.product?.nama }} · {{ formatRupiah(o.amount) }}</strong>
          <span
            >{{ o.user?.name }} · dibayar {{ formatTanggal(o.paid_at) }} · grafolog
            {{ o.grafolog?.name ?? '-' }}</span
          >
        </div>
        <div class="arq__actions">
          <textarea
            v-model="notes[o.id]"
            rows="2"
            placeholder="Catatan untuk pelanggan (wajib)"
          ></textarea>
          <button
            type="button"
            class="btn btn--primary"
            :disabled="busy === o.id"
            @click="refund(o.id, 'grafolog_terlambat', notes[o.id])"
          >
            Refund (grafolog terlambat)
          </button>
        </div>
      </li>
    </ul>

    <h3>Refund karena kesalahan sistem</h3>
    <form class="arq__manual" @submit.prevent="refund(orderId, 'kesalahan_sistem', manualNote)">
      <input v-model="orderId" type="number" min="1" placeholder="No. pesanan" required />
      <input v-model="manualNote" type="text" placeholder="Kronologi / catatan (wajib)" required />
      <button type="submit" class="btn" :disabled="busy === orderId">Refund</button>
    </form>

    <details v-if="data.refunded.length">
      <summary>Riwayat refund ({{ data.refunded.length }})</summary>
      <ul class="arq__done">
        <li v-for="o in data.refunded" :key="o.id">
          #{{ o.id }} · {{ o.user?.name }} · {{ o.product?.nama }} ·
          {{ o.refund_reason === 'kesalahan_sistem' ? 'kesalahan sistem' : 'grafolog terlambat' }} ·
          {{ formatTanggal(o.refunded_at) }}
        </li>
      </ul>
    </details>
  </section>
</template>

<style scoped>
.arq {
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.arq h2 {
  font-size: 17px;
  margin: 0;
}
.arq h3 {
  font-size: 14px;
  margin: 8px 0 0;
}
.arq__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.arq ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.arq__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 14px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-left: 4px solid var(--color-gold);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.arq__info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
}
.arq__info span {
  color: var(--color-text-soft);
  font-size: 13px;
}
.arq__actions {
  min-width: 260px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.arq__manual {
  display: grid;
  grid-template-columns: 120px 1fr auto;
  gap: 8px;
}
.arq__manual input {
  margin: 0;
  width: 100%;
  padding: 9px 10px;
  box-sizing: border-box;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
}
.arq__done {
  font-size: 13px;
  margin-top: 6px !important;
}
</style>
