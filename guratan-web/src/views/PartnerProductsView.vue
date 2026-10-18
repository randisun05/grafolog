<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

/** Etalase produk mitra (afiliasi). Klik dicatat lalu membuka halaman mitra di tab baru. */
const products = ref([])
const loading = ref(true)
const kategori = ref('')

const categories = computed(() => [...new Set(products.value.map((p) => p.kategori))].sort())
const shown = computed(() =>
  kategori.value ? products.value.filter((p) => p.kategori === kategori.value) : products.value,
)

onMounted(async () => {
  try {
    products.value = (await api.get('/affiliate-products')).data
  } finally {
    loading.value = false
  }
})

async function open(p) {
  // Tab dibuka dulu (masih dalam gesture klik agar tidak diblokir), putus opener, baru diarahkan.
  const tab = window.open('', '_blank')
  if (tab) tab.opener = null
  try {
    const { data } = await api.post(`/affiliate-products/${p.id}/click`)
    if (tab) tab.location.href = data.url
    else window.location.href = data.url
  } catch {
    tab?.close()
  }
}
</script>

<template>
  <div class="pp">
    <h1>Produk Mitra</h1>
    <p class="pp__hint">
      Produk pihak ketiga pilihan yang mendukung perjalanan Anda. Guratan dapat menerima komisi dari
      pembelian melalui tautan di halaman ini; harga dan pembelian sepenuhnya di toko mitra.
    </p>
    <div v-if="categories.length > 1" class="pp__filters">
      <button
        type="button"
        :class="['pp__chip', { 'pp__chip--on': !kategori }]"
        @click="kategori = ''"
      >
        Semua
      </button>
      <button
        v-for="k in categories"
        :key="k"
        type="button"
        :class="['pp__chip', { 'pp__chip--on': kategori === k }]"
        @click="kategori = k"
      >
        {{ k }}
      </button>
    </div>
    <SkeletonLoader v-if="loading" variant="cards" :rows="3" label="Memuat produk..." />
    <p v-else-if="!shown.length" class="pp__hint">Belum ada produk mitra.</p>
    <div class="pp__grid">
      <article v-for="p in shown" :key="p.id" class="pp__card">
        <img
          v-if="p.image_url"
          :src="p.image_url"
          :alt="p.nama"
          loading="lazy"
          referrerpolicy="no-referrer"
        />
        <div v-else class="pp__noimg" aria-hidden="true">{{ p.nama.slice(0, 1) }}</div>
        <span class="pp__mitra">{{ p.mitra }}</span>
        <strong>{{ p.nama }}</strong>
        <span v-if="p.ringkasan" class="pp__desc">{{ p.ringkasan }}</span>
        <span v-if="p.harga" class="pp__price">{{ p.harga }}</span>
        <button type="button" class="btn" @click="open(p)">Lihat di toko mitra ↗</button>
      </article>
    </div>
  </div>
</template>

<style scoped>
.pp {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pp__hint {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin: 0;
}
.pp__filters {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.pp__chip {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  border-radius: 999px;
  padding: 5px 14px;
  cursor: pointer;
  text-transform: capitalize;
}
.pp__chip--on {
  background: var(--color-ink);
  border-color: var(--color-ink);
  color: var(--color-paper);
}
.pp__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
}
.pp__card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.pp__card img,
.pp__noimg {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: var(--radius-sm);
  background: var(--color-paper-alt);
}
.pp__noimg {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: var(--color-text-soft);
}
.pp__mitra {
  font-size: 11.5px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-seal);
  font-weight: 600;
}
.pp__desc {
  font-size: 13px;
  color: var(--color-text-soft);
}
.pp__price {
  font-weight: 600;
}
.pp__card .btn {
  margin-top: auto;
  align-self: flex-start;
}
</style>
