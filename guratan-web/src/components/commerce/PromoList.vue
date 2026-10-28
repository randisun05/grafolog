<script setup>
// Daftar promo yang tersedia untuk grafolog (kode publik, aktif, saluran grafolog). Hanya daftar.
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatRupiah } from '@/lib/format'

const codes = ref([])

function label(c) {
  return (
    c.description ||
    (c.type === 'percentage' ? `Hemat ${c.value}%` : `Hemat ${formatRupiah(c.value)}`)
  )
}

onMounted(async () => {
  try {
    codes.value = (await api.get('/promos', { params: { audience: 'grafolog' } })).data.codes
  } catch {
    // Promo bersifat tambahan - halaman tetap jalan bila gagal dimuat.
  }
})
</script>

<template>
  <section v-if="codes.length" class="promo-list">
    <h2 class="promo-list__title">Promo Tersedia untuk Anda</h2>
    <ul class="promo-list__items">
      <li v-for="c in codes" :key="c.code" class="promo-list__item">
        <span class="promo-list__code">{{ c.code }}</span>
        <span class="promo-list__desc">{{ label(c) }}</span>
        <span class="promo-list__until">
          {{ c.products?.length ? `Berlaku: ${c.products.join(', ')}` : 'Berlaku: semua produk' }}
          <template v-if="c.valid_until">
            · s.d. {{ new Date(c.valid_until).toLocaleDateString('id-ID') }}
          </template>
        </span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.promo-list {
  margin-bottom: 24px;
}
.promo-list__title {
  font-size: 16px;
  margin: 0 0 8px;
}
.promo-list__items {
  list-style: none;
  padding: 0;
  margin: 0;
}
.promo-list__item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border);
}
.promo-list__code {
  font-family: monospace;
  font-weight: 700;
  padding: 2px 8px;
  border: 1px dashed var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
}
.promo-list__desc {
  flex: 1 1 160px;
  font-size: 13.5px;
}
.promo-list__until {
  font-size: 12px;
  color: var(--color-text-soft);
}
</style>
