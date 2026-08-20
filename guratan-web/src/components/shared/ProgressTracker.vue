<script setup>
defineProps({
  steps: { type: Array, required: true },
  current: { type: Number, required: true },
})
</script>

<template>
  <ol class="progress-tracker">
    <li
      v-for="(step, i) in steps"
      :key="step"
      class="progress-tracker__step"
      :class="{
        'progress-tracker__step--done': i + 1 < current,
        'progress-tracker__step--active': i + 1 === current,
      }"
    >
      <span class="progress-tracker__index">{{ i + 1 < current ? '✓' : i + 1 }}</span>
      <span class="progress-tracker__label">{{ step }}</span>
    </li>
  </ol>
</template>

<style scoped>
.progress-tracker {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  list-style: none;
  padding: 0;
  margin-bottom: 24px;
}
.progress-tracker__step {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text-soft);
  font-size: 13px;
}
.progress-tracker__index {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 1px solid var(--color-border-hover);
  font-size: 12px;
  font-weight: 600;
}
.progress-tracker__step--active {
  color: var(--color-ink);
  font-weight: 600;
}
.progress-tracker__step--active .progress-tracker__index {
  border-color: var(--color-seal);
  background: var(--color-seal);
  color: #fff;
}
.progress-tracker__step--done .progress-tracker__index {
  border-color: var(--color-sage);
  background: var(--color-sage-soft);
  color: var(--color-sage);
}
</style>
