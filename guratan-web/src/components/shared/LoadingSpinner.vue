<script setup>
defineProps({
  label: { type: String, default: '' },
})
</script>

<template>
  <div class="loading-spinner" role="status">
    <span class="loading-spinner__ring" aria-hidden="true"></span>
    <span v-if="label">{{ label }}</span>
  </div>
</template>

<style scoped>
.loading-spinner {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text-soft);
  font-size: 14px;
}
.loading-spinner__ring {
  width: 18px;
  height: 18px;
  border: 2px solid var(--color-border-hover);
  border-top-color: var(--color-seal);
  border-radius: 50%;
  animation: loading-spinner-spin 0.7s linear infinite;
}

@keyframes loading-spinner-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
