<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { formatRupiah, formatTanggal, STATUS_PESANAN } from '@/lib/format'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import ServiceDeliveryCard from '@/components/commerce/ServiceDeliveryCard.vue'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const toast = useToast()
const reports = ref([])
const orders = ref([])
const loading = ref(true)
const error = ref('')

const statusLabel = {
  generating: 'Diproses',
  completed: 'Selesai',
  failed: 'Gagal',
}

async function load() {
  try {
    const [{ data }, ordersRes] = await Promise.all([
      api.get('/reports'),
      auth.isClient ? api.get('/orders') : Promise.resolve({ data: [] }),
    ])
    reports.value = data.data
    orders.value = ordersRes.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat riwayat.'
  } finally {
    loading.value = false
  }
}

async function payOrder(order) {
  try {
    const { data } = await api.post(`/orders/${order.id}/payment`)
    if (data.paid) {
      toast.push('Mode uji: pesanan langsung dianggap lunas.')
      await load()
    } else {
      window.location.href = data.payment_url
    }
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memulai pembayaran.')
  }
}

async function cancelOrder(order) {
  try {
    await api.post(`/orders/${order.id}/cancel`)
    toast.push('Pesanan dibatalkan.', 'success')
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membatalkan pesanan.')
  }
}

onMounted(load)
</script>

<template>
  <div class="riwayat">
    <template v-if="auth.isClient">
      <h1>Pesanan Saya</h1>
      <SkeletonLoader v-if="loading" variant="list" :rows="2" label="Memuat pesanan..." />
      <p v-else-if="!orders.length" class="riwayat__empty">
        Belum ada pesanan. <RouterLink to="/pesan">Lihat layanan</RouterLink>
      </p>
      <ul v-else class="riwayat__orders">
        <li v-for="o in orders" :key="o.id" class="riwayat__order">
          <div class="riwayat__order-head">
            <div>
              <strong>{{ o.product?.nama }}</strong>
              <span class="riwayat__meta"
                >#{{ o.id }} · {{ formatTanggal(o.created_at) }} ·
                {{ formatRupiah(o.amount) }}</span
              >
            </div>
            <span :class="['riwayat__badge', `riwayat__badge--${o.status}`]">{{
              STATUS_PESANAN[o.status] ?? o.status
            }}</span>
          </div>

          <div v-if="o.status === 'menunggu_pembayaran'" class="riwayat__actions">
            <button type="button" class="btn btn--primary" @click="payOrder(o)">Bayar</button>
            <button type="button" class="btn" @click="cancelOrder(o)">Batalkan</button>
          </div>

          <div v-if="o.sample_id && o.status !== 'menunggu_pembayaran'" class="riwayat__component">
            <span>Laporan grafologi</span>
            <RouterLink v-if="o.report_id" :to="`/reports/${o.report_id}`"
              >Lihat laporan</RouterLink
            >
            <span v-else class="riwayat__meta">Sedang dikerjakan grafolog</span>
          </div>
          <ServiceDeliveryCard v-for="d in o.deliveries" :key="d.id" :delivery="d" mode="client" />
        </li>
      </ul>
    </template>

    <h1 :class="{ 'riwayat__h1--second': auth.isClient }">Riwayat Laporan</h1>
    <SkeletonLoader v-if="loading" variant="list" label="Memuat..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="reports.length === 0">Belum ada laporan.</p>
    <ul v-else class="riwayat__list">
      <li v-for="report in reports" :key="report.id" class="riwayat__item">
        <RouterLink :to="`/reports/${report.id}`">
          Laporan #{{ report.id }} - {{ report.tier }}
        </RouterLink>
        <span class="riwayat__status">{{ statusLabel[report.status] ?? report.status }}</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.riwayat__h1--second {
  margin-top: 36px;
}
.riwayat__empty,
.riwayat__meta {
  color: var(--color-text-soft);
  font-size: 13px;
}
.riwayat__orders {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.riwayat__order {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}
.riwayat__order-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
}
.riwayat__order-head div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.riwayat__badge {
  align-self: flex-start;
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--color-gold-soft);
}
.riwayat__badge--selesai {
  background: var(--color-sage-soft);
}
.riwayat__badge--dibatalkan {
  background: var(--color-seal-soft);
}
.riwayat__actions {
  display: flex;
  gap: 8px;
}
.riwayat__component {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}
.riwayat__list {
  list-style: none;
  padding: 0;
}
.riwayat__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 4px 12px;
  padding: 14px 4px;
  border-bottom: 1px solid var(--color-border);
}
.riwayat__item a {
  text-decoration: none;
  color: var(--color-ink);
  font-weight: 500;
}
.riwayat__item a:hover {
  color: var(--color-seal);
}
.riwayat__status {
  color: var(--color-text-soft);
  font-size: 13px;
  white-space: nowrap;
}
</style>
