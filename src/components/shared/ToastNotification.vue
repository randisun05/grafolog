<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <div class="toast-stack">
    <button
      v-for="toast in toasts"
      :key="toast.id"
      type="button"
      class="toast-stack__item"
      :class="`toast-stack__item--${toast.type}`"
      @click="dismiss(toast.id)"
    >
      {{ toast.message }}
    </button>
  </div>
</template>

<style scoped>
.toast-stack {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 100;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: min(360px, calc(100vw - 32px));
}
.toast-stack__item {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  font-size: 13px;
  line-height: 1.4;
  box-shadow: var(--shadow-card);
  cursor: pointer;
  color: #fff;
  background: var(--color-ink);
  animation: toast-stack-in 0.15s ease;
}
.toast-stack__item--error {
  background: var(--color-seal);
}
.toast-stack__item--success {
  background: var(--color-sage);
}

@keyframes toast-stack-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
