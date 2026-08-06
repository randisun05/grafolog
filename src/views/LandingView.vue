<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'

// Nilai default = fallback kalau API gagal/lambat, BUKAN dihapus setelah
// CMS ada - halaman ini tidak boleh kosong hanya karena backend down.
const content = ref({
  landing_eyebrow: 'Analisis grafologi tepercaya',
  landing_tagline:
    'Analisis kepribadian berbasis grafologi - laporan komprehensif dari tulisan tangan, disusun oleh grafolog bersertifikat.',
  landing_cta_label: 'Mulai Sekarang',
})

onMounted(async () => {
  try {
    const { data } = await api.get('/content')
    content.value = { ...content.value, ...data }
  } catch {
    // Diam-diam pakai fallback di atas - halaman utama tidak boleh error
    // ke pengunjung cuma karena CMS gagal dimuat.
  }
})
</script>

<template>
  <div class="landing">
    <p class="landing__eyebrow">{{ content.landing_eyebrow }}</p>
    <h1>Guratan</h1>
    <p class="landing__tagline">{{ content.landing_tagline }}</p>
    <div class="landing__actions">
      <RouterLink to="/register" class="btn btn--primary">{{ content.landing_cta_label }}</RouterLink>
      <RouterLink to="/login" class="btn">Masuk</RouterLink>
    </div>
  </div>
</template>

<style scoped>
.landing {
  text-align: center;
  padding: 60px 16px;
}
.landing__eyebrow {
  font-family: var(--font-accent);
  font-size: 20px;
  color: var(--color-seal);
  margin-bottom: 4px;
}
.landing h1 {
  font-size: 40px;
  margin-bottom: 12px;
}
.landing__tagline {
  color: var(--color-text-soft);
  max-width: 480px;
  margin: 0 auto 28px;
  line-height: 1.5;
}
.landing__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: center;
}
</style>
