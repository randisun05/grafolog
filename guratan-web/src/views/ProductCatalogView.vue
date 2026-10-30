<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatRupiah, TIPE_LAYANAN } from '@/lib/format'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

/** Katalog produk publik (tanpa login): jalur "lihat produk dulu, baru daftar". */
const products = ref([])
const loading = ref(true)
const error = ref('')
const utama = computed(() => products.value.filter((p) => p.kategori === 'utama'))
const pendukung = computed(() => products.value.filter((p) => p.kategori !== 'utama'))
const MODE = { online: 'Online', offline: 'Tatap muka' }

onMounted(async () => {
  try {
    products.value = (await api.get('/products')).data
  } catch {
    error.value = 'Gagal memuat produk.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="catalog">
    <h1>Produk & Layanan</h1>
    <p class="catalog__lead">
      Analisis tulisan tangan oleh grafolog bersertifikat, plus layanan pendukung. Anda bisa melihat
      semua layanan tanpa akun; daftar atau masuk saat siap memesan.
    </p>
    <SkeletonLoader v-if="loading" variant="cards" :rows="3" label="Memuat produk..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else>
      <template
        v-for="g in [
          { t: 'Layanan utama', l: utama },
          { t: 'Layanan pendukung', l: pendukung },
        ]"
        :key="g.t"
      >
        <h2 v-if="g.l.length">{{ g.t }}</h2>
        <div v-if="g.l.length" class="catalog__grid">
          <RouterLink v-for="p in g.l" :key="p.id" :to="`/produk/${p.kode}`" class="catalog__card">
            <span v-if="p.promo_label" class="catalog__badge">{{ p.promo_label }}</span>
            <strong>{{ p.nama }}</strong>
            <span class="catalog__price">{{ formatRupiah(p.price) }}</span>
            <span class="catalog__sum">{{ p.ringkasan }}</span>
            <span class="catalog__tags">
              <span v-for="m in p.modes" :key="m">{{ MODE[m] }}</span>
              <span v-for="c in p.components" :key="c.nama">{{
                TIPE_LAYANAN[c.tipe] ?? c.tipe
              }}</span>
            </span>
          </RouterLink>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.catalog__lead {
  color: var(--color-text-soft);
  max-width: 640px;
}
.catalog h2 {
  font-size: 17px;
  margin: 18px 0 10px;
}
.catalog__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}
.catalog__card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  text-decoration: none;
  color: var(--color-ink);
}
.catalog__card:hover {
  border-color: var(--color-seal);
}
.catalog__badge {
  align-self: flex-start;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--color-gold-soft);
}
.catalog__price {
  font-weight: 700;
  color: var(--color-seal);
}
.catalog__sum {
  font-size: 13.5px;
  color: var(--color-text-soft);
}
.catalog__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: auto;
}
.catalog__tags span {
  font-size: 11.5px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--color-paper-alt);
  border: 1px solid var(--color-border);
}
</style>
