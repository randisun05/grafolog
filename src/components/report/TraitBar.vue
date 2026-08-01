<script setup>
import { computed } from 'vue'

const props = defineProps({
  nama: { type: String, required: true },
  skor: { type: Number, required: true }, // 1-10
  bandLabel: { type: String, default: '' },
})

const percent = computed(() => (props.skor / 10) * 100)
const colorClass = computed(() => {
  if (props.skor >= 7) return 'high'
  if (props.skor >= 4) return 'medium'
  return 'low'
})
</script>

<template>
  <div class="trait-bar">
    <div class="trait-bar__label">
      <span>{{ nama }}</span>
      <span class="trait-bar__score">{{ skor }}/10 &middot; {{ bandLabel }}</span>
    </div>
    <div class="trait-bar__track">
      <div class="trait-bar__fill" :class="colorClass" :style="{ width: percent + '%' }"></div>
    </div>
  </div>
</template>

<style scoped>
.trait-bar {
  margin-bottom: 10px;
  width: 100%;
}
.trait-bar__label {
  display: flex;
  flex-wrap: wrap;
  gap: 2px 8px;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 4px;
}
.trait-bar__score {
  color: var(--color-text-soft);
  white-space: nowrap;
}
.trait-bar__track {
  height: 8px;
  background: var(--color-ink-faint);
  border-radius: 4px;
  overflow: hidden;
}
.trait-bar__fill {
  height: 100%;
  border-radius: 4px;
}
.trait-bar__fill.low {
  background: #94a3b8;
}
.trait-bar__fill.medium {
  background: #c98a3a;
}
.trait-bar__fill.high {
  background: var(--color-sage);
}
</style>
