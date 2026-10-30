<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { formatRupiah, TIPE_LAYANAN } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

/** Detail produk publik + CTA: masuk/daftar membawa pilihan produk sampai ke wizard pesan. */
const props = defineProps({ kode: { type: String, required: true } })
const route = useRoute()
const auth = useAuthStore()
const p = ref(null)
const loading = ref(true)
const notFound = ref(false)
const MODE = { online: 'Online (tanpa perlu bertemu)', offline: 'Tatap muka (offline)' }
const orderPath = computed(() => `/pesan?produk=${props.kode}`)
const redirectQuery = computed(() => ({ redirect: orderPath.value }))

onMounted(async () => {
  try {
    p.value = (await api.get(`/products/${props.kode}`)).data
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})
void route
</script>

<template>
  <div class="detail">
    <RouterLink to="/produk" class="detail__back">‹ Semua produk</RouterLink>
    <SkeletonLoader v-if="loading" variant="report" :rows="3" label="Memuat produk..." />
    <p v-else-if="notFound" class="error">Produk tidak ditemukan atau sedang tidak dijual.</p>
    <template v-else>
      <h1>{{ p.nama }}</h1>
      <p class="detail__price">{{ formatRupiah(p.price) }}</p>
      <p v-if="p.ringkasan" class="detail__sum">{{ p.ringkasan }}</p>
      <p v-if="p.deskripsi" class="detail__desc">{{ p.deskripsi }}</p>

      <h2>Yang Anda dapatkan</h2>
      <ul>
        <li v-for="c in p.components" :key="c.nama">
          {{ c.nama }} <small>({{ TIPE_LAYANAN[c.tipe] ?? c.tipe }})</small>
        </li>
      </ul>

      <h2>Pelaksanaan</h2>
      <ul>
        <li v-for="m in p.modes" :key="m">{{ MODE[m] }}</li>
      </ul>

      <template v-if="p.requirements.length || p.requires_handwriting">
        <h2>Yang perlu Anda siapkan</h2>
        <ul>
          <li v-if="p.requires_handwriting">
            Hasil scan tulisan tangan (PDF A4/F4, 300 dpi) - petunjuk lengkap ada di langkah
            pemesanan.
          </li>
          <li v-for="r in p.requirements" :key="r.judul">
            <strong>{{ r.judul }}</strong
            ><span v-if="r.keterangan"> - {{ r.keterangan }}</span>
          </li>
        </ul>
      </template>

      <p class="detail__note">
        Laporan adalah <strong>alat insight reflektif</strong>, bukan diagnosis psikologis.
      </p>

      <div class="detail__cta">
        <RouterLink
          v-if="auth.isAuthenticated && auth.isClient"
          :to="orderPath"
          class="btn btn--primary"
        >
          Pesan sekarang
        </RouterLink>
        <template v-else-if="!auth.isAuthenticated">
          <RouterLink :to="{ name: 'register', query: redirectQuery }" class="btn btn--primary">
            Daftar & pesan
          </RouterLink>
          <RouterLink :to="{ name: 'login', query: redirectQuery }" class="btn"
            >Sudah punya akun? Masuk</RouterLink
          >
        </template>
        <p v-else class="detail__note">Pemesanan layanan ini hanya untuk akun klien individu.</p>
      </div>
    </template>
  </div>
</template>

<style scoped>
.detail {
  max-width: 680px;
}
.detail__back {
  font-size: 13px;
}
.detail__price {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-seal);
  margin: 0 0 8px;
}
.detail__sum {
  font-weight: 600;
}
.detail__desc {
  color: var(--color-text-soft);
  white-space: pre-line;
}
.detail h2 {
  font-size: 16px;
  margin: 18px 0 6px;
}
.detail__note {
  font-size: 13px;
  color: var(--color-text-soft);
}
.detail__cta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
}
</style>
