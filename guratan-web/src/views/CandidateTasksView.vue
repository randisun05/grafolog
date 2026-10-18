<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import SampleImages from '@/components/shared/SampleImages.vue'
import { PHOTO_HINT } from '@/lib/handwritingPhoto'

/** Jalur kandidat: unggah sendiri scan tulisan tangan untuk asesmen yang dipesan perusahaan. */
const tasks = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  try {
    tasks.value = (await api.get('/candidate/tasks')).data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat tugas.'
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="ct">
    <h1>Tugas Asesmen</h1>
    <p class="ct__hint">
      Perusahaan yang melamar Anda memesan asesmen grafologi. Unggah hasil scan tulisan tangan Anda
      di sini (atau serahkan ke tim HR perusahaan). Hasil analisis menjadi milik perusahaan dan
      tidak ditampilkan di akun ini. Data Anda diproses sesuai
      <RouterLink to="/privasi" target="_blank">Kebijakan Privasi</RouterLink>.
    </p>
    <SkeletonLoader v-if="loading" variant="cards" :rows="2" label="Memuat tugas..." />
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!tasks.length" class="ct__hint">Belum ada tugas asesmen untuk Anda.</p>
    <article v-for="t in tasks" :key="t.order_id" class="ct__card">
      <h2>{{ t.product }} · {{ t.company }}</h2>
      <p v-if="!t.paid" class="ct__hint">
        Menunggu pembayaran perusahaan - unggah dibuka setelah itu.
      </p>
      <p v-else-if="t.done" class="ct__hint">Asesmen selesai. Terima kasih!</p>
      <template v-else-if="t.needs_scan">
        <p class="ct__hint">Panduan: {{ PHOTO_HINT }}</p>
        <SampleImages :sample-id="t.sample_id" :can-upload="true" @changed="load" />
      </template>
      <p v-else class="ct__hint">
        Tidak ada berkas yang perlu Anda unggah - tim akan menghubungi Anda.
      </p>
    </article>
  </div>
</template>

<style scoped>
.ct {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 720px;
}
.ct__hint {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin: 0;
}
.ct__card {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.ct__card h2 {
  font-size: 17px;
  margin: 0 0 8px;
}
</style>
