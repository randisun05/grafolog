<script setup>
defineProps({
  modelValue: { type: Number, default: null },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="score-selector">
    <button
      v-for="n in 10"
      :key="n"
      type="button"
      class="score-btn"
      :class="{ active: modelValue === n }"
      @click="$emit('update:modelValue', n)"
    >
      {{ n }}
    </button>
  </div>
</template>

<style scoped>
.score-selector {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.score-btn {
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  cursor: pointer;
  font-size: 13px;
}
.score-btn:hover {
  border-color: var(--color-seal);
}
.score-btn.active {
  background: var(--color-seal);
  color: #fff;
  border-color: var(--color-seal);
}

@media (max-width: 480px) {
  .score-selector {
    gap: 5px;
  }
  .score-btn {
    width: 28px;
    height: 28px;
    font-size: 12px;
  }
}
</style>
