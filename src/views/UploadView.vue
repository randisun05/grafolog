<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import UploadDropzone from '@/components/upload/UploadDropzone.vue'

const router = useRouter()
const file = ref(null)
const loading = ref(false)
const error = ref('')

async function submit() {
  if (!file.value) return
  loading.value = true
  error.value = ''

  const formData = new FormData()
  formData.append('tier', 'rapid')
  formData.append('image', file.value)

  try {
    const { data } = await api.post('/samples', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    router.push({ name: 'hasil-rapid', params: { sampleId: data.id } })
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal mengunggah foto.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="upload-view">
    <h1>Unggah Foto Tulisan Tangan</h1>
    <p class="upload-view__note">
      Tier Rapid saat ini menggunakan skor placeholder (analisis tulisan tangan otomatis belum
      tersedia). Untuk laporan mendalam, hubungi grafolog kami untuk sesi Comprehensive/Master.
    </p>

    <UploadDropzone v-model="file" />

    <p v-if="error" class="error">{{ error }}</p>

    <button type="button" class="btn btn--primary" :disabled="!file || loading" @click="submit">
      {{ loading ? 'Mengunggah...' : 'Unggah & Lihat Hasil' }}
    </button>
  </div>
</template>

<style scoped>
.upload-view {
  max-width: 480px;
  width: 100%;
  margin: 0 auto;
}
.upload-view__note {
  background: var(--color-seal-soft);
  border: 1px solid var(--color-seal);
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--color-seal-dark);
  margin-bottom: 20px;
}
.btn--primary {
  padding: 10px 20px;
  margin-top: 16px;
}
</style>
