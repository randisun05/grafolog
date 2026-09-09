<script setup>
import { onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Link from '@tiptap/extension-link'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const toast = useToast()

const editor = useEditor({
  content: props.modelValue,
  extensions: [StarterKit, Image, Link.configure({ openOnClick: false })],
  onUpdate: ({ editor: e }) => emit('update:modelValue', e.getHTML()),
})

watch(
  () => props.modelValue,
  (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
      editor.value.commands.setContent(value, { emitUpdate: false })
    }
  },
)

function setLink() {
  const url = window.prompt('URL tautan:')
  if (url) editor.value.chain().focus().setLink({ href: url }).run()
}

async function insertImage(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return

  const formData = new FormData()
  formData.append('image', file)
  try {
    const { data } = await api.post('/admin/media', formData)
    editor.value.chain().focus().setImage({ src: data.url }).run()
  } catch {
    toast.push('Gagal mengunggah gambar.')
  }
}

onBeforeUnmount(() => editor.value?.destroy())
</script>

<template>
  <div class="rich-text-editor">
    <div v-if="editor" class="rich-text-editor__toolbar">
      <button
        type="button"
        :class="{ 'is-active': editor.isActive('bold') }"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <strong>B</strong>
      </button>
      <button
        type="button"
        :class="{ 'is-active': editor.isActive('italic') }"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        <em>I</em>
      </button>
      <button
        type="button"
        :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        H2
      </button>
      <button
        type="button"
        :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
      >
        H3
      </button>
      <button
        type="button"
        :class="{ 'is-active': editor.isActive('bulletList') }"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        Daftar
      </button>
      <button type="button" @click="setLink">Tautan</button>
      <label class="rich-text-editor__upload">
        Gambar
        <input type="file" accept="image/*" @change="insertImage" />
      </label>
    </div>
    <EditorContent :editor="editor" class="rich-text-editor__content" />
  </div>
</template>

<style scoped>
.rich-text-editor {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}
.rich-text-editor__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 6px;
  background: var(--color-paper-alt);
  border-bottom: 1px solid var(--color-border);
}
.rich-text-editor__toolbar button,
.rich-text-editor__upload {
  font-size: 12.5px;
  padding: 5px 9px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  cursor: pointer;
}
.rich-text-editor__toolbar button.is-active {
  background: var(--color-seal);
  color: #fff;
  border-color: var(--color-seal);
}
.rich-text-editor__upload input {
  display: none;
}
.rich-text-editor__content {
  padding: 12px;
  min-height: 220px;
}
.rich-text-editor__content :deep(.ProseMirror) {
  outline: none;
  min-height: 200px;
}
.rich-text-editor__content :deep(img) {
  max-width: 100%;
  border-radius: var(--radius-sm);
}
</style>
