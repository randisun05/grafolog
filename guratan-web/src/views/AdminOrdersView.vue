<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import SampleImages from '@/components/shared/SampleImages.vue'
import AdminServiceQueue from '@/components/commerce/AdminServiceQueue.vue'
import AdminRefundQueue from '@/components/commerce/AdminRefundQueue.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const filters = [
  { value: 'unassigned', label: 'Perlu Ditugaskan' },
  { value: 'in_progress', label: 'Sedang Dikerjakan' },
  { value: 'unpaid', label: 'Belum Bayar' },
  { value: 'completed', label: 'Selesai' },
  { value: 'all', label: 'Semua' },
]
const status = ref('unassigned')
const orders = ref([])
const grafologs = ref([])
const selectedGrafolog = ref({})
const assigning = ref({})
const expanded = ref(null)
const loading = ref(true)
const loadError = ref('')

async function loadOrders() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/orders', { params: { status: status.value } })
    orders.value = data.data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat pesanan.'
  } finally {
    loading.value = false
  }
}

async function loadGrafologs() {
  try {
    const { data } = await api.get('/grafologs')
    grafologs.value = data
  } catch {
    toast.push('Gagal memuat daftar grafolog.')
  }
}

async function assign(order) {
  const grafologId = selectedGrafolog.value[order.id]
  if (!grafologId) return
  assigning.value = { ...assigning.value, [order.id]: true }
  try {
    await api.post(`/samples/${order.id}/assignment`, { grafolog_id: grafologId })
    toast.push('Grafolog berhasil ditugaskan.', 'success')
    await loadOrders()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menugaskan grafolog.')
  } finally {
    assigning.value = { ...assigning.value, [order.id]: false }
  }
}

function formatDate(value) {
  return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

watch(status, loadOrders)
onMounted(() => Promise.all([loadOrders(), loadGrafologs()]))
</script>

<template>
  <div class="admin-orders">
    <h1>Pesanan Masuk</h1>
    <p class="admin-orders__note">
      Analisis tulisan tangan dari pesanan klien (Master). Tugaskan grafolog setelah pembayaran
      lunas. Layanan pendukung (wawancara, konsultasi, konseling, intervensi) ada di bagian bawah.
    </p>

    <div class="admin-orders__filters" role="tablist">
      <button
        v-for="f in filters"
        :key="f.value"
        type="button"
        class="btn"
        :class="{ 'btn--primary': status === f.value }"
        @click="status = f.value"
      >
        {{ f.label }}
      </button>
    </div>

    <SkeletonLoader v-if="loading" variant="list" :rows="5" label="Memuat pesanan..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="!orders.length" class="admin-orders__empty">Tidak ada pesanan pada filter ini.</p>
    <ul v-else class="admin-orders__list">
      <li v-for="o in orders" :key="o.id" class="admin-orders__item">
        <div class="admin-orders__row">
          <div class="admin-orders__info">
            <strong>#{{ o.id }} · {{ o.user?.name }}</strong>
            <span class="admin-orders__meta">
              {{ o.user?.email }} · {{ o.tier }} · {{ formatDate(o.created_at) }}
            </span>
            <span class="admin-orders__meta">
              <span :class="o.is_paid ? 'admin-orders__paid' : 'admin-orders__unpaid'">
                {{ o.is_paid ? 'Lunas' : 'Belum bayar' }}
              </span>
              ·
              <button
                type="button"
                class="admin-orders__link"
                @click="expanded = expanded === o.id ? null : o.id"
              >
                {{ o.images_count }} scan
              </button>
            </span>
          </div>

          <div class="admin-orders__assign">
            <span v-if="o.assignment?.grafolog">Grafolog: {{ o.assignment.grafolog.name }}</span>
            <template v-else-if="o.status !== 'completed'">
              <select v-model="selectedGrafolog[o.id]" :disabled="!o.is_paid">
                <option :value="undefined" disabled>Pilih grafolog</option>
                <option v-for="g in grafologs" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
              <button
                type="button"
                class="btn btn--primary"
                :disabled="!o.is_paid || !selectedGrafolog[o.id] || assigning[o.id]"
                @click="assign(o)"
              >
                {{ assigning[o.id] ? '...' : 'Tugaskan' }}
              </button>
            </template>
          </div>
        </div>
        <SampleImages v-if="expanded === o.id" :sample-id="o.id" />
      </li>
    </ul>
    <AdminRefundQueue />
    <AdminServiceQueue :grafologs="grafologs" />
  </div>
</template>

<style scoped>
.admin-orders {
  max-width: 900px;
}
.admin-orders__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 16px;
}
.admin-orders__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 18px;
}
.admin-orders__empty {
  color: var(--color-text-soft);
}
.admin-orders__list {
  list-style: none;
  padding: 0;
}
.admin-orders__item {
  padding: 14px 0;
  border-bottom: 1px solid var(--color-border);
}
.admin-orders__row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 10px 16px;
}
.admin-orders__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.admin-orders__meta {
  font-size: 12.5px;
  color: var(--color-text-soft);
  overflow-wrap: anywhere;
}
.admin-orders__paid {
  color: var(--color-success);
  font-weight: 600;
}
.admin-orders__unpaid {
  color: var(--color-error);
}
.admin-orders__link {
  background: none;
  border: none;
  padding: 0;
  color: var(--color-seal);
  cursor: pointer;
  font-size: inherit;
  text-decoration: underline;
}
.admin-orders__assign {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.admin-orders__assign select {
  width: auto;
  max-width: 100%;
}
</style>
