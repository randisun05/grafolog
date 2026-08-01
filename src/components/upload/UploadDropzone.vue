<script setup>
import { ref, computed, onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  modelValue: { type: File, default: null },
  accept: { type: String, default: 'image/png,image/jpeg' },
})
const emit = defineEmits(['update:modelValue'])

const isDragging = ref(false)
const inputRef = ref(null)
const previewUrl = ref(null)

watch(
  () => props.modelValue,
  (file) => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = file ? URL.createObjectURL(file) : null
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})

const hint = computed(() => 'JPG atau PNG, maks 5MB')

function setFile(fileList) {
  const file = fileList?.[0]
  if (file) emit('update:modelValue', file)
}

function onDrop(event) {
  isDragging.value = false
  setFile(event.dataTransfer.files)
}

function onChange(event) {
  setFile(event.target.files)
}

function openBrowser() {
  inputRef.value?.click()
}
</script>

<template>
  <div
    class="upload-dropzone"
    :class="{ 'upload-dropzone--active': isDragging }"
    role="button"
    tabindex="0"
    @click="openBrowser"
    @keydown.enter="openBrowser"
    @dragover.prevent="isDragging = true"
    @dragleave.prevent="isDragging = false"
    @drop.prevent="onDrop"
  >
    <input
      ref="inputRef"
      type="file"
      :accept="accept"
      class="upload-dropzone__input"
      @click.stop
      @change="onChange"
    />

    <template v-if="modelValue">
      <img :src="previewUrl" alt="Pratinjau tulisan tangan" class="upload-dropzone__preview" />
      <span class="upload-dropzone__filename">{{ modelValue.name }}</span>
    </template>
    <template v-else>
      <span class="upload-dropzone__icon" aria-hidden="true">📄</span>
      <span class="upload-dropzone__label">Seret foto ke sini, atau klik untuk memilih</span>
      <span class="upload-dropzone__hint">{{ hint }}</span>
    </template>
  </div>
</template>

<style scoped>
.upload-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  text-align: center;
  padding: 32px 16px;
  border: 2px dashed var(--color-border-hover);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}
.upload-dropzone:hover,
.upload-dropzone:focus-visible {
  border-color: var(--color-seal);
  outline: none;
}
.upload-dropzone--active {
  border-color: var(--color-seal);
  background: var(--color-seal-soft);
}
.upload-dropzone__input {
  display: none;
}
.upload-dropzone__icon {
  font-size: 28px;
}
.upload-dropzone__label {
  font-size: 14px;
  color: var(--color-text);
}
.upload-dropzone__hint {
  font-size: 12px;
  color: var(--color-text-soft);
}
.upload-dropzone__preview {
  display: block;
  max-width: 100%;
  max-height: 240px;
  border-radius: var(--radius-sm);
  margin-bottom: 4px;
}
.upload-dropzone__filename {
  font-size: 13px;
  color: var(--color-text-soft);
  word-break: break-all;
}
</style>
