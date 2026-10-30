<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatTanggal } from '@/lib/format'
import { useToast } from '@/composables/useToast'

/** Admin: pesanan klien individu menurut tahap penentuan grafolog + rekomendasi sistem. */
const toast = useToast()
const state = ref('menunggu_admin')
const rows = ref([])
const loading = ref(false)
const STATES = {
  menunggu_admin: 'Perlu rekomendasi admin',
  menunggu_konfirmasi: 'Menunggu konfirmasi grafolog',
  menunggu_pilih: 'Menunggu klien memilih ulang',
  diterima: 'Diterima grafolog',
}
const picked = ref({})

async function load() {
  loading.value = true
  try {
    rows.value = (await api.get('/admin/client-orders', { params: { state: state.value } })).data
  } finally {
    loading.value = false
  }
}
async function assign(o, gid) {
  try {
    await api.post(`/admin/client-orders/${o.id}/assign`, { grafolog_id: gid })
    toast.push('Grafolog ditugaskan.', 'success')
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menugaskan.')
  }
}
onMounted(load)
</script>

<template>
  <section class="aco">
    <h2>Pesanan klien: penentuan grafolog</h2>
    <select v-model="state" aria-label="Tahap" @change="load">
      <option v-for="(l, k) in STATES" :key="k" :value="k">{{ l }}</option>
    </select>
    <p v-if="!loading && !rows.length" class="aco__hint">Tidak ada pesanan pada tahap ini.</p>
    <ul class="aco__list">
      <li v-for="o in rows" :key="o.id" class="aco__item">
        <div>
          <strong>#{{ o.id }} · {{ o.product }}</strong>
          <span class="aco__meta">
            {{ o.client }} · {{ o.delivery_mode === 'offline' ? 'Tatap muka' : 'Online' }}
            <template v-if="o.location_note"> ({{ o.location_note }})</template>
            <template v-if="o.scheduled_for"> · {{ formatTanggal(o.scheduled_for) }}</template>
            · percobaan {{ o.attempts }}
            <template v-if="o.grafolog"> · {{ o.grafolog }}</template>
            <template v-if="o.deadline"> · batas {{ formatTanggal(o.deadline, true) }}</template>
          </span>
          <span v-for="(h, i) in o.history" :key="i" class="aco__meta"
            >• {{ h.grafolog }}: {{ h.status
            }}<template v-if="h.note"> ({{ h.note }})</template></span
          >
        </div>
        <div v-if="o.recommendations.length" class="aco__recs">
          <span class="aco__meta">Rekomendasi sistem:</span>
          <div v-for="r in o.recommendations" :key="r.id" class="aco__rec">
            <span>
              <strong>{{ r.name }}</strong> <small>skor {{ r.score }}</small>
              <small class="aco__meta">{{ r.why.join('; ') }}</small>
            </span>
            <button type="button" class="btn btn--primary" @click="assign(o, r.id)">
              Tugaskan
            </button>
          </div>
        </div>
        <p v-else-if="state === 'menunggu_admin'" class="aco__hint">
          Tidak ada grafolog tersedia yang cocok. Cek ketersediaan bulan itu atau tugaskan manual
          dari daftar pesanan.
        </p>
        <div v-if="state !== 'diterima'" class="aco__manual">
          <input
            v-model.number="picked[o.id]"
            type="number"
            min="1"
            placeholder="ID grafolog"
            aria-label="ID grafolog"
          />
          <button
            type="button"
            class="btn"
            :disabled="!picked[o.id]"
            @click="assign(o, picked[o.id])"
          >
            Tugaskan manual
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.aco {
  margin: 18px 0;
}
.aco select {
  width: auto;
}
.aco__list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.aco__item {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.aco__meta {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.aco__hint {
  color: var(--color-text-soft);
  font-size: 13px;
}
.aco__rec {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
  border-top: 1px dashed var(--color-border);
}
.aco__manual {
  display: flex;
  gap: 8px;
}
.aco__manual input {
  width: 120px;
}
</style>
