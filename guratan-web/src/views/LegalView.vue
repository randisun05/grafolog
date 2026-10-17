<script setup>
import { computed } from 'vue'
import { marked } from 'marked'
import privacySource from '../../../legal/privacy-policy.md?raw'
import termsSource from '../../../legal/terms-of-service.md?raw'

const props = defineProps({
  doc: { type: String, required: true },
})

const sources = { privasi: privacySource, syarat: termsSource }

const markdown = computed(() => (sources[props.doc] ?? '').replace(/<!--[\s\S]*?-->/g, '').trim())
const isDraft = computed(() => /\[[A-Z][^\]]*\]/.test(markdown.value))
const html = computed(() => marked.parse(markdown.value))
</script>

<template>
  <article class="legal">
    <p v-if="isDraft" class="legal__draft">
      Draf — dokumen ini masih dalam peninjauan hukum dan dapat berubah sebelum peluncuran resmi.
    </p>
    <!-- eslint-disable-next-line vue/no-v-html -- konten statis dari repo legal/, bukan input pengguna -->
    <div class="legal__body" v-html="html" />
  </article>
</template>

<style scoped>
.legal {
  max-width: 760px;
  margin: 0 auto;
  padding: 32px 16px 64px;
  line-height: 1.65;
}
.legal__draft {
  background: var(--color-seal-soft, #fdecea);
  border: 1px solid var(--color-seal, #c0392b);
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 0.875rem;
  margin-bottom: 24px;
}
.legal__body :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 12px 0;
  display: block;
  overflow-x: auto;
}
.legal__body :deep(th),
.legal__body :deep(td) {
  border: 1px solid var(--color-border, #ddd);
  padding: 6px 10px;
  text-align: left;
  vertical-align: top;
}
.legal__body :deep(h2) {
  margin-top: 28px;
}
</style>
