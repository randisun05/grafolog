<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import { formatRupiah, formatTanggal } from '@/lib/format'

/** Antrean permintaan refund klien (SOP Fase E). Dana dikembalikan manual via dashboard DOKU. */
const toast = useToast()
const list = ref([])
const notes = ref({})
const deciding = ref(null)

async function load() {
  try {
    list.value = (await api.get('/admin/refunds')).data
  } catch {
    list.value = []
  }
}

async function decide(o, decision) {
  if (!notes.value[o.id]?.trim()) {
    toast.push('Isi catatan keputusan untuk klien.')
    return
  }
  deciding.value = o.id
  try {
    await api.post(`/admin/orders/${o.id}/refund`, { decision, note: notes.value[o.id] })
    toast.push(
      decision === 'approve'
        ? 'Refund disetujui - proses pengembalian dana di DOKU.'
        : 'Refund ditolak.',
      'success',
    )
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan keputusan.')
  } finally {
    deciding.value = null
  }
}

onMounted(load)
</script>

<template>
  <section v-if="list.length" class="arq">
    <h2>Permintaan Refund ({{ list.length }})</h2>
    <ul>
      <li v-for="o in list" :key="o.id" class="arq__item">
        <div class="arq__info">
          <strong>#{{ o.id }} · {{ o.product?.nama }} · {{ formatRupiah(o.amount) }}</strong>
          <span
            >{{ o.user?.name }} ({{ o.user?.email }}) · diajukan
            {{ formatTanggal(o.refund_requested_at, true) }}</span
          >
          <span class="arq__reason">“{{ o.refund_reason }}”</span>
        </div>
        <div class="arq__actions">
          <textarea
            v-model="notes[o.id]"
            rows="2"
            placeholder="Catatan untuk klien (wajib)"
          ></textarea>
          <div class="arq__buttons">
            <button
              type="button"
              class="btn btn--primary"
              :disabled="deciding === o.id"
              @click="decide(o, 'approve')"
            >
              Setujui refund
            </button>
            <button
              type="button"
              class="btn"
              :disabled="deciding === o.id"
              @click="decide(o, 'reject')"
            >
              Tolak
            </button>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.arq {
  margin-bottom: 24px;
}
.arq h2 {
  font-size: 17px;
}
.arq ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
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
.arq__reason {
  font-style: italic;
}
.arq__actions {
  min-width: 260px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.arq__buttons {
  display: flex;
  gap: 8px;
}
</style>
