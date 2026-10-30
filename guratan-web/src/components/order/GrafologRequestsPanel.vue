<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatTanggal } from '@/lib/format'
import { useToast } from '@/composables/useToast'

/** Grafolog: permintaan penanganan pesanan klien (jawab dalam 24 jam). */
const toast = useToast()
const data = ref({ pending: [], history: [], accepted: [], confirm_hours: 24 })
const notes = ref({})
const busy = ref(0)
const STATUS = {
  diterima: 'Diterima',
  ditolak: 'Ditolak',
  kedaluwarsa: 'Lewat waktu',
  dibatalkan: 'Dibatalkan',
}

async function load() {
  data.value = (await api.get('/grafolog/requests')).data
}
async function respond(r, decision) {
  busy.value = r.id
  try {
    await api.post(`/grafolog/requests/${r.id}/respond`, {
      decision,
      note: notes.value[r.id] || undefined,
    })
    toast.push(decision === 'accept' ? 'Pesanan diterima.' : 'Pesanan ditolak.', 'success')
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengirim jawaban.')
    await load()
  } finally {
    busy.value = 0
  }
}
const hoursLeft = (r) => Math.max(0, Math.round((new Date(r.expires_at) - Date.now()) / 3600000))
onMounted(load)
</script>

<template>
  <section v-if="data.pending.length || data.history.length || data.accepted.length" class="grp">
    <h2>Permintaan pesanan klien</h2>
    <p v-if="data.pending.length" class="grp__hint">
      Jawab dalam {{ data.confirm_hours }} jam. Bila tidak dijawab, permintaan dialihkan dan klien
      memilih grafolog lain.
    </p>
    <ul class="grp__list">
      <li v-for="r in data.pending" :key="r.id" class="grp__item">
        <div>
          <strong>#{{ r.order.id }} · {{ r.order.product }}</strong>
          <span class="grp__meta">
            Klien: {{ r.order.client }} ·
            {{ r.order.delivery_mode === 'offline' ? 'Tatap muka' : 'Online' }}
            <template v-if="r.order.location_note"> ({{ r.order.location_note }})</template>
            <template v-if="r.order.scheduled_for">
              · Tanggal {{ formatTanggal(r.order.scheduled_for) }}</template
            >
            <template v-if="r.order.upload_deadline">
              · Klien unggah paling lambat {{ formatTanggal(r.order.upload_deadline) }}</template
            >
          </span>
          <span class="grp__left">Sisa ± {{ hoursLeft(r) }} jam</span>
        </div>
        <div class="grp__actions">
          <input
            v-model="notes[r.id]"
            type="text"
            maxlength="255"
            placeholder="Catatan (opsional, bila menolak)"
          />
          <button
            type="button"
            class="btn btn--primary"
            :disabled="busy === r.id"
            @click="respond(r, 'accept')"
          >
            Terima
          </button>
          <button
            type="button"
            class="btn"
            :disabled="busy === r.id"
            @click="respond(r, 'decline')"
          >
            Tolak
          </button>
        </div>
      </li>
    </ul>
    <template v-if="data.accepted.length">
      <h3>Pesanan klien yang Anda tangani</h3>
      <ul class="grp__hist">
        <li v-for="o in data.accepted" :key="o.id">
          #{{ o.id }} {{ o.product }} — {{ o.client }}
          <RouterLink :to="`/pesanan/${o.id}/chat`" class="btn">
            Chat <span v-if="o.unread" class="grp__badge">{{ o.unread }}</span>
          </RouterLink>
        </li>
      </ul>
    </template>
    <details v-if="data.history.length">
      <summary>Riwayat jawaban</summary>
      <ul class="grp__hist">
        <li v-for="r in data.history" :key="r.id">
          #{{ r.order.id }} {{ r.order.product }} — {{ STATUS[r.status] ?? r.status }}
          <small v-if="r.note">({{ r.note }})</small>
        </li>
      </ul>
    </details>
  </section>
</template>

<style scoped>
.grp {
  margin-bottom: 20px;
}
.grp__hint {
  color: var(--color-text-soft);
  font-size: 13px;
}
.grp__list,
.grp__hist {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.grp__item {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  justify-content: space-between;
  border: 1px solid var(--color-gold);
  background: var(--color-gold-soft);
  border-radius: var(--radius-md);
  padding: 10px 14px;
}
.grp__meta,
.grp__left {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.grp__left {
  color: var(--color-seal);
  font-weight: 600;
}
.grp__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.grp__actions input {
  width: 220px;
}
.grp__badge {
  background: var(--color-seal);
  color: #fff;
  border-radius: 999px;
  padding: 0 6px;
  font-size: 11px;
  margin-left: 4px;
}
</style>
