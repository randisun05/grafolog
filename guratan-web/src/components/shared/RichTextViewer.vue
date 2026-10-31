<script setup>
import { computed } from 'vue'
import DOMPurify from 'dompurify'

const props = defineProps({
  html: { type: String, default: '' },
})

// Sanitasi sisi-klien - pertahanan tambahan untuk konten publik yang
// dibaca siapa pun termasuk tamu, meski penulisnya selalu admin
// tepercaya (lihat guratan-api/CLAUDE.md untuk model kepercayaan admin).
const safeHtml = computed(() => DOMPurify.sanitize(props.html))
</script>

<template>
  <div class="rich-text-viewer" v-html="safeHtml"></div>
</template>

<style scoped>
.rich-text-viewer {
  line-height: 1.7;
  font-size: 15px;
}
.rich-text-viewer :deep(h2) {
  font-family: var(--font-heading);
  font-size: 22px;
  margin: 24px 0 10px;
}
.rich-text-viewer :deep(h3) {
  font-family: var(--font-heading);
  font-size: 18px;
  margin: 20px 0 8px;
}
.rich-text-viewer :deep(p) {
  margin: 0 0 14px;
}
.rich-text-viewer :deep(ul) {
  margin: 0 0 14px;
  padding-left: 22px;
}
.rich-text-viewer :deep(img) {
  max-width: 100%;
  border-radius: var(--radius-sm);
  margin: 12px 0;
}
.rich-text-viewer :deep(a) {
  color: var(--color-seal);
}
</style>
