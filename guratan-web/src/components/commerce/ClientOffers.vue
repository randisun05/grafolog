<script setup>
// Promo aktif + layanan (utama & pendukung) di dashboard klien - supaya klien yang sudah punya
// laporan melihat langkah lanjutan: wawancara, konsultasi, konseling, program pengembangan.
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { formatRupiah, TIPE_LAYANAN } from '@/lib/format'

const products = ref([])
const codes = ref([])
const partners = ref([])

// Produk afiliasi: catat klik dulu, lalu buka tautan mitra di tab baru.
async function openPartner(p) {
  // Buka tab dulu (masih dalam gesture klik - tidak diblokir popup blocker), putus opener.
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

onMounted(async () => {
  try {
    const [{ data: list }, { data: promo }] = await Promise.all([
      api.get('/products'),
      api.get('/promos'),
    ])
    products.value = list
    codes.value = promo.codes
    partners.value = (await api.get('/affiliate-products')).data
  } catch {
    // Penawaran bersifat tambahan - dashboard tetap jalan bila gagal dimuat.
  }
})

const supporting = computed(() => products.value.filter((p) => p.kategori !== 'utama'))
const main = computed(() => products.value.find((p) => p.kategori === 'utama'))
</script>

<template>
  <section v-if="products.length" class="offers">
    <div v-if="codes.length" class="offers__promos">
      <strong>Promo untuk Anda</strong>
      <RouterLink
        v-for="c in codes"
        :key="c.code"
        :to="`/pesan?kode=${c.code}`"
        class="offers__code"
      >
        <span>{{ c.code }}</span>
        {{
          c.description ||
          (c.type === 'percentage' ? `Hemat ${c.value}%` : `Hemat ${formatRupiah(c.value)}`)
        }}
      </RouterLink>
    </div>

    <h2>Lanjutkan perjalanan Anda</h2>
    <div class="offers__grid">
      <RouterLink
        v-if="main"
        :to="`/pesan?produk=${main.kode}`"
        class="offers__card offers__card--main"
      >
        <span class="offers__label">{{ main.promo_label || 'Layanan utama' }}</span>
        <strong>{{ main.nama }}</strong>
        <span class="offers__desc">{{ main.ringkasan }}</span>
        <span class="offers__price">{{ formatRupiah(main.price) }}</span>
      </RouterLink>
      <RouterLink
        v-for="p in supporting"
        :key="p.id"
        :to="`/pesan?produk=${p.kode}`"
        class="offers__card"
      >
        <span class="offers__label">{{ TIPE_LAYANAN[p.components[0]?.tipe] ?? 'Layanan' }}</span>
        <strong>{{ p.nama }}</strong>
        <span class="offers__desc">{{ p.ringkasan }}</span>
        <span class="offers__price">{{ formatRupiah(p.price) }}</span>
      </RouterLink>
    </div>

    <div class="offers__explore">
      <RouterLink to="/cek-tulisan">Cek tulisan gratis</RouterLink>
      <RouterLink to="/kuis">Kuis ciri tulisan</RouterLink>
      <RouterLink to="/artikel">Artikel</RouterLink>
    </div>

    <template v-if="partners.length">
      <h2>Rekomendasi mitra · <RouterLink to="/produk-mitra">lihat semua</RouterLink></h2>
      <p class="offers__disclosure">
        Produk pihak ketiga. Guratan dapat menerima komisi dari pembelian melalui tautan ini.
      </p>
      <div class="offers__grid">
        <button
          v-for="p in partners"
          :key="p.id"
          type="button"
          class="offers__card"
          @click="openPartner(p)"
        >
          <span class="offers__label">{{ p.mitra }}</span>
          <strong>{{ p.nama }}</strong>
          <span class="offers__desc">{{ p.ringkasan }}</span>
          <span class="offers__desc">Kunjungi mitra ↗</span>
        </button>
      </div>
    </template>
  </section>
</template>

<style scoped>
.offers {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 28px;
}
.offers h2 {
  margin: 0;
  font-size: 18px;
}
.offers__promos {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border: 1px dashed var(--color-gold);
  border-radius: var(--radius-md);
  background: var(--color-gold-soft);
  font-size: 14px;
}
.offers__code {
  display: inline-flex;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--color-surface);
  border: 1px solid var(--color-gold);
  color: var(--color-text);
  text-decoration: none;
  font-size: 12.5px;
}
.offers__code span {
  font-weight: 700;
}
.offers__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}
.offers__card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  text-decoration: none;
}
.offers__card:hover {
  border-color: var(--color-seal);
}
.offers__card--main {
  border-color: var(--color-seal);
  background: var(--color-seal-soft);
}
.offers__label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-seal-dark);
}
.offers__desc {
  font-size: 13px;
  color: var(--color-text-soft);
}
.offers__price {
  margin-top: auto;
  font-weight: 600;
}
.offers__explore {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 14px;
}
.offers__disclosure {
  margin: -8px 0 0;
  font-size: 12px;
  color: var(--color-text-soft);
}
button.offers__card {
  text-align: left;
  font: inherit;
  cursor: pointer;
}
</style>
