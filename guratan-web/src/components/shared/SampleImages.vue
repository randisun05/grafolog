<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import api from '@/lib/api'
import { PHOTO_ACCEPT, PHOTO_HINT, partitionPhotos } from '@/lib/handwritingPhoto'
import { useToast } from '@/composables/useToast'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

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

const isImage = (img) => (img.mime_type ?? '').startsWith('image/')

async function openPdf(image) {
  try {
    const { data } = await api.get(`/samples/${props.sampleId}/images/${image.id}`, {
      responseType: 'blob',
    })
    window.open(URL.createObjectURL(new Blob([data], { type: 'application/pdf' })), '_blank')
  } catch {
    toast.push('Gagal membuka berkas scan.')
  }
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
    await Promise.all(data.filter(isImage).map(loadPreview))
  } catch {
    toast.push('Gagal memuat berkas scan.')
  } finally {
    loading.value = false
  }
}

async function onFiles(event) {
  const picked = Array.from(event.target.files ?? [])
  event.target.value = ''
  if (!picked.length) return
  const { ok: files, errors: invalid } = await partitionPhotos(picked)
  invalid.forEach((msg) => toast.push(msg))
  if (!files.length) return
  uploading.value = true
  try {
    const formData = new FormData()
    files.forEach((f) => formData.append('images[]', f))
    await api.post(`/samples/${props.sampleId}/images`, formData)
    toast.push('Scan berhasil diunggah.', 'success')
    await load()
    emit('changed', images.value.length)
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(
      errors
        ? Object.values(errors).flat()[0]
        : (e.response?.data?.message ?? 'Gagal mengunggah scan.'),
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
    toast.push(e.response?.data?.message ?? 'Gagal menghapus scan.')
  }
}

onMounted(load)
watch(() => props.sampleId, load)
onBeforeUnmount(revokePreviews)
</script>

<template>
  <div class="sample-images">
    <SkeletonLoader v-if="loading" variant="text" :rows="2" label="Memuat berkas scan..." />
    <p v-else-if="!images.length" class="sample-images__note">
      Belum ada berkas scan tulisan tangan.
    </p>
    <div v-else class="sample-images__grid">
      <figure v-for="img in images" :key="img.id" class="sample-images__item">
        <button
          type="button"
          class="sample-images__thumb"
          @click="isImage(img) ? (viewing = img) : openPdf(img)"
        >
          <img
            v-if="isImage(img) && previews[img.id]"
            :src="previews[img.id]"
            :alt="img.original_name ?? 'Foto tulisan tangan'"
          />
          <span v-else-if="!isImage(img)" class="sample-images__pdf"
            >PDF<small
              >{{ img.page_count ?? '?' }} hlm<template v-if="img.page_width_mm">
                · {{ img.page_width_mm }}×{{ img.page_height_mm }} mm</template
              ></small
            ></span
          >
        </button>
        <figcaption>
          <span>{{ img.original_name }}</span>
          <button v-if="canUpload" type="button" class="sample-images__remove" @click="remove(img)">
            Hapus
          </button>
        </figcaption>
      </figure>
    </div>

    <p v-if="canUpload" class="sample-images__note">{{ PHOTO_HINT }}</p>
    <label v-if="canUpload" class="btn sample-images__upload">
      {{ uploading ? 'Mengunggah...' : 'Tambah Scan (PDF)' }}
      <input
        type="file"
        :accept="PHOTO_ACCEPT"
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
.sample-images__pdf {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  font-weight: 700;
  color: var(--color-seal);
  gap: 2px;
}
.sample-images__pdf small {
  font-weight: 400;
  font-size: 10.5px;
  color: var(--color-text-soft);
  text-align: center;
}
</style>
