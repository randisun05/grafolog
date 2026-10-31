<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatRupiah, STATUS_PESANAN } from '@/lib/format'

/** Dashboard klien: pesanan berjalan + status grafolog + chat + laporan terbaru (resume). */
const orders = ref([])
const unread = ref({})
const loading = ref(true)
const GSTATE = {
  menunggu_konfirmasi: 'Menunggu konfirmasi grafolog',
  menunggu_pilih: 'Perlu memilih grafolog lain',
  menunggu_admin: 'Tim kami mencarikan grafolog',
  diterima: 'Diterima grafolog',
}
const active = computed(() =>
  orders.value.filter((o) => !['dibatalkan', 'dikembalikan'].includes(o.status)).slice(0, 5),
)

onMounted(async () => {
  try {
    const [o, u] = await Promise.all([api.get('/orders'), api.get('/chat/unread')])
    orders.value = o.data
    unread.value = u.data.by_order ?? {}
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section v-if="!loading && active.length" class="co">
    <h2>Pesanan Anda</h2>
    <ul>
      <li v-for="o in active" :key="o.id">
        <div>
          <strong>{{ o.product?.nama }}</strong>
          <span class="co__meta">
            #{{ o.id }} · {{ formatRupiah(o.amount) }} · {{ STATUS_PESANAN[o.status] ?? o.status }}
            <template v-if="o.grafolog_state">
              · {{ GSTATE[o.grafolog_state]
              }}<template v-if="o.grafolog?.name"> ({{ o.grafolog.name }})</template></template
            >
          </span>
        </div>
        <div class="co__actions">
          <RouterLink v-if="o.report_id" :to="`/reports/${o.report_id}`" class="btn btn--primary"
            >Laporan & ringkasan</RouterLink
          >
          <RouterLink
            v-if="o.grafolog_state === 'diterima'"
            :to="`/pesanan/${o.id}/chat`"
            class="btn"
          >
            Chat <span v-if="unread[o.id]" class="co__badge">{{ unread[o.id] }}</span>
          </RouterLink>
          <RouterLink
            v-if="o.grafolog_state === 'menunggu_pilih'"
            to="/riwayat"
            class="btn btn--primary"
            >Pilih grafolog</RouterLink
          >
          <RouterLink v-else to="/riwayat" class="btn">Detail</RouterLink>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.co {
  margin: 20px 0;
}
.co ul {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.co li {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  justify-content: space-between;
  align-items: center;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 10px 14px;
}
.co__meta {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.co__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.co__badge {
  background: var(--color-seal);
  color: #fff;
  border-radius: 999px;
  padding: 0 6px;
  font-size: 11px;
  margin-left: 4px;
}
</style>
