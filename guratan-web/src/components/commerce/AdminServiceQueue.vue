<script setup>
// Antrean layanan pendukung untuk admin: tugaskan grafolog terverifikasi ke tiap layanan.
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { formatTanggal, STATUS_LAYANAN, TIPE_LAYANAN } from '@/lib/format'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

defineProps({ grafologs: { type: Array, default: () => [] } })
const toast = useToast()
const status = ref('menunggu_penugasan')
const list = ref([])
const loading = ref(true)
const chosen = ref({})

async function load() {
  loading.value = true
  try {
    list.value = (
      await api.get('/service-deliveries', {
        params: status.value === 'all' ? {} : { status: status.value },
      })
    ).data
  } catch {
    toast.push('Gagal memuat layanan.')
  } finally {
    loading.value = false
  }
}

async function assign(d) {
  if (!chosen.value[d.id]) return
  try {
    await api.post(`/service-deliveries/${d.id}/assign`, { grafolog_id: chosen.value[d.id] })
    toast.push('Layanan ditugaskan.', 'success')
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menugaskan.')
  }
}

watch(status, load)
onMounted(load)
</script>

<template>
  <section class="asq">
    <div class="asq__head">
      <h2>Layanan Pendukung</h2>
      <select v-model="status" aria-label="Filter status layanan">
        <option value="menunggu_penugasan">Perlu ditugaskan</option>
        <option value="ditugaskan">Ditugaskan</option>
        <option value="dijadwalkan">Dijadwalkan</option>
        <option value="selesai">Selesai</option>
        <option value="all">Semua</option>
      </select>
    </div>
    <SkeletonLoader v-if="loading" variant="list" :rows="2" label="Memuat layanan..." />
    <p v-else-if="!list.length" class="asq__empty">Tidak ada layanan di tahap ini.</p>
    <ul v-else class="asq__list">
      <li v-for="d in list" :key="d.id" class="asq__item">
        <div class="asq__info">
          <strong>{{ TIPE_LAYANAN[d.component?.tipe] }} · {{ d.component?.nama }}</strong>
          <span
            >{{ d.user?.name }} ({{ d.user?.email }}) · {{ d.order?.product?.nama }} · pesanan #{{
              d.order_id
            }}</span
          >
          <span>
            {{ STATUS_LAYANAN[d.status] }}
            <template v-if="d.assignee"> · {{ d.assignee.name }}</template>
            <template v-if="d.scheduled_at"> · {{ formatTanggal(d.scheduled_at, true) }}</template>
          </span>
        </div>
        <div v-if="!['selesai', 'dibatalkan'].includes(d.status)" class="asq__assign">
          <select v-model="chosen[d.id]" :aria-label="`Grafolog untuk layanan ${d.id}`">
            <option :value="undefined" disabled>Pilih grafolog</option>
            <option v-for="g in grafologs" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
          <button
            type="button"
            class="btn btn--primary"
            :disabled="!chosen[d.id]"
            @click="assign(d)"
          >
            {{ d.assignee ? 'Ganti' : 'Tugaskan' }}
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.asq {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 32px;
}
.asq__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.asq__head h2 {
  margin: 0;
  font-size: 18px;
}
.asq__head select {
  width: auto;
}
.asq__empty {
  color: var(--color-text-soft);
}
.asq__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.asq__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.asq__info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  font-size: 14px;
  min-width: 0;
}
.asq__info span {
  color: var(--color-text-soft);
  font-size: 13px;
}
.asq__assign {
  display: flex;
  gap: 8px;
  align-items: center;
}
</style>
