<script setup>
import ScoreSelector from './ScoreSelector.vue'

const props = defineProps({
  aspek: { type: Object, required: true }, // { kode, nama }
  modelValue: { type: Object, required: true }, // { skor, catatan_grafolog }
})
const emit = defineEmits(['update:modelValue'])

function updateSkor(skor) {
  emit('update:modelValue', { ...props.modelValue, skor })
}

function updateCatatan(event) {
  emit('update:modelValue', { ...props.modelValue, catatan_grafolog: event.target.value })
}
</script>

<template>
  <div class="aspek-row">
    <div class="aspek-row__header">
      <span class="aspek-row__kode">{{ aspek.kode }}</span>
      <span class="aspek-row__nama">{{ aspek.nama }}</span>
    </div>
    <ScoreSelector :model-value="modelValue.skor" @update:model-value="updateSkor" />
    <textarea
      class="aspek-row__catatan"
      placeholder="Catatan grafolog (opsional)"
      :value="modelValue.catatan_grafolog"
      @input="updateCatatan"
    ></textarea>
  </div>
</template>

<style scoped>
.aspek-row {
  padding: 12px 0;
  border-bottom: 1px solid var(--color-border);
  max-width: 100%;
}
.aspek-row__header {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}
.aspek-row__kode {
  color: var(--color-text-soft);
  font-family: monospace;
}
.aspek-row__nama {
  font-weight: 600;
}
.aspek-row__catatan {
  display: block;
  width: 100%;
  margin-top: 8px;
  font-size: 12px;
  min-height: 32px;
  box-sizing: border-box;
}
</style>
