<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  sampleId: { type: [Number, String], required: true },
  canUpload: { type: Boolean, default: false },
})
const emit = defineEmits(['changed'])

const toast = useToast()
const images = ref([])
const previews = ref({})
const loading = ref(true)
const uploading = ref(false)
const viewing = ref(null)

function revokePreviews() {
  Object.values(previews.value).forEach((url) => URL.revokeObjectURL(url))
  previews.value = {}
}

async function loadPreview(image) {
  const { data } = await api.get(`/samples/${props.sampleId}/images/${image.id}`, {
    responseType: 'blob',
  })
  previews.value = { ...previews.value, [image.id]: URL.createObjectURL(data) }
}

async function load() {
  loading.value = true
  revokePreviews()
  try {
    const { data } = await api.get(`/samples/${props.sampleId}/images`)
    images.value = data
    await Promise.all(data.map(loadPreview))
  } catch {
    toast.push('Gagal memuat foto tulisan tangan.')
  } finally {
    loading.value = false
  }
}

async function onFiles(event) {
  const files = Array.from(event.target.files ?? [])
  event.target.value = ''
  if (!files.length) return
  uploading.value = true
  try {
    const formData = new FormData()
    files.forEach((f) => formData.append('images[]', f))
    await api.post(`/samples/${props.sampleId}/images`, formData)
    toast.push('Foto berhasil diunggah.', 'success')
    await load()
    emit('changed', images.value.length)
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(
      errors
        ? Object.values(errors).flat()[0]
        : (e.response?.data?.message ?? 'Gagal mengunggah foto.'),
    )
  } finally {
    uploading.value = false
  }
}

async function remove(image) {
  try {
    await api.delete(`/samples/${props.sampleId}/images/${image.id}`)
    await load()
    emit('changed', images.value.length)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menghapus foto.')
  }
}

onMounted(load)
watch(() => props.sampleId, load)
onBeforeUnmount(revokePreviews)
</script>

<template>
  <div class="sample-images">
    <p v-if="loading" class="sample-images__note">Memuat foto...</p>
    <p v-else-if="!images.length" class="sample-images__note">Belum ada foto tulisan tangan.</p>
    <div v-else class="sample-images__grid">
      <figure v-for="img in images" :key="img.id" class="sample-images__item">
        <button type="button" class="sample-images__thumb" @click="viewing = img">
          <img
            v-if="previews[img.id]"
            :src="previews[img.id]"
            :alt="img.original_name ?? 'Foto tulisan tangan'"
          />
        </button>
        <figcaption>
          <span>{{ img.original_name }}</span>
          <button v-if="canUpload" type="button" class="sample-images__remove" @click="remove(img)">
            Hapus
          </button>
        </figcaption>
      </figure>
    </div>

    <label v-if="canUpload" class="btn sample-images__upload">
      {{ uploading ? 'Mengunggah...' : 'Tambah Foto' }}
      <input
        type="file"
        accept="image/jpeg,image/png,image/webp,image/heic"
        multiple
        hidden
        :disabled="uploading"
        @change="onFiles"
      />
    </label>

    <div v-if="viewing" class="sample-images__lightbox" role="dialog" @click="viewing = null">
      <img :src="previews[viewing.id]" :alt="viewing.original_name ?? 'Foto tulisan tangan'" />
    </div>
  </div>
</template>

<style scoped>
.sample-images__note {
  font-size: 13px;
  color: var(--color-text-soft);
  margin: 4px 0 10px;
}
.sample-images__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 10px;
  margin-bottom: 10px;
}
.sample-images__item {
  margin: 0;
  font-size: 11px;
  color: var(--color-text-soft);
  overflow-wrap: anywhere;
}
.sample-images__thumb {
  display: block;
  width: 100%;
  aspect-ratio: 1;
  padding: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 6px);
  overflow: hidden;
  background: var(--color-paper-alt);
  cursor: zoom-in;
}
.sample-images__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.sample-images__item figcaption {
  display: flex;
  justify-content: space-between;
  gap: 4px;
  margin-top: 4px;
}
.sample-images__remove {
  background: none;
  border: none;
  color: var(--color-error);
  cursor: pointer;
  font-size: 11px;
  padding: 0;
}
.sample-images__upload {
  display: inline-block;
  cursor: pointer;
}
.sample-images__upload input {
  display: none;
}
.sample-images__lightbox {
  position: fixed;
  inset: 0;
  background: rgb(0 0 0 / 85%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
  cursor: zoom-out;
}
.sample-images__lightbox img {
  max-width: 100%;
  max-height: 100%;
}
</style>
